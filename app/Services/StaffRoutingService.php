<?php
/**
 * StaffRoutingService
 * Handles Application Code Based Staff Routing, Round-Robin allocation,
 * Reassignments, History Tracking, and Admin Staff Routing Rules.
 */

declare(strict_types=1);

namespace App\Services;

use App\Config\Database;
use Exception;
use PDO;

class StaffRoutingService
{
    /**
     * Resolve eligible active Block Operator for an application using exact:
     * - Application district = assignment district = operator assigned district
     * - Application block = assignment block = operator assigned block
     * - Application code = assignment code (normalized uppercase)
     * - Operator role = 'OPERATOR' AND status = 'ACTIVE'
     * - Assignment is_active = 1
     *
     * Uses atomic DB lock and load-balancing round-robin when multiple staff are assigned to the same code.
     */
    public static function resolveOperatorForApplication(
        int $districtId,
        int $blockId,
        string $applicationCode,
        ?PDO $db = null,
        ?int $excludeUserId = null
    ): ?int {
        if ($db === null) {
            $db = Database::getConnection();
        }

        $code = strtoupper(trim($applicationCode));
        if ($districtId <= 0 || $blockId <= 0 || empty($code)) {
            return null;
        }

        // Query eligible active operators with load counter and atomic row lock
        // Order by active load (PENDING + IN_PROCESS) ASC, then tie-break by least recently assigned, then ID
        $hasHistoryTable = true;
        try {
            $chkTable = $db->query("SHOW TABLES LIKE 'application_assignment_history'")->fetch();
            $hasHistoryTable = !empty($chkTable);
        } catch (\Throwable $e) {
            $hasHistoryTable = false;
        }

        $lastSeqSql = $hasHistoryTable
            ? "(SELECT COALESCE(MAX(aah.id), 0) FROM application_assignment_history aah WHERE aah.new_operator_id = u.id)"
            : "0";

        $excludeClause = "";
        $params = [$districtId, $blockId, $code, $districtId, $blockId];
        if ($excludeUserId !== null && $excludeUserId > 0) {
            $excludeClause = " AND u.id != ? ";
            $params[] = $excludeUserId;
        }

        $stmt = $db->prepare("
            SELECT oaca.operator_user_id,
                   (
                       SELECT COUNT(*)
                       FROM applications a
                       WHERE a.assigned_operator_id = u.id
                         AND a.status IN ('PENDING', 'IN_PROCESS')
                   ) AS active_load,
                   {$lastSeqSql} AS last_assignment_seq
            FROM operator_application_code_assignments oaca
            JOIN users u ON oaca.operator_user_id = u.id
            WHERE oaca.district_id = ?
              AND oaca.block_id = ?
              AND oaca.application_code = ?
              AND oaca.is_active = 1
              AND (
                  u.role = 'OPERATOR'
                  OR EXISTS (
                      SELECT 1 FROM user_role_assignments ura
                      WHERE ura.user_id = u.id AND ura.role_code = 'OPERATOR' AND ura.is_active = 1
                  )
              )
              AND u.status = 'ACTIVE'
              AND EXISTS (
                  SELECT 1 FROM operator_block_assignments oba
                  WHERE oba.operator_user_id = u.id AND oba.district_id = ? AND oba.block_id = ? AND oba.is_active = 1
              )
              {$excludeClause}
            ORDER BY active_load ASC, last_assignment_seq ASC, u.id ASC
            LIMIT 1
            FOR UPDATE
        ");
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row || empty($row['operator_user_id'])) {
            // Self-processing check: if the creator was excluded and would otherwise have matched
            if ($excludeUserId !== null && $excludeUserId > 0) {
                try {
                    $chkCreator = $db->prepare("
                        SELECT 1 FROM operator_application_code_assignments oaca
                        JOIN users u ON oaca.operator_user_id = u.id
                        WHERE oaca.district_id = ? AND oaca.block_id = ? AND oaca.application_code = ? AND oaca.is_active = 1
                          AND (u.role = 'OPERATOR' OR EXISTS (SELECT 1 FROM user_role_assignments ura WHERE ura.user_id = u.id AND ura.role_code = 'OPERATOR' AND ura.is_active = 1))
                          AND u.status = 'ACTIVE'
                          AND EXISTS (
                              SELECT 1 FROM operator_block_assignments oba
                              WHERE oba.operator_user_id = u.id AND oba.district_id = ? AND oba.block_id = ? AND oba.is_active = 1
                          )
                          AND u.id = ?
                        LIMIT 1
                    ");
                    $chkCreator->execute([$districtId, $blockId, $code, $districtId, $blockId, $excludeUserId]);
                    if ($chkCreator->fetchColumn()) {
                        AuditService::log('SELF_ASSIGNMENT_PREVENTED', 'USER', (string)$excludeUserId, [
                            'user_id' => $excludeUserId,
                            'district_id' => $districtId,
                            'block_id' => $blockId,
                            'application_code' => $code,
                            'reason' => 'Submitting retailer excluded from staff assignment; application remains unassigned'
                        ], $excludeUserId);
                    }
                } catch (\Throwable $e) {}
            }
            return null;
        }

        return (int)$row['operator_user_id'];
    }

    /**
     * Record assignment details on an application and store immutable assignment history.
     */
    public static function recordAssignment(
        int $applicationId,
        ?int $operatorId,
        string $applicationCode,
        string $assignmentType = 'AUTO',
        ?int $assignedBy = null,
        ?string $reason = null,
        ?PDO $db = null
    ): void {
        if ($db === null) {
            $db = Database::getConnection();
        }

        $code = strtoupper(trim($applicationCode));

        // Fetch current assigned operator (if any)
        $curStmt = $db->prepare("SELECT app_ref, applicant_name, applicant_identifier, district_id, block_id, assigned_operator_id, status FROM applications WHERE id = ? LIMIT 1");
        $curStmt->execute([$applicationId]);
        $app = $curStmt->fetch(PDO::FETCH_ASSOC);
        if (!$app) {
            return;
        }

        $prevOpId = !empty($app['assigned_operator_id']) ? (int)$app['assigned_operator_id'] : null;

        // 1. Update applications row
        $upd = $db->prepare("
            UPDATE applications
            SET application_code = ?,
                assigned_operator_id = ?,
                assigned_at = " . ($operatorId ? "NOW()" : "NULL") . ",
                assignment_method = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $upd->execute([
            $code,
            $operatorId,
            $operatorId ? $assignmentType : 'UNASSIGNED',
            $applicationId
        ]);

        // 2. Insert into application_assignment_history
        try {
            $insHist = $db->prepare("
                INSERT INTO application_assignment_history (
                    application_id, previous_operator_id, new_operator_id, application_code,
                    assigned_by, assignment_type, reason, created_at
                ) VALUES (
                    ?, ?, ?, ?,
                    ?, ?, ?, NOW()
                )
            ");
            $insHist->execute([
                $applicationId,
                $prevOpId,
                $operatorId,
                $code,
                $assignedBy,
                $assignmentType,
                $reason
            ]);
        } catch (\Throwable $e) {
            error_log("Assignment history notice: " . $e->getMessage());
        }

        // 3. Audit log
        $auditEvent = 'APPLICATION_AUTO_ASSIGNED';
        if ($assignmentType === 'MANUAL') {
            $auditEvent = 'APPLICATION_MANUALLY_ASSIGNED';
        } elseif ($assignmentType === 'REASSIGN') {
            $auditEvent = 'APPLICATION_REASSIGNED';
        } elseif (!$operatorId) {
            $auditEvent = 'APPLICATION_LEFT_UNASSIGNED';
        }

        AuditService::log($auditEvent, 'APPLICATION', (string)$applicationId, [
            'application_code' => $code,
            'previous_operator_id' => $prevOpId,
            'assigned_operator_id' => $operatorId,
            'assignment_type' => $assignmentType,
            'reason' => $reason
        ], $assignedBy);

        // 4. In-App Notifications
        if ($operatorId) {
            $appNum = $app['applicant_identifier'] ?: $app['app_ref'];
            NotificationService::create(
                $operatorId,
                "New Application Assigned",
                "{$code} application {$appNum} has been assigned to you.",
                'INFO',
                "/applications/view?id={$applicationId}"
            );
        } else {
            // Notify Super Admins about unassigned application
            $admins = $db->query("SELECT id FROM users WHERE role = 'ADMIN' AND status = 'ACTIVE'")->fetchAll(PDO::FETCH_COLUMN);
            $distName = (string)$db->query("SELECT name FROM districts WHERE id = " . (int)$app['district_id'])->fetchColumn();
            $blkName = (string)$db->query("SELECT name FROM blocks WHERE id = " . (int)$app['block_id'])->fetchColumn();
            foreach ($admins as $admId) {
                NotificationService::create(
                    (int)$admId,
                    "Unassigned Application Received",
                    "Application {$app['app_ref']} ({$code}) for {$distName} > {$blkName} has no assigned staff.",
                    'WARNING',
                    "/admin/applications?assignment_status=UNASSIGNED"
                );
            }
        }
    }

    /**
     * Admin-only manual reassignment of an application to another active block operator
     */
    public static function reassignApplication(
        int $applicationId,
        int $newOperatorId,
        string $reason,
        int $adminUserId
    ): array {
        $db = Database::getConnection();

        $appStmt = $db->prepare("SELECT * FROM applications WHERE id = ? LIMIT 1");
        $appStmt->execute([$applicationId]);
        $app = $appStmt->fetch(PDO::FETCH_ASSOC);

        if (!$app) {
            throw new Exception("Application record not found.");
        }

        // Validate new operator
        $opStmt = $db->prepare("
            SELECT id, name, mobile, role, status, assigned_district_id, assigned_block_id
            FROM users
            WHERE id = ? LIMIT 1
        ");
        $opStmt->execute([$newOperatorId]);
        $op = $opStmt->fetch(PDO::FETCH_ASSOC);

        if (!$op || !AuthService::hasRole($newOperatorId, 'OPERATOR') || $op['status'] !== 'ACTIVE') {
            throw new Exception("Selected staff account is not an active Block Operator.");
        }

        // Self-processing protection: application creator must never be manually assigned to their own application
        if ((int)$app['user_id'] === $newOperatorId) {
            AuditService::log('SELF_ASSIGNMENT_PREVENTED', 'APPLICATION', (string)$applicationId, [
                'user_id' => $newOperatorId,
                'admin_id' => $adminUserId,
                'reason' => 'Admin manual assignment to application creator rejected'
            ], $adminUserId, 'ADMIN');
            throw new Exception("You cannot process an application submitted by your own retailer account.");
        }

        if (!AuthService::isOperatorAssignedToBlock($newOperatorId, (int)$app['district_id'], (int)$app['block_id'])) {
            throw new Exception("Operator belongs to another District/Block. Applications can only be assigned to operators belonging to the same District and Block.");
        }

        $appCode = !empty($app['application_code']) ? strtoupper(trim((string)$app['application_code'])) : 'WB-PASS';

        // Verify operator has active permission for application code
        $codeCheck = $db->prepare("
            SELECT COUNT(*) FROM operator_application_code_assignments
            WHERE operator_user_id = ? AND district_id = ? AND block_id = ? AND application_code = ? AND is_active = 1
        ");
        $codeCheck->execute([$newOperatorId, (int)$app['district_id'], (int)$app['block_id'], $appCode]);
        $hasCode = (int)$codeCheck->fetchColumn();
        if ($hasCode === 0) {
            $anyCodes = (int)$db->query("SELECT COUNT(*) FROM operator_application_code_assignments WHERE operator_user_id = {$newOperatorId} AND district_id = " . (int)$app['district_id'] . " AND block_id = " . (int)$app['block_id'] . " AND is_active = 1")->fetchColumn();
            if ($anyCodes > 0) {
                throw new Exception("Operator '{$op['name']}' is not assigned to handle application code '{$appCode}'.");
            }
        }

        // Perform atomic update
        $managedTx = false;
        if (!$db->inTransaction()) {
            $db->beginTransaction();
            $managedTx = true;
        }
        try {
            self::recordAssignment(
                $applicationId,
                $newOperatorId,
                $appCode,
                'REASSIGN',
                $adminUserId,
                $reason,
                $db
            );

            // Queue WhatsApp to new staff if mobile is valid
            $distName = (string)$db->query("SELECT name FROM districts WHERE id = " . (int)$app['district_id'])->fetchColumn();
            $blkName = (string)$db->query("SELECT name FROM blocks WHERE id = " . (int)$app['block_id'])->fetchColumn();
            $waData = [
                'applicant_name' => $app['applicant_name'],
                'application_number' => $app['applicant_identifier'] ?: '',
                'district_name' => $distName,
                'block_name' => $blkName
            ];
            $afvStmt = $db->prepare("SELECT field_name, field_value FROM application_field_values WHERE application_id = ?");
            $afvStmt->execute([$applicationId]);
            foreach ($afvStmt->fetchAll(PDO::FETCH_ASSOC) as $afv) {
                if (!empty($afv['field_value'])) {
                    $waData[$afv['field_name']] = $afv['field_value'];
                }
            }
            WhatsAppNotificationService::queueSubmissionNotification(
                $applicationId,
                (int)$app['district_id'],
                (int)$app['block_id'],
                $waData,
                $db,
                $newOperatorId // Send only to this specific operator
            );

            if ($managedTx && $db->inTransaction()) {
                $db->commit();
            }

            // Try instant WhatsApp dispatch
            try {
                WhatsAppNotificationService::dispatchPending(3);
            } catch (\Throwable $we) {
                error_log("WhatsApp dispatch error on reassign: " . $we->getMessage());
            }

            return [
                'success' => true,
                'message' => "Application {$app['app_ref']} successfully reassigned to {$op['name']}."
            ];
        } catch (Exception $e) {
            if ($managedTx && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Route pending unassigned applications to matching active operators
     */
    public static function routeUnassignedApplications(?int $districtId = null, ?int $blockId = null, int $adminUserId = 1): array
    {
        $db = Database::getConnection();
        $sql = "
            SELECT a.id, a.app_ref, a.user_id, a.data_type_id, a.application_code, 
                   a.district_id, a.block_id, a.applicant_name, a.applicant_mobile, a.applicant_identifier
            FROM applications a
            WHERE a.assigned_operator_id IS NULL AND a.status = 'PENDING'
        ";
        $params = [];
        if ($districtId !== null && $districtId > 0) {
            $sql .= " AND a.district_id = ?";
            $params[] = $districtId;
        }
        if ($blockId !== null && $blockId > 0) {
            $sql .= " AND a.block_id = ?";
            $params[] = $blockId;
        }
        $sql .= " ORDER BY a.id ASC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $apps = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $routedCount = 0;
        $byOperator = [];

        foreach ($apps as $app) {
            $dist = (int)$app['district_id'];
            $blk = (int)$app['block_id'];
            $code = (string)($app['application_code'] ?: 'BICCO');
            $applicantId = (int)$app['user_id'];

            $opId = self::resolveOperatorForApplication($dist, $blk, $code, $db, $applicantId);
            if ($opId !== null && $opId > 0) {
                $upd = $db->prepare("
                    UPDATE applications 
                    SET assigned_operator_id = ?, assigned_at = NOW(), assignment_method = 'BATCH_ROUTE', updated_at = NOW()
                    WHERE id = ? AND assigned_operator_id IS NULL
                ");
                $upd->execute([$opId, (int)$app['id']]);

                if ($upd->rowCount() > 0) {
                    self::recordAssignment(
                        (int)$app['id'],
                        $opId,
                        $code,
                        'AUTO',
                        $adminUserId,
                        'Bulk routing of unassigned applications to matching operator',
                        $db
                    );

                    // Queue WhatsApp notification to assigned operator
                    try {
                        $distName = (string)$db->query("SELECT name FROM districts WHERE id = {$dist}")->fetchColumn();
                        $blkName = (string)$db->query("SELECT name FROM blocks WHERE id = {$blk}")->fetchColumn();
                        $waData = [
                            'id' => (int)$app['id'],
                            'app_ref' => $app['app_ref'],
                            'application_code' => $code,
                            'application_number' => $app['applicant_identifier'] ?: $app['app_ref'],
                            'district_id' => $dist,
                            'block_id' => $blk,
                            'district_name' => $distName,
                            'block_name' => $blkName,
                            'applicant_name' => $app['applicant_name'],
                            'applicant_mobile' => $app['applicant_mobile']
                        ];
                        WhatsAppNotificationService::queueSubmissionNotification(
                            (int)$app['id'],
                            $dist,
                            $blk,
                            $waData,
                            $db,
                            $opId
                        );
                    } catch (\Throwable $we) {}

                    $routedCount++;
                    $byOperator[$opId] = ($byOperator[$opId] ?? 0) + 1;
                }
            }
        }

        return [
            'total_candidates' => count($apps),
            'routed_count' => $routedCount,
            'by_operator' => $byOperator
        ];
    }

    /**
     * Save application codes routing for an operator
     */
    public static function saveOperatorCodeAssignments(
        int $operatorUserId,
        int $districtId,
        int $blockId,
        array $applicationCodes,
        int $assignedByUserId = 1
    ): array {
        $db = Database::getConnection();

        // 1. Validate operator (can be dedicated OPERATOR or dual-role user with OPERATOR capability)
        $opStmt = $db->prepare("
            SELECT id, name, mobile, role, status, assigned_district_id, assigned_block_id
            FROM users
            WHERE id = ? LIMIT 1
        ");
        $opStmt->execute([$operatorUserId]);
        $op = $opStmt->fetch(PDO::FETCH_ASSOC);

        if (!$op || !AuthService::hasRole($operatorUserId, 'OPERATOR')) {
            throw new Exception("Target user is not a Block Operator.");
        }
        if ($op['status'] !== 'ACTIVE') {
            throw new Exception("Operator '{$op['name']}' is currently INACTIVE. Only active operators can be assigned application codes.");
        }
        if (!AuthService::isOperatorAssignedToBlock($operatorUserId, $districtId, $blockId)) {
            // Automatically ensure block assignment exists for this operator
            self::saveOperatorBlockAssignment($operatorUserId, $districtId, $blockId, true, $assignedByUserId);
        }

        // 2. Validate block service availability is LIVE
        $sbaStmt = $db->prepare("
            SELECT status FROM service_block_availability
            WHERE district_id = ? AND block_id = ? LIMIT 1
        ");
        $sbaStmt->execute([$districtId, $blockId]);
        $sbaStatus = $sbaStmt->fetchColumn();

        // 3. Clean and sanitize selected application codes
        $cleanCodes = [];
        foreach ($applicationCodes as $rawCode) {
            $c = strtoupper(trim((string)$rawCode));
            if ($c !== '' && preg_match('/^[A-Z0-9_\-]+$/', $c)) {
                $cleanCodes[] = $c;
            }
        }
        $cleanCodes = array_values(array_unique($cleanCodes));

        $managedTx = false;
        if (!$db->inTransaction()) {
            $db->beginTransaction();
            $managedTx = true;
        }
        try {
            // First deactivate all existing codes for this operator in this block
            $deact = $db->prepare("
                UPDATE operator_application_code_assignments
                SET is_active = 0, updated_at = NOW()
                WHERE operator_user_id = ? AND district_id = ? AND block_id = ?
            ");
            $deact->execute([$operatorUserId, $districtId, $blockId]);

            // Now activate or insert selected codes
            $insOrUpd = $db->prepare("
                INSERT INTO operator_application_code_assignments (
                    operator_user_id, district_id, block_id, application_code, is_active,
                    priority, assigned_by, created_at, updated_at
                ) VALUES (
                    ?, ?, ?, ?, 1,
                    1, ?, NOW(), NOW()
                )
                ON DUPLICATE KEY UPDATE
                    is_active = 1,
                    assigned_by = VALUES(assigned_by),
                    updated_at = NOW()
            ");

            foreach ($cleanCodes as $c) {
                $insOrUpd->execute([
                    $operatorUserId,
                    $districtId,
                    $blockId,
                    $c,
                    $assignedByUserId
                ]);
            }

            AuditService::log('STAFF_ROUTING_UPDATED', 'USER', (string)$operatorUserId, [
                'district_id' => $districtId,
                'block_id' => $blockId,
                'operator_name' => $op['name'],
                'assigned_codes' => $cleanCodes
            ], $assignedByUserId);

            if ($managedTx && $db->inTransaction()) {
                $db->commit();
            }

            return [
                'success' => true,
                'message' => "Staff routing successfully updated for {$op['name']} (" . count($cleanCodes) . " codes assigned).",
                'assigned_codes' => $cleanCodes
            ];
        } catch (Exception $e) {
            if ($managedTx && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Get active application codes configured in service_rate_rules for a block
     */
    public static function getActiveCodesForBlock(int $districtId, int $blockId, ?PDO $db = null): array
    {
        if ($db === null) {
            $db = Database::getConnection();
        }

        $stmt = $db->prepare("
            SELECT DISTINCT UPPER(TRIM(application_code)) as code, fee_amount
            FROM service_rate_rules
            WHERE district_id = ? AND block_id = ? AND is_active = 1 AND (fee_amount >= 0 OR fee_amount IS NULL) AND application_code IS NOT NULL AND application_code != ''
            ORDER BY application_code ASC
        ");
        $stmt->execute([$districtId, $blockId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Get active block operators for a district and block (supports dual-role)
     */
    public static function saveOperatorBlockAssignment(
        int $operatorUserId,
        int $districtId,
        int $blockId,
        bool|int $isActive = true,
        int $assignedByUserId = 1,
        ?\PDO $db = null
    ): void {
        if ($db === null) {
            $db = Database::getConnection();
        }
        $activeVal = ((bool)$isActive) ? 1 : 0;
        $ins = $db->prepare("
            INSERT INTO operator_block_assignments (operator_user_id, district_id, block_id, is_active, assigned_by, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE is_active = VALUES(is_active), assigned_by = VALUES(assigned_by), updated_at = NOW()
        ");
        $ins->execute([
            $operatorUserId,
            $districtId,
            $blockId,
            $activeVal,
            $assignedByUserId
        ]);
    }

    /**
     * Get active block operators for a district and block (supports multi-block dual-role)
     */
    public static function getOperatorsForBlock(int $districtId, int $blockId, ?PDO $db = null): array
    {
        if ($db === null) {
            $db = Database::getConnection();
        }

        $stmt = $db->prepare("
            SELECT DISTINCT u.id, u.name, u.mobile, u.email, u.status
            FROM users u
            WHERE (
                u.role = 'OPERATOR'
                OR EXISTS (
                    SELECT 1 FROM user_role_assignments ura
                    WHERE ura.user_id = u.id AND ura.role_code = 'OPERATOR' AND ura.is_active = 1
                )
            )
            AND EXISTS (
                SELECT 1 FROM operator_block_assignments oba
                WHERE oba.operator_user_id = u.id AND oba.district_id = ? AND oba.block_id = ? AND oba.is_active = 1
            )
            ORDER BY u.status = 'ACTIVE' DESC, u.name ASC
        ");
        $stmt->execute([$districtId, $blockId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Check if a specific block application code has at least one active staff assigned
     */
    public static function hasStaffAssignedForCode(int $districtId, int $blockId, string $applicationCode, ?PDO $db = null): bool
    {
        if ($db === null) {
            $db = Database::getConnection();
        }

        $code = strtoupper(trim($applicationCode));
        $stmt = $db->prepare("
            SELECT COUNT(*)
            FROM operator_application_code_assignments oaca
            JOIN users u ON oaca.operator_user_id = u.id
            WHERE oaca.district_id = ?
              AND oaca.block_id = ?
              AND oaca.application_code = ?
              AND oaca.is_active = 1
              AND (
                  u.role = 'OPERATOR'
                  OR EXISTS (
                      SELECT 1 FROM user_role_assignments ura
                      WHERE ura.user_id = u.id AND ura.role_code = 'OPERATOR' AND ura.is_active = 1
                  )
              )
              AND u.status = 'ACTIVE'
              AND EXISTS (
                  SELECT 1 FROM operator_block_assignments oba
                  WHERE oba.operator_user_id = u.id AND oba.district_id = ? AND oba.block_id = ? AND oba.is_active = 1
              )
        ");
        $stmt->execute([$districtId, $blockId, $code, $districtId, $blockId]);
        return ((int)$stmt->fetchColumn() > 0);
    }
}
