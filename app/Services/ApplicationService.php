<?php
namespace App\Services;

use App\Config\Database;
use Exception;
use PDO;

class ApplicationService
{
    /**
     * Submit a new application with validation, duplicate checks, and atomic wallet fee debit
     */
    public static function submit(
        int $userId,
        int $dataTypeId,
        int $districtId,
        int $blockId,
        array $submittedValues
    ): array {
        $db = Database::getConnection();

        // 1. Verify user status and location permission
        $userStmt = $db->prepare("SELECT id, role, parent_distributor_id, status FROM users WHERE id = ?");
        $userStmt->execute([$userId]);
        $user = $userStmt->fetch();

        if (!$user || $user['status'] !== 'ACTIVE') {
            throw new Exception(__("account_inactive", "Your account is not active."));
        }

        if (!AuthService::canAccessLocation($userId, $districtId, $blockId)) {
            throw new Exception(__("location_not_permitted", "You are not authorized to submit applications for this district or block."));
        }

        // 2. Fetch Data Type configuration
        $dtStmt = $db->prepare("SELECT * FROM data_types WHERE id = ? AND is_active = 1");
        $dtStmt->execute([$dataTypeId]);
        $dataType = $dtStmt->fetch();

        if (!$dataType) {
            throw new Exception(__("service_unavailable", "Selected service / data type is currently unavailable."));
        }

        $feeAmount = (float)$dataType['fee_amount'];
        $distributorCommission = (float)$dataType['distributor_commission'];
        $distributorId = $user['parent_distributor_id'] ? (int)$user['parent_distributor_id'] : null;

        // 3. Fetch Data Type Fields
        $fStmt = $db->prepare("SELECT * FROM data_type_fields WHERE data_type_id = ? ORDER BY sort_order ASC");
        $fStmt->execute([$dataTypeId]);
        $fields = $fStmt->fetchAll();

        // 4. Validate Fields & Extract Primary Identifiers
        $validatedData = [];
        $applicantName = '';
        $applicantMobile = '';
        $applicantIdentifier = null;

        foreach ($fields as $field) {
            $fName = $field['field_name'];
            $val = trim((string)($submittedValues[$fName] ?? ''));

            // Required check
            if ($field['is_required'] && $val === '') {
                throw new Exception("Field '{$field['label_en']}' is required.");
            }

            // Pattern validation
            if ($val !== '' && !empty($field['validation_pattern'])) {
                if (!preg_match('/' . $field['validation_pattern'] . '/', $val)) {
                    throw new Exception("Field '{$field['label_en']}' has an invalid format.");
                }
            }

            // Common field extraction
            if (in_array($fName, ['applicant_name', 'head_of_family'], true) && empty($applicantName)) {
                $applicantName = $val;
            }
            if (in_array($fName, ['mobile_no', 'applicant_mobile'], true) && empty($applicantMobile)) {
                $applicantMobile = $val;
            }
            if ($fName === $dataType['duplicate_check_field'] || (!empty($field['is_unique']) && empty($applicantIdentifier))) {
                $applicantIdentifier = $val;
            }

            $validatedData[$fName] = [
                'field_id' => $field['id'],
                'field_name' => $fName,
                'value' => $val,
                'is_unique' => (int)$field['is_unique']
            ];
        }

        if (empty($applicantName)) {
            $applicantName = 'Applicant';
        }
        if (empty($applicantMobile)) {
            $applicantMobile = '9999999999';
        }

        // 5. Check Duplicate Entry in this data type
        if (!empty($applicantIdentifier)) {
            $dupStmt = $db->prepare("
                SELECT id, app_ref, created_at FROM applications
                WHERE data_type_id = ? AND applicant_identifier = ? AND status != 'REJECTED'
                LIMIT 1
            ");
            $dupStmt->execute([$dataTypeId, $applicantIdentifier]);
            $existing = $dupStmt->fetch();
            if ($existing) {
                throw new Exception(__("duplicate_warning", "An active application with identifier '{$applicantIdentifier}' already exists in system (Ref: {$existing['app_ref']})."));
            }
        }

        // 6. Check Wallet Balance before starting transaction
        $currentBalance = WalletService::getBalance($userId);
        if ($feeAmount > 0 && $currentBalance < $feeAmount) {
            throw new Exception(__("insufficient_balance", "Insufficient wallet balance (Available: ₹" . number_format($currentBalance, 2) . ", Required: ₹" . number_format($feeAmount, 2) . "). Please recharge your wallet."));
        }

        // 7. Atomic DB Transaction
        $db->beginTransaction();

        try {
            // Generate unique application reference
            $appRef = 'WB-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));

            // Insert Application
            $insApp = $db->prepare("
                INSERT INTO applications (
                    app_ref, user_id, distributor_id, data_type_id, district_id, block_id,
                    applicant_name, applicant_mobile, applicant_identifier, fee_debited,
                    commission_credited, status, is_locked, created_at
                ) VALUES (
                    ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?,
                    ?, 'PENDING', 1, NOW()
                )
            ");
            $insApp->execute([
                $appRef,
                $userId,
                $distributorId,
                $dataTypeId,
                $districtId,
                $blockId,
                $applicantName,
                $applicantMobile,
                $applicantIdentifier,
                $feeAmount,
                ($distributorId && $distributorCommission > 0) ? $distributorCommission : 0.00
            ]);
            $appId = (int)$db->lastInsertId();

            // Insert dynamic field values
            $insVal = $db->prepare("
                INSERT INTO application_field_values (application_id, field_id, field_name, field_value, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            foreach ($validatedData as $f) {
                $insVal->execute([$appId, $f['field_id'], $f['field_name'], $f['value']]);
            }

            // Initial status history
            $insHist = $db->prepare("
                INSERT INTO application_status_history (
                    application_id, from_status, to_status, actor_user_id, actor_name,
                    actor_role, remarks, created_at
                ) VALUES (
                    ?, 'NONE', 'PENDING', ?, ?,
                    ?, 'Application submitted by operator', NOW()
                )
            ");
            $actorName = $_SESSION['user_name'] ?? 'User';
            $actorRole = $_SESSION['user_role'] ?? 'USER';
            $insHist->execute([$appId, $userId, $actorName, $actorRole]);

            // 8. Debit Application Fee from user wallet
            if ($feeAmount > 0) {
                $debitIdemKey = "APP_FEE_DEBIT_{$appId}_{$appRef}";
                WalletService::debit(
                    $userId,
                    $feeAmount,
                    'APPLICATION_FEE_DEBIT',
                    'APPLICATION',
                    (string)$appId,
                    $debitIdemKey,
                    "Application submission fee ({$dataType['name_en']}) - Ref: {$appRef}",
                    "Application submission fee ({$dataType['name_en']}) - Ref: {$appRef}",
                    "Debited for application {$appRef}",
                    $userId
                );
            }

            // 9. Credit Distributor Commission if eligible
            if ($distributorId && $distributorCommission > 0) {
                $commIdemKey = "DIST_COMM_CREDIT_{$appId}_{$distributorId}";
                WalletService::credit(
                    $distributorId,
                    $distributorCommission,
                    'COMMISSION_CREDIT',
                    'APPLICATION',
                    (string)$appId,
                    $commIdemKey,
                    "Commission earned on operator application ({$appRef})",
                    "Commission earned on operator application ({$appRef})",
                    "Commission for app {$appRef}",
                    $userId
                );
            }

            AuditService::log(
                'APPLICATION_SUBMITTED',
                'APPLICATION',
                (string)$appId,
                [
                    'app_ref' => $appRef,
                    'data_type' => $dataType['name_en'],
                    'applicant' => $applicantName,
                    'fee' => $feeAmount
                ],
                $userId,
                $user['role']
            );

            $db->commit();

            return [
                'success' => true,
                'application_id' => $appId,
                'app_ref' => $appRef,
                'fee_debited' => $feeAmount,
                'message' => __("application_submitted_success", "Application submitted successfully! Reference: ") . $appRef
            ];
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Transition application status (Pending -> Under Review -> Approved / Rejected)
     */
    public static function updateStatus(
        int $appId,
        string $newStatus,
        int $actorUserId,
        string $actorRole,
        string $actorName,
        ?string $remarks = null,
        ?string $rejectionReason = null
    ): array {
        $db = Database::getConnection();

        $stmt = $db->prepare("SELECT a.*, dt.name_en as dt_name_en, dt.name_hi as dt_name_hi FROM applications a JOIN data_types dt ON a.data_type_id = dt.id WHERE a.id = ? FOR UPDATE");

        $db->beginTransaction();
        try {
            $stmt->execute([$appId]);
            $app = $stmt->fetch();

            if (!$app) {
                throw new Exception("Application record not found.");
            }

            $currentStatus = $app['status'];

            // Validation of status transitions
            $validStatuses = ['PENDING', 'UNDER_REVIEW', 'APPROVED', 'REJECTED'];
            if (!in_array($newStatus, $validStatuses, true)) {
                throw new Exception("Invalid target status.");
            }

            if ($currentStatus === $newStatus) {
                $db->commit();
                return ['success' => true, 'message' => 'Status is already ' . $newStatus];
            }

            if ($newStatus === 'REJECTED' && empty(trim((string)$rejectionReason))) {
                throw new Exception(__("rejection_reason_mandatory", "A specific reason is mandatory when rejecting an application."));
            }

            // Role permissions check
            if ($actorRole === 'DISTRIBUTOR') {
                // Check if admin allows distributor review
                $setStmt = $db->query("SELECT setting_value FROM settings WHERE setting_key = 'distributor_review_required'");
                $reviewEnabled = (int)($setStmt->fetchColumn() ?: 1);

                if (!$reviewEnabled) {
                    throw new Exception("Distributor review is currently disabled by administrator.");
                }

                // Distributor can only mark UNDER_REVIEW or recommend
                if (!in_array($newStatus, ['UNDER_REVIEW', 'REJECTED'], true)) {
                    throw new Exception("Distributors can only review or mark under-review. Final approval is reserved for Administration.");
                }

                // Distributor scope check
                if ((int)$app['distributor_id'] !== $actorUserId) {
                    throw new Exception("You are not authorized to review applications outside your team.");
                }
            }

            // Update application status
            $upd = $db->prepare("
                UPDATE applications
                SET status = ?,
                    rejection_reason = ?,
                    remarks = ?,
                    final_action_at = NOW(),
                    final_action_by = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $upd->execute([$newStatus, $rejectionReason, $remarks, $actorUserId, $appId]);

            // Record status history
            $hist = $db->prepare("
                INSERT INTO application_status_history (
                    application_id, from_status, to_status, actor_user_id,
                    actor_name, actor_role, remarks, rejection_reason, created_at
                ) VALUES (
                    ?, ?, ?, ?,
                    ?, ?, ?, ?, NOW()
                )
            ");
            $hist->execute([
                $appId,
                $currentStatus,
                $newStatus,
                $actorUserId,
                $actorName,
                $actorRole,
                $remarks,
                $rejectionReason
            ]);

            // Refund logic if application is REJECTED and setting is active
            if ($newStatus === 'REJECTED') {
                $refSet = $db->query("SELECT setting_value FROM settings WHERE setting_key = 'refund_on_rejection'");
                $refundActive = (int)($refSet->fetchColumn() ?: 1);

                $feeDebited = (float)$app['fee_debited'];
                if ($refundActive && $feeDebited > 0) {
                    $refundKey = "REFUND_APP_{$appId}_FEE";
                    WalletService::credit(
                        (int)$app['user_id'],
                        $feeDebited,
                        'REFUND_CREDIT',
                        'APPLICATION',
                        (string)$appId,
                        $refundKey,
                        "Automatic fee refund on rejected application ({$app['app_ref']})",
                        "Automatic fee refund on rejected application ({$app['app_ref']})",
                        $rejectionReason,
                        $actorUserId
                    );
                }

                // If distributor was paid commission, reverse it cleanly
                $distComm = (float)$app['commission_credited'];
                if ($app['distributor_id'] && $distComm > 0) {
                    $revKey = "COMM_REV_APP_{$appId}_{$app['distributor_id']}";
                    WalletService::debit(
                        (int)$app['distributor_id'],
                        $distComm,
                        'APPLICATION_FEE_DEBIT',
                        'APPLICATION',
                        (string)$appId,
                        $revKey,
                        "Commission reversal on rejected application ({$app['app_ref']})",
                        "Commission reversal on rejected application ({$app['app_ref']})",
                        "Reversed due to rejection",
                        $actorUserId
                    );
                }
            }

            AuditService::log(
                'APPLICATION_STATUS_UPDATE',
                'APPLICATION',
                (string)$appId,
                [
                    'from' => $currentStatus,
                    'to' => $newStatus,
                    'app_ref' => $app['app_ref'],
                    'rejection_reason' => $rejectionReason
                ],
                $actorUserId,
                $actorRole
            );

            $db->commit();

            return [
                'success' => true,
                'status' => $newStatus,
                'message' => "Application status updated to {$newStatus}."
            ];
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }
}
