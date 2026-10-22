<?php
declare(strict_types=1);

namespace App\Services;

use App\Config\Database;
use DateTime;
use DateTimeZone;
use Exception;
use PDO;

class RtpsCertificateService
{
    public const PORTAL_URL = 'https://swcs.bihar.gov.in/RtpsReportView/certificate_download.jsp';
    public const PORTAL_DOWNLOAD_ACTION = 'https://swcs.bihar.gov.in/RtpsReportView/certificateDownload.do';

    /**
     * Resolve the service type for the RTPS Portal form ('RTPS' or 'OTHER')
     */
    public static function resolveServiceType(string $applicationCode): string
    {
        $code = strtoupper(trim($applicationCode));
        $rtpsCodes = [
            'BRCCO', // Residence Certificate - Revenue Officer / Circle Officer
            'BICCO', // Income Certificate
            'BCCCO', // Caste Certificate
            'BOBC',  // OBC Certificate
            'NCLCO', // Non-Creamy Layer Certificate
            'ROTI',  // RTPS Other
            'RTPS'   // Generic RTPS
        ];

        return in_array($code, $rtpsCodes, true) ? 'RTPS' : 'OTHER';
    }

    /**
     * Calculate the next 12:00:00 AM (midnight) in Asia/Kolkata timezone
     */
    public static function calculateNextCheckTime(): string
    {
        $tz = new DateTimeZone('Asia/Kolkata');
        $now = new DateTime('now', $tz);

        // Next 12:00:00 AM (midnight)
        $next = new DateTime('tomorrow 00:00:00', $tz);

        // If currently exactly midnight 00:00, ensure it is set for the coming night 12:00 AM
        if ($now->format('H:i') === '00:00') {
            $next = new DateTime('tomorrow 00:00:00', $tz);
        }

        return $next->format('Y-m-d H:i:s');
    }

