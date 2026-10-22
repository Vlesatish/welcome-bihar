<?php
declare(strict_types=1);

namespace App\Controllers\Traits;

use App\Config\Database;
use App\Services\AuthService;
use App\Services\RtpsCertificateService;
use PDO;

trait RtpsQueueTrait
{
    /**
     * RTPS Certificate Check Queue Manager
     * GET /admin/rtps-queue
     */
    public function rtpsQueue(): void
    {
        if (!AuthService::isStaffOrAdmin()) {
            $_SESSION['flash_error'] = 'Access restricted: Staff or Administrator permissions required.';
            header('Location: /login');
            exit;
        }

        $db = Database::getConnection();

        $page = max(1, (int)($_GET['page'] ?? 1));
        $filters = [
            'status' => trim($_GET['status'] ?? ''),
            'district_id' => (int)($_GET['district_id'] ?? 0),
            'block_id' => (int)($_GET['block_id'] ?? 0),
            'search' => trim($_GET['search'] ?? '')
        ];

        $queueData = RtpsCertificateService::getQueueItems($filters, $page, 20);
        $stats = RtpsCertificateService::getQueueStats();

        // Load active districts
        $districts = $db->query("SELECT id, name, name_hi FROM districts WHERE is_active = 1 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

        // Load active blocks for selected district
        $blocks = [];
        if (!empty($filters['district_id'])) {
            $bStmt = $db->prepare("SELECT id, name, name_hi FROM blocks WHERE district_id = ? AND is_active = 1 ORDER BY name ASC");
            $bStmt->execute([$filters['district_id']]);
            $blocks = $bStmt->fetchAll(PDO::FETCH_ASSOC);
        }

        // All active blocks grouped by district for modal dropdowns
        $allBlocksGrouped = [];
        $bRows = $db->query("SELECT id, district_id, name FROM blocks WHERE is_active = 1 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($bRows as $br) {
            $allBlocksGrouped[$br['district_id']][] = $br;
        }

        $items = $queueData['items'];
        $totalItems = $queueData['total'];
        $totalPages = $queueData['total_pages'];
        $currentPage = $queueData['page'];

        require dirname(__DIR__, 2) . '/Views/admin/rtps_queue.php';
    }

    /**
     * Attach Downloaded RTPS Certificate PDF
     * POST /admin/rtps-queue/attach
     */
    public function attachRtpsCertificate(): void
    {
        if (!AuthService::isStaffOrAdmin()) {
            $_SESSION['flash_error'] = 'Access denied: Staff or Administrator access required.';
            header('Location: /login');
            exit;
        }

        if (!AuthService::validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $_SESSION['flash_error'] = 'Invalid security token (CSRF). Please try again.';
            header('Location: /admin/rtps-queue');
            exit;
        }

        $appId = (int)($_POST['app_id'] ?? 0);
        if ($appId <= 0) {
            $_SESSION['flash_error'] = 'Invalid application identifier.';
            header('Location: /admin/rtps-queue');
            exit;
        }

        $actorId = AuthService::id();
        $actorRole = AuthService::role();

        $certUrl = trim((string)($_POST['certificate_url'] ?? ''));
        $hasUpload = (!empty($_FILES['certificate_pdf']['name']) && $_FILES['certificate_pdf']['error'] === UPLOAD_ERR_OK);

        if (!$hasUpload && empty($certUrl)) {
            $_SESSION['flash_error'] = 'Please either upload a certificate PDF file or provide the official download URL.';
            header('Location: /admin/rtps-queue');
            exit;
        }

        try {
            if ($hasUpload) {
                $file = $_FILES['certificate_pdf'];
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                if ($ext !== 'pdf') {
                    throw new \Exception('Invalid file extension. Only genuine PDF certificates (.pdf) are allowed.');
                }
                $source = $file['tmp_name'];
                $origName = $file['name'];
            } else {
                $source = $certUrl;
                $origName = 'official_rtps_certificate.pdf';
            }

            $res = RtpsCertificateService::attachCertificatePdf(
                $appId,
                $source,
                $origName,
                $actorId,
                $actorRole
            );

            $_SESSION['flash_success'] = 'Certificate attached successfully! Application is now marked COMPLETED and available for retailer download.';
        } catch (\Throwable $e) {
            $_SESSION['flash_error'] = 'Failed to attach certificate: ' . $e->getMessage();
        }

        header('Location: /admin/rtps-queue');
        exit;
    }

    /**
     * Mark Certificate Unavailable on Portal (Application remains IN_PROCESS, next check scheduled at 12:00 AM)
     * POST /admin/rtps-queue/unavailable
     */
    public function markRtpsUnavailable(): void
    {
        if (!AuthService::isStaffOrAdmin()) {
            $_SESSION['flash_error'] = 'Access denied: Staff or Administrator access required.';
            header('Location: /login');
            exit;
        }

        if (!AuthService::validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $_SESSION['flash_error'] = 'Invalid security token (CSRF). Please try again.';
            header('Location: /admin/rtps-queue');
            exit;
        }

        $appId = (int)($_POST['app_id'] ?? 0);
        $reason = trim((string)($_POST['reason'] ?? ''));
        if (empty($reason)) {
            $reason = 'Certificate not generated yet on RTPS portal (Application under process)';
        }

        $actorId = AuthService::id();

        try {
            RtpsCertificateService::markCertificateUnavailable($appId, $reason, $actorId);
            $_SESSION['flash_success'] = 'Application recorded as Unavailable on portal. Status remains IN_PROCESS, and next check is scheduled for 12:00 AM Asia/Kolkata.';
        } catch (\Throwable $e) {
            $_SESSION['flash_error'] = 'Action failed: ' . $e->getMessage();
        }

        header('Location: /admin/rtps-queue');
        exit;
    }

    /**
     * Trigger Scheduled Queue Run (Check eligible applications)
     * POST /admin/rtps-queue/trigger
     */
    public function triggerRtpsQueueCheck(): void
    {
        if (!AuthService::isAdmin()) {
            $_SESSION['flash_error'] = 'Access restricted: Administrator access required to trigger queue worker.';
            header('Location: /admin/rtps-queue');
            exit;
        }

        if (!AuthService::validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $_SESSION['flash_error'] = 'Invalid security token (CSRF). Please try again.';
            header('Location: /admin/rtps-queue');
            exit;
        }

        $force = !empty($_POST['force']);

        try {
            $res = RtpsCertificateService::processScheduledQueue(50, $force);
            $_SESSION['flash_success'] = "Queue worker executed: {$res['processed']} eligible applications checked. Next cycle scheduled for 12:00 AM.";
        } catch (\Throwable $e) {
            $_SESSION['flash_error'] = 'Queue trigger error: ' . $e->getMessage();
        }

        header('Location: /admin/rtps-queue');
        exit;
    }
}
