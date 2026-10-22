<?php

declare(strict_types=1);

namespace App\Services\Traits;

use App\Config\Database;
use App\Services\AuditService;
use App\Services\NotificationService;
use App\Services\WhatsAppNotificationService;
use App\Services\CommissionService;
use App\Services\AuthService;
use Exception;
use PDO;

trait ApplicationStatusTrait
{
    /**
     * Transition application status (Pending -> Under Review -> Approved / Rejected / Completed)
     */
    public static function updateStatus(
        int $appId,
        string $newStatus,
        int $actorUserId,
        string $actorRole,
        string $actorName,
        ?string $remarks = null,
        ?string $rejectionReason = null,
        ?string $adminRemarks = null,
        ?string $resultFilePath = null
    ): array {
        $db = Database::getConnection();

        $stmt = $db->prepare("SELECT a.*, dt.name_en as dt_name_en, dt.name_hi as dt_name_hi FROM applications a JOIN data_types dt ON a.data_type_id = dt.id WHERE a.id = ? FOR UPDATE");

        $ownsTransaction = false;
        if (!$db->inTransaction()) {
            $db->beginTransaction();
            $ownsTransaction = true;
        }
        try {
            $stmt->execute([$appId]);
            $app = $stmt->fetch();

            if (!$app) {
                throw new Exception("Application record not found.");
            }

            $currentStatus = $app['status'];

            // Strict Self-Processing Protection: A user must never process their own submitted application
            if ((int)$app['user_id'] === $actorUserId && $actorRole !== 'ADMIN') {
                AuditService::log('SELF_ASSIGNMENT_PREVENTED', 'APPLICATION', (string)$appId, [
                    'user_id' => $actorUserId,
                    'reason' => 'Attempted to process/update status on self-submitted application'
                ], $actorUserId);
                throw new Exception("You cannot process an application submitted by your own retailer account.");
            }

            // Resolve effective role for dual-role user (USER account acting as OPERATOR)
            if ($actorRole === 'USER' && AuthService::canAccessOperatorMode($actorUserId)) {
                $actorRole = 'OPERATOR';
            }

            // 1. Role-based authorization: Pure USER cannot update status
            if ($actorRole === 'USER' || !in_array($actorRole, ['ADMIN', 'OPERATOR', 'DISTRIBUTOR'], true)) {
                throw new Exception("Unauthorized: Role '{$actorRole}' is not permitted to update application status.");
            }

            // Operator scoping check
            if ($actorRole === 'OPERATOR') {
                if (!AuthService::canOperatorAccessApplication($actorUserId, $appId)) {
                    throw new Exception("Unauthorized: You do not have permission to process applications outside your assigned District and Block or not assigned to you.");
                }
            }

            // 2. Validate target status existence
            $validStatuses = ['PENDING', 'IN_PROCESS', 'UNDER_REVIEW', 'APPROVED', 'REJECTED', 'COMPLETED'];
            if (!in_array($newStatus, $validStatuses, true)) {
                throw new Exception("Invalid target status '{$newStatus}'.");
            }

            if ($currentStatus === $newStatus && empty($resultFilePath) && empty($adminRemarks)) {
                $db->commit();
                return ['success' => true, 'message' => 'Status is already ' . $newStatus];
            }

            // 3. Legal status transition check: Terminal statuses cannot transition
            if ($currentStatus === 'REJECTED') {
                throw new Exception("Application is in terminal state 'REJECTED' and cannot be modified.");
            }

            // Allowed transitions:
            $allowedTransitions = [
                'PENDING' => ['IN_PROCESS', 'UNDER_REVIEW', 'APPROVED', 'REJECTED', 'COMPLETED'],
                'IN_PROCESS' => ['APPROVED', 'COMPLETED', 'REJECTED', 'PENDING'],
                'UNDER_REVIEW' => ['IN_PROCESS', 'APPROVED', 'REJECTED', 'COMPLETED'],
                'APPROVED' => ['IN_PROCESS', 'COMPLETED', 'REJECTED', 'APPROVED'],
                'COMPLETED' => ['APPROVED', 'COMPLETED'] // Allow admin to adjust certificate if needed
            ];

            if (!isset($allowedTransitions[$currentStatus]) || !in_array($newStatus, $allowedTransitions[$currentStatus], true)) {
                throw new Exception("Illegal status transition from '{$currentStatus}' to '{$newStatus}'.");
            }

            // 4. Role-specific constraints
            if ($actorRole === 'DISTRIBUTOR') {
                $setStmt = $db->query("SELECT setting_value FROM settings WHERE setting_key = 'distributor_review_required'");
                $reviewEnabled = (int)($setStmt->fetchColumn() ?: 1);

                if (!$reviewEnabled) {
                    throw new Exception("Distributor review is currently disabled by administrator.");
                }

                if ($newStatus !== 'UNDER_REVIEW') {
                    throw new Exception("Distributors are only authorized to mark applications as UNDER_REVIEW. Final approval, completion, and rejection are reserved for Administration.");
                }

                if ((int)$app['distributor_id'] !== $actorUserId) {
                    throw new Exception("You are not authorized to review applications outside your team.");
                }
            }

            if ($newStatus === 'REJECTED' && empty(trim((string)$rejectionReason))) {
                throw new Exception(__("rejection_reason_mandatory", "A specific reason is mandatory when rejecting an application."));
            }

            // Update application status with proper reviewer/final action timestamps
            $finalRemarks = $remarks ?: $adminRemarks;
            $resultFile = $resultFilePath ?: $app['result_file'];

            if ($actorRole === 'DISTRIBUTOR') {
                $upd = $db->prepare("
                    UPDATE applications
                    SET status = ?,
                        remarks = ?,
                        distributor_reviewed_at = NOW(),
                        distributor_reviewed_by = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $upd->execute([$newStatus, $finalRemarks, $actorUserId, $appId]);
            } else {
                // Admin or Operator action
                $completedAt = ($newStatus === 'COMPLETED' || $newStatus === 'APPROVED') ? date('Y-m-d H:i:s') : $app['completed_at'];
                $completedBy = ($newStatus === 'COMPLETED' || $newStatus === 'APPROVED') ? $actorUserId : $app['completed_by'];

                $upd = $db->prepare("
                    UPDATE applications
                    SET status = ?,
                        rejection_reason = ?,
                        remarks = ?,
                        admin_remarks = ?,
                        result_file = ?,
                        final_action_at = NOW(),
                        final_action_by = ?,
                        completed_at = ?,
                        completed_by = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $upd->execute([
                    $newStatus,
                    $rejectionReason,
                    $finalRemarks,
                    $adminRemarks ?: $finalRemarks,
                    $resultFile,
                    $actorUserId,
                    $completedAt,
                    $completedBy,
                    $appId
                ]);
            }

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
                $finalRemarks,
                $rejectionReason
            ]);

            // Payout Distributor Commission upon COMPLETED (matching eCyberCafe flow)
            if ($newStatus === 'COMPLETED' && $currentStatus !== 'COMPLETED') {
                $isCommPaid = (int)($app['is_commission_paid'] ?? 0);
                $distId = $app['distributor_id'] ? (int)$app['distributor_id'] : null;

                if ($distId && !$isCommPaid) {
                    $commFee = (float)($app['commission_credited'] ?? 0.00);
                    if ($commFee <= 0) {
                        $commFee = (float)$db->query("SELECT distributor_commission FROM data_types WHERE id = " . (int)$app['data_type_id'])->fetchColumn();
                        if ($commFee <= 0) $commFee = 10.00;
                    }

                    if ($commFee > 0) {
                        $commIdemKey = "DIST_COMM_CREDIT_{$appId}_{$distId}";
                        WalletService::credit(
                            $distId,
                            $commFee,
                            'COMMISSION_CREDIT',
                            'APPLICATION',
                            (string)$appId,
                            $commIdemKey,
                            "Commission earned on operator application ({$app['app_ref']})",
                            "Commission earned on operator application ({$app['app_ref']})",
                            "Commission for app {$app['app_ref']}",
                            $actorUserId
                        );

                        $db->prepare("UPDATE applications SET is_commission_paid = 1, commission_credited = ? WHERE id = ?")->execute([$commFee, $appId]);
                    }
                }
            }

            // Refund logic if application is REJECTED
            if ($newStatus === 'REJECTED') {
                $refSet = $db->query("SELECT setting_value FROM settings WHERE setting_key = 'refund_on_rejection'");
                $refundActive = (int)($refSet->fetchColumn() ?: 1);

                $feeDebited = (float)$app['fee_debited'];
                $isAlreadyRefunded = (int)($app['is_refunded'] ?? 0);

                if ($refundActive && $feeDebited > 0 && !$isAlreadyRefunded) {
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

                    $db->prepare("UPDATE applications SET is_refunded = 1 WHERE id = ?")->execute([$appId]);
                }

                // If distributor was previously paid commission, reverse it cleanly
                if (!empty($app['is_commission_paid']) && $app['distributor_id'] && (float)$app['commission_credited'] > 0) {
                    $distComm = (float)$app['commission_credited'];
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
                    $db->prepare("UPDATE applications SET is_commission_paid = 0 WHERE id = ?")->execute([$appId]);
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

            // In-app Notification to Submitting User
            $notifType = 'INFO';
            if ($newStatus === 'COMPLETED' || $newStatus === 'APPROVED') {
                $notifType = 'SUCCESS';
            } elseif ($newStatus === 'REJECTED') {
                $notifType = 'DANGER';
            } elseif ($newStatus === 'UNDER_REVIEW') {
                $notifType = 'WARNING';
            }

            $notifMsg = "Your application {$app['app_ref']} status is now {$newStatus}.";
            if (!empty($adminRemarks)) {
                $notifMsg .= " Remarks: " . $adminRemarks;
            } elseif (!empty($rejectionReason)) {
                $notifMsg .= " Reason: " . $rejectionReason;
            }

            NotificationService::create(
                (int)$app['user_id'],
                "Application Status: {$newStatus}",
                $notifMsg,
                $notifType,
                "/applications/view?id={$appId}"
            );

            // If result file was uploaded/attached
            if (!empty($resultFilePath) && $resultFilePath !== ($app['result_file'] ?? null)) {
                NotificationService::create(
                    (int)$app['user_id'],
                    "Result File Uploaded",
                    "A result certificate / document has been uploaded for your application ({$app['app_ref']}).",
                    'SUCCESS',
                    "/applications/view?id={$appId}"
                );
                AuditService::log('RESULT_FILE_UPLOADED', 'APPLICATION', (string)$appId, ['file' => $resultFilePath], $actorUserId, $actorRole);
            }

            if ($ownsTransaction && $db->inTransaction()) {
                $db->commit();
            }

            return [
                'success' => true,
                'status' => $newStatus,
                'message' => "Application status updated to {$newStatus}."
            ];
        } catch (Exception $e) {
            if ($ownsTransaction && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }
}