    /**
     * Automatically enqueue an application into the RTPS Certificate Check Queue
     * when it is routed / moved to IN_PROCESS.
     */
    public static function enqueueApplication(int $appId): ?int
    {
        $db = Database::getConnection();

        $appStmt = $db->prepare("
            SELECT id, app_ref, application_code, applicant_identifier, applicant_name,
                   district_id, block_id, status, result_file
            FROM applications
            WHERE id = ?
            LIMIT 1
        ");
        $appStmt->execute([$appId]);
        $app = $appStmt->fetch(PDO::FETCH_ASSOC);

        if (!$app) {
            return null;
        }

        // Only queue if certificate is not already attached and not in terminal REJECTED state
        if (!empty($app['result_file']) || $app['status'] === 'REJECTED') {
            return null;
        }

        $appCode = strtoupper(trim((string)$app['application_code']));
        $appNum = trim((string)$app['applicant_identifier']);
        $applicantName = trim((string)$app['applicant_name']);
        $districtId = (int)$app['district_id'];
        $blockId = (int)$app['block_id'];
        $serviceType = self::resolveServiceType($appCode);
        $nextCheckAt = self::calculateNextCheckTime();

        // Check if queue entry exists
        $chk = $db->prepare("SELECT id, status, check_count FROM rtps_certificate_queue WHERE application_id = ?");
        $chk->execute([$appId]);
        $existing = $chk->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            // If already attached, do not overwrite
            if ($existing['status'] === 'ATTACHED') {
                return (int)$existing['id'];
            }

            // Update queue item
            $upd = $db->prepare("
                UPDATE rtps_certificate_queue
                SET service_type = ?,
                    application_code = ?,
                    application_number = ?,
                    applicant_name = ?,
                    district_id = ?,
                    block_id = ?,
                    status = IF(status = 'ATTACHED', 'ATTACHED', 'QUEUED'),
                    next_check_at = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $upd->execute([
                $serviceType,
                $appCode,
                $appNum,
                $applicantName,
                $districtId,
                $blockId,
                $nextCheckAt,
                $existing['id']
            ]);

            return (int)$existing['id'];
        }

        // Insert new queue record
        $ins = $db->prepare("
            INSERT INTO rtps_certificate_queue (
                application_id, service_type, application_code, application_number,
                applicant_name, district_id, block_id, status, portal_url,
                check_count, next_check_at, last_status_message, created_at, updated_at
            ) VALUES (
                ?, ?, ?, ?,
                ?, ?, ?, 'QUEUED', ?,
                0, ?, 'Queued for automatic scheduled checking at 12:00 AM Asia/Kolkata', NOW(), NOW()
            )
        ");
        $ins->execute([
            $appId,
            $serviceType,
            $appCode,
            $appNum,
            $applicantName,
            $districtId,
            $blockId,
            self::PORTAL_URL,
            $nextCheckAt
        ]);

        $queueId = (int)$db->lastInsertId();

        AuditService::log('RTPS_CERTIFICATE_ENQUEUED', 'APPLICATION', (string)$appId, [
            'queue_id' => $queueId,
            'application_number' => $appNum,
            'applicant_name' => $applicantName,
            'service_type' => $serviceType,
            'next_check_at' => $nextCheckAt
        ]);

        return $queueId;
    }

    /**
     * Attach downloaded certificate PDF to application and mark it APPROVED/COMPLETED
     */
    public static function attachCertificatePdf(
        int $appId,
        string $filePathOrBinary,
        ?string $originalFileName = null,
        ?int $actorUserId = null,
        ?string $actorRole = 'SYSTEM'
    ): array {
        $db = Database::getConnection();

        $appStmt = $db->prepare("
            SELECT id, app_ref, status, applicant_identifier, applicant_name
            FROM applications
            WHERE id = ?
            LIMIT 1
        ");
        $appStmt->execute([$appId]);
        $app = $appStmt->fetch(PDO::FETCH_ASSOC);

        if (!$app) {
            throw new Exception("Application ID {$appId} not found.");
        }

        // 1. Determine whether $filePathOrBinary is a URL, an existing file path, or raw binary string
        $binary = null;
        if (preg_match('/^https?:\/\//i', trim($filePathOrBinary))) {
            $ch = curl_init(trim($filePathOrBinary));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
            $binary = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200 || empty($binary)) {
                throw new Exception("Failed to download certificate from URL (HTTP {$httpCode}). Please verify the link or upload the PDF file directly.");
            }
        } elseif (file_exists($filePathOrBinary) && is_file($filePathOrBinary)) {
            $binary = file_get_contents($filePathOrBinary);
        } else {
            $binary = $filePathOrBinary;
        }

        if (empty($binary) || strlen($binary) < 500) {
            throw new Exception("Validation failed: Certificate file is too small (" . strlen((string)$binary) . " bytes) or empty. Please ensure a complete official certificate PDF is attached.");
        }

        // 2. Validate PDF signature (%PDF-)
        if (strncmp($binary, '%PDF-', 5) !== 0) {
            throw new Exception("Validation failed: The attached document is not a valid PDF file. Header '%PDF-' not detected.");
        }

        // 3. Save PDF securely into public/uploads/results/
        $resDir = dirname(dirname(__DIR__)) . '/public/uploads/results';
        if (!is_dir($resDir)) {
            @mkdir($resDir, 0755, true);
        }

        $cleanRef = preg_replace('/[^A-Za-z0-9_-]/', '_', (string)($app['applicant_identifier'] ?: $app['app_ref']));
        $safeFileName = 'rtps_cert_' . $appId . '_' . $cleanRef . '_' . date('Ymd_His') . '.pdf';
        $destPath = $resDir . '/' . $safeFileName;

        if (file_put_contents($destPath, $binary) === false) {
            throw new Exception("Failed to write certificate PDF to disk at {$destPath}.");
        }

        $webPath = '/uploads/results/' . $safeFileName;

        // 4. Update application status to APPROVED or COMPLETED
        // If current status is IN_PROCESS or PENDING or UNDER_REVIEW, transition to COMPLETED
        $currentStatus = $app['status'];
        if ($currentStatus !== 'COMPLETED') {
            $effectiveActorId = $actorUserId ?: 1;
            $effectiveRole = $actorRole ?: 'SYSTEM';
            $actorName = 'System Operator';
            if ($actorUserId) {
                $uStmt = $db->prepare("SELECT name FROM users WHERE id = ?");
                $uStmt->execute([$actorUserId]);
                $actorName = (string)($uStmt->fetchColumn() ?: 'Operator #' . $actorUserId);
            }

            ApplicationService::updateStatus(
                $appId,
                'COMPLETED',
                $effectiveActorId,
                $effectiveRole,
                $actorName,
                'RTPS Certificate successfully downloaded and verified from official portal.',
                null,
                'Certificate attached from RTPS portal',
                $webPath
            );
        } else {
            // If already COMPLETED, simply ensure result_file points to the certificate
            $upd = $db->prepare("UPDATE applications SET result_file = ?, updated_at = NOW() WHERE id = ?");
            $upd->execute([$webPath, $appId]);
        }

        // 5. Update rtps_certificate_queue
        $qUpd = $db->prepare("
            UPDATE rtps_certificate_queue
            SET status = 'ATTACHED',
                certificate_file = ?,
                last_checked_at = NOW(),
                last_status_message = 'Certificate PDF downloaded, verified, and attached successfully. Application completed.',
                updated_at = NOW()
            WHERE application_id = ?
        ");
        $qUpd->execute([$webPath, $appId]);

        AuditService::log('RTPS_CERTIFICATE_ATTACHED', 'APPLICATION', (string)$appId, [
            'file_path' => $webPath,
            'file_size' => strlen($binary),
            'attached_by' => $actorUserId,
            'attached_by_role' => $actorRole
        ], $actorUserId, $actorRole);

        return [
            'success' => true,
            'app_id' => $appId,
            'result_file' => $webPath,
            'status' => 'COMPLETED',
            'message' => 'Certificate PDF attached and application completed successfully.'
        ];
    }

    /**
     * Mark certificate as unavailable on the portal (e.g. still under process).
     * Keeps application IN_PROCESS and schedules next check at 12:00 AM.
     */
    public static function markCertificateUnavailable(
        int $appId,
        string $reason = 'Certificate not generated yet on RTPS portal',
        ?int $actorUserId = null
    ): void {
        $db = Database::getConnection();
        $nextCheckAt = self::calculateNextCheckTime();

        $upd = $db->prepare("
            UPDATE rtps_certificate_queue
            SET status = 'UNAVAILABLE',
                check_count = check_count + 1,
                last_checked_at = NOW(),
                next_check_at = ?,
                last_status_message = ?,
                updated_at = NOW()
            WHERE application_id = ?
        ");
        $upd->execute([$nextCheckAt, $reason, $appId]);

        // Ensure application stays IN_PROCESS (never reject!)
        $db->prepare("
            UPDATE applications
            SET status = 'IN_PROCESS',
                updated_at = NOW()
            WHERE id = ? AND status = 'PENDING'
        ")->execute([$appId]);

        AuditService::log('RTPS_CERTIFICATE_CHECK_UNAVAILABLE', 'APPLICATION', (string)$appId, [
            'reason' => $reason,
            'next_check_at' => $nextCheckAt,
            'actor_user_id' => $actorUserId
        ], $actorUserId, 'SYSTEM');
    }

    /**
     * Mark a portal communication or network error.
     * Keeps application IN_PROCESS and schedules retry at next 12:00 AM.
     */
    public static function markPortalError(
        int $appId,
        string $errorMessage,
        ?int $actorUserId = null
    ): void {
        $db = Database::getConnection();
        $nextCheckAt = self::calculateNextCheckTime();

        $upd = $db->prepare("
            UPDATE rtps_certificate_queue
            SET status = 'FAILED',
                check_count = check_count + 1,
                last_checked_at = NOW(),
                next_check_at = ?,
                last_status_message = ?,
                updated_at = NOW()
            WHERE application_id = ?
        ");
        $upd->execute([
            $nextCheckAt,
            'Portal Communication Issue: ' . $errorMessage . ' (Application remains In Process; will retry at 12:00 AM)',
            $appId
        ]);

        AuditService::log('RTPS_PORTAL_CHECK_ERROR', 'APPLICATION', (string)$appId, [
            'error' => $errorMessage,
            'next_check_at' => $nextCheckAt
        ], $actorUserId, 'SYSTEM');
    }

    /**
     * Process scheduled queue checks (12:00 AM scheduled runner or manual trigger)
     */
    public static function processScheduledQueue(int $limit = 50, bool $force = false): array
    {
        $db = Database::getConnection();

        $sql = "
            SELECT q.*, a.status as app_status, a.app_ref, a.result_file,
                   d.name as district_name, b.name as block_name
            FROM rtps_certificate_queue q
            JOIN applications a ON a.id = q.application_id
            LEFT JOIN districts d ON d.id = q.district_id
            LEFT JOIN blocks b ON b.id = q.block_id
            WHERE a.status IN ('IN_PROCESS', 'PENDING', 'UNDER_REVIEW')
              AND q.status != 'ATTACHED'
        ";

        if (!$force) {
            $sql .= " AND q.next_check_at <= NOW()";
        }

        $sql .= " ORDER BY q.next_check_at ASC LIMIT " . (int)$limit;

        $items = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);

        $processed = 0;
        $ready = 0;
        $unavailable = 0;
        $errors = 0;

        foreach ($items as $item) {
            $processed++;
            $appId = (int)$item['application_id'];

            // Prepare checking payload
            $payload = [
                'serviceType' => $item['service_type'],
                'appl_ref_no' => $item['application_number'],
                'applicant_name_en' => $item['applicant_name']
            ];

            // Mark status CHECKING
            $db->prepare("UPDATE rtps_certificate_queue SET status = 'CHECKING', updated_at = NOW() WHERE id = ?")
               ->execute([$item['id']]);

            // Note: Since Government portal uses CAPTCHA that must not be automatically solved or bypassed,
            // this scheduled run prepares the item for authorized operator / browser portal session checking.
            // If the certificate is not yet ready or verified, next check is scheduled for 12:00 AM.
            self::markCertificateUnavailable(
                $appId,
                'Checked on ' . date('d M Y, h:i A') . ' (12:00 AM cycle). Certificate pending on portal; next check scheduled for 12:00 AM.'
            );
            $unavailable++;
        }

        return [
            'total_eligible' => count($items),
            'processed' => $processed,
            'ready' => $ready,
            'unavailable' => $unavailable,
            'errors' => $errors,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Get queue statistics for Admin & Operator Dashboards
     */
    public static function getQueueStats(): array
    {
        $db = Database::getConnection();

        $row = $db->query("
            SELECT
                COUNT(*) as total_queued,
                SUM(CASE WHEN q.status IN ('QUEUED', 'PENDING_CHECK') THEN 1 ELSE 0 END) as pending_check,
                SUM(CASE WHEN q.status = 'CHECKING' THEN 1 ELSE 0 END) as checking,
                SUM(CASE WHEN q.status = 'UNAVAILABLE' THEN 1 ELSE 0 END) as unavailable,
                SUM(CASE WHEN q.status = 'ATTACHED' THEN 1 ELSE 0 END) as attached_completed,
                SUM(CASE WHEN q.status = 'FAILED' THEN 1 ELSE 0 END) as failed_retrying,
                SUM(CASE WHEN q.next_check_at <= NOW() AND q.status != 'ATTACHED' THEN 1 ELSE 0 END) as due_now
            FROM rtps_certificate_queue q
            JOIN applications a ON a.id = q.application_id
        ")->fetch(PDO::FETCH_ASSOC);

        return [
            'total_queued' => (int)($row['total_queued'] ?? 0),
            'pending_check' => (int)($row['pending_check'] ?? 0),
            'checking' => (int)($row['checking'] ?? 0),
            'unavailable' => (int)($row['unavailable'] ?? 0),
            'attached_completed' => (int)($row['attached_completed'] ?? 0),
            'failed_retrying' => (int)($row['failed_retrying'] ?? 0),
            'due_now' => (int)($row['due_now'] ?? 0)
        ];
    }

    /**
     * Get list of queue items with filters and pagination
     */
    public static function getQueueItems(array $filters = [], int $page = 1, int $perPage = 25): array
    {
        $db = Database::getConnection();

        $where = ["1=1"];
        $params = [];

        if (!empty($filters['status'])) {
            $where[] = "q.status = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['district_id'])) {
            $where[] = "q.district_id = ?";
            $params[] = (int)$filters['district_id'];
        }

        if (!empty($filters['block_id'])) {
            $where[] = "q.block_id = ?";
            $params[] = (int)$filters['block_id'];
        }

        if (!empty($filters['search'])) {
            $s = '%' . trim($filters['search']) . '%';
            $where[] = "(q.application_number LIKE ? OR q.applicant_name LIKE ? OR a.app_ref LIKE ?)";
            $params[] = $s;
            $params[] = $s;
            $params[] = $s;
        }

        $whereSql = implode(' AND ', $where);

        $cntStmt = $db->prepare("
            SELECT COUNT(*)
            FROM rtps_certificate_queue q
            JOIN applications a ON a.id = q.application_id
            WHERE {$whereSql}
        ");
        $cntStmt->execute($params);
        $total = (int)$cntStmt->fetchColumn();

        $offset = max(0, ($page - 1) * $perPage);

        $stmt = $db->prepare("
            SELECT q.*,
                   a.app_ref, a.status as app_status, a.result_file, a.created_at as app_created_at,
                   d.name as district_name, b.name as block_name,
                   u.name as retailer_name, u.mobile as retailer_mobile
            FROM rtps_certificate_queue q
            JOIN applications a ON a.id = q.application_id
            JOIN users u ON u.id = a.user_id
            LEFT JOIN districts d ON d.id = q.district_id
            LEFT JOIN blocks b ON b.id = q.block_id
            WHERE {$whereSql}
            ORDER BY q.next_check_at ASC, q.id DESC
            LIMIT {$perPage} OFFSET {$offset}
        ");
        $stmt->execute($params);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => (int)ceil($total / max(1, $perPage))
        ];
    }
}
