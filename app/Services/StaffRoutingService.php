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
        ?PDO $db = null
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
        $stmt = $db->prepare("
            SELECT oaca.operator_user_id,
                   (
                       SELECT COUNT(*)
                       FROM applications a
                       WHERE a.assigned_operator_id = u.id
                         AND a.status IN ('PENDING', 'IN_PROCESS')
                   ) AS active_load,
                   (
                       SELECT COALESCE(MAX(aah.id), 0)
                       FROM application_assignment_history aah
                       WHERE aah.new_operator_id = u.id
                   ) AS last_assignment_seq
            FROM operator_application_code_assignments oaca
            JOIN users u ON oaca.operator_user_id = u.id
            WHERE oaca.district_id = ?
              AND oaca.block_id = ?
              AND oaca.application_code = ?
              AND oaca.is_active = 1
              AND u.role = 'OPERATOR'
              AND u.status = 'ACTIVE'
              AND u.assigned_district_id = ?
              AND u.assigned_block_id = ?
            ORDER BY active_load ASC, last_assignment_seq ASC, u.id ASC
            LIMIT 1
            FOR UPDATE
        ");
        $stmt->execute([$districtId, $blockId, $code, $districtId, $blockId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row || empty($row['operator_user_id'])) {
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

        if (!$op || $op['role'] !== 'OPERATOR' || $op['status'] !== 'ACTIVE') {
            throw new Exception("Selected staff account is not an active Block Operator.");
        }

        if ((int)$op['assigned_district_id'] !== (int)$app['district_id'] || (int)$op['assigned_block_id'] !== (int)$app['block_id']) {
            throw new Exception("Operator belongs to another District/Block. Applications can only be assigned to operators belonging to the same District and Block.");
        }

        $appCode = !empty($app['application_code']) ? $app['application_code'] : 'WB-PASS';

        // Perform atomic update
        $db->beginTransaction();
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

            $db->commit();

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
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Save application codes routing for an operator
     */
    public static function saveOperatorCodeAssignments(
        int $operatorUserId,
        int $districtId,
        int $blockId,
        array $applicationCodes,
        int $assignedByUserId
    ): array {
        $db = Database::getConnection();

        // 1. Validate operator
        $opStmt = $db->prepare("
            SELECT id, name, mobile, role, status, assigned_district_id, assigned_block_id
            FROM users
            WHERE id = ? LIMIT 1
        ");
        $opStmt->execute([$operatorUserId]);
        $op = $opStmt->fetch(PDO::FETCH_ASSOC);

        if (!$op || $op['role'] !== 'OPERATOR') {
            throw new Exception("Target user is not a Block Operator.");
        }
        if ($op['status'] !== 'ACTIVE') {
            throw new Exception("Operator '{$op['name']}' is currently INACTIVE. Only active operators can be assigned application codes.");
        }
        if ((int)$op['assigned_district_id'] !== $districtId || (int)$op['assigned_block_id'] !== $blockId) {
            throw new Exception("Operator '{$op['name']}' belongs to another District and Block. Assignment must match operator's configured jurisdiction.");
        }

        // 2. Validate block service availability is LIVE
        $sbaStmt = $db->prepare("
            SELECT status FROM service_block_availability
            WHERE district_id = ? AND block_id = ? LIMIT 1
        ");
        $sbaStmt->execute([$districtId, $blockId]);
        $sbaStatus = $sbaStmt->fetchColumn();
        if ($sbaStatus !== 'LIVE') {
            throw new Exception("Block service is currently disabled or not LIVE for this block. Enable block service before assigning routing.");
        }

        // 3. Fetch active block rates for this block
        $rateCodesStmt = $db->prepare("
            SELECT DISTINCT UPPER(TRIM(application_code)) as code
            FROM service_rate_rules
            WHERE district_id = ? AND block_id = ? AND is_active = 1 AND fee_amount > 0 AND application_code IS NOT NULL AND application_code != ''
        ");
        $rateCodesStmt->execute([$districtId, $blockId]);
        $activeBlockRateCodes = $rateCodesStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

        $cleanCodes = [];
        foreach ($applicationCodes as $rawCode) {
            $c = strtoupper(trim((string)$rawCode));
            if ($c === '') continue;
            if (!in_array($c, $activeBlockRateCodes, true)) {
                throw new Exception("Application code '{$c}' has no active rate configured for this block. Codes must come from active block-specific rate records.");
            }
            $cleanCodes[] = $c;
        }
        $cleanCodes = array_values(array_unique($cleanCodes));

        $db->beginTransaction();
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

            $db->commit();

            return [
                'success' => true,
                'message' => "Staff routing successfully updated for {$op['name']} (" . count($cleanCodes) . " codes assigned).",
                'assigned_codes' => $cleanCodes
            ];
        } catch (Exception $e) {
            if ($db->inTransaction()) {
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
            WHERE district_id = ? AND block_id = ? AND is_active = 1 AND fee_amount > 0 AND application_code IS NOT NULL AND application_code != ''
            ORDER BY application_code ASC
        ");
        $stmt->execute([$districtId, $blockId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Get active block operators for a district and block
     */
    public static function getOperatorsForBlock(int $districtId, int $blockId, ?PDO $db = null): array
    {
        if ($db === null) {
            $db = Database::getConnection();
        }

        $stmt = $db->prepare("
            SELECT id, name, mobile, email, status
            FROM users
            WHERE role = 'OPERATOR' AND assigned_district_id = ? AND assigned_block_id = ?
            ORDER BY status = 'ACTIVE' DESC, name ASC
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
              AND u.role = 'OPERATOR'
              AND u.status = 'ACTIVE'
              AND u.assigned_district_id = ?
              AND u.assigned_block_id = ?
        ");
        $stmt->execute([$districtId, $blockId, $code, $districtId, $blockId]);
        return ((int)$stmt->fetchColumn() > 0);
    }
}
