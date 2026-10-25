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

        // Run non-blocking auto-check for any due items (next_check_at <= NOW())
        RtpsCertificateService::autoCheckDueQueue(10);

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

        $preset = trim((string)($_POST['schedule_preset'] ?? '5_hours'));
        $customNext = null;

        if ($preset === '7_am_today') {
            $customNext = date('Y-m-d 07:00:00');
        } elseif ($preset === '7_am_tomorrow') {
            $customNext = date('Y-m-d 07:00:00', strtotime('+1 day'));
        } elseif ($preset === 'due_now') {
            $customNext = date('Y-m-d H:i:s', strtotime('-1 minute'));
        } elseif ($preset === 'custom' && !empty($_POST['custom_datetime'])) {
            $customNext = date('Y-m-d H:i:s', strtotime($_POST['custom_datetime']));
        } else {
            // Default 5-hour cycle
            $customNext = date('Y-m-d H:i:s', strtotime('+5 hours'));
        }

        try {
            RtpsCertificateService::markCertificateUnavailable($appId, $reason, $actorId, $customNext);
            $_SESSION['flash_success'] = 'Application recorded as Unavailable on portal. Status remains IN_PROCESS, next check scheduled for ' . date('d M Y, h:i A', strtotime($customNext)) . ' (Every 5 Hours / 7 AM).';
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
        $isJson = (
            (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
            (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) ||
            !empty($_POST['ajax'])
        );

        if (!AuthService::isAdmin()) {
            if ($isJson) {
                http_response_code(403);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Access restricted: Administrator access required.']);
                exit;
            }
            $_SESSION['flash_error'] = 'Access restricted: Administrator access required to trigger queue worker.';
            header('Location: /admin/rtps-queue');
            exit;
        }

        // Validate CSRF if provided
        if (!empty($_POST['csrf_token']) && !AuthService::validateCsrfToken($_POST['csrf_token'])) {
            if ($isJson) {
                http_response_code(400);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Invalid security token (CSRF).']);
                exit;
            }
            $_SESSION['flash_error'] = 'Invalid security token (CSRF). Please try again.';
            header('Location: /admin/rtps-queue');
            exit;
        }

        $force = !isset($_POST['force']) || !empty($_POST['force']);
        $limit = isset($_POST['limit']) ? max(1, min(50, (int)$_POST['limit'])) : 25;

        try {
            $res = RtpsCertificateService::processScheduledQueue($limit, $force);

            if ($isJson) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'total_eligible' => $res['total_eligible'],
                    'processed' => $res['processed'],
                    'ready' => $res['ready'],
                    'unavailable' => $res['unavailable'],
                    'errors' => $res['errors'],
                    'message' => "Auto Check completed: {$res['processed']} applications checked, {$res['ready']} certificates downloaded & attached!"
                ]);
                exit;
            }

            $_SESSION['flash_success'] = "Queue worker executed: {$res['processed']} eligible applications checked. {$res['ready']} certificates attached. Next cycle runs every 5 hours (and daily 7:00 AM).";
        } catch (\Throwable $e) {
            if ($isJson) {
                http_response_code(500);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Queue trigger error: ' . $e->getMessage()]);
                exit;
            }
            $_SESSION['flash_error'] = 'Queue trigger error: ' . $e->getMessage();
        }

        $redirect = !empty($_POST['redirect_to']) ? $_POST['redirect_to'] : '/admin/rtps-queue';
        header('Location: ' . $redirect);
        exit;
    }

    /**
     * One-Click Auto Download and Attach Certificate from Bihar RTPS Portal
     * POST /admin/rtps-queue/auto-fetch
     */
    public function autoFetchRtpsCertificate(): void
    {
        if (!AuthService::isStaffOrAdmin()) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Unauthorized: Staff or Admin privilege required.']);
            exit;
        }

        $appId = (int)($_POST['application_id'] ?? $_POST['app_id'] ?? 0);
        if ($appId <= 0) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid application ID.']);
            exit;
        }

        try {
            $res = RtpsCertificateService::checkAndProcessApplication($appId);

            header('Content-Type: application/json');
            if (!empty($res['success'])) {
                echo json_encode([
                    'success' => true,
                    'app_id' => $appId,
                    'status' => 'COMPLETED',
                    'result_file' => $res['result_file'] ?? '',
                    'message' => 'Official certificate PDF downloaded from Bihar RTPS and attached successfully!'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'app_id' => $appId,
                    'status' => $res['status'] ?? 'UNAVAILABLE',
                    'message' => $res['message'] ?? 'Certificate not yet generated or ready on Bihar RTPS portal. Next check scheduled in 5 hours.'
                ]);
            }
        } catch (\Throwable $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Error checking RTPS portal: ' . $e->getMessage()
            ]);
        }
        exit;
    }
}
