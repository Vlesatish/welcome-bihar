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
     * Trigger Batch Queue Check (Check eligible applications)
     * POST /admin/applications/trigger-rtps OR /admin/rtps-queue/trigger
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
            header('Location: /admin/applications');
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
            header('Location: /admin/applications');
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

        $redirect = !empty($_POST['redirect_to']) ? $_POST['redirect_to'] : '/admin/applications';
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

        // Release session lock immediately to prevent blocking other page requests
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $appId = (int)($_POST['application_id'] ?? $_POST['app_id'] ?? 0);
        if ($appId <= 0) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid application ID.']);
            exit;
        }

        try {
            $db = Database::getConnection();
            $rootPath = dirname(__DIR__, 3);

            // 1. Fetch and validate application record
            $appStmt = $db->prepare("
                SELECT id, status, result_file, application_code, applicant_identifier, applicant_name, district_id, block_id
                FROM applications
                WHERE id = ?
                LIMIT 1
            ");
            $appStmt->execute([$appId]);
            $app = $appStmt->fetch(PDO::FETCH_ASSOC);

            if (!$app) {
                http_response_code(404);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Application record not found.']);
                exit;
            }

            // 2. Prevent duplicate execution if certificate is already attached & verified on disk
            if ($app['status'] === 'COMPLETED' || $app['status'] === 'APPROVED' || !empty($app['result_file'])) {
                $resFile = $app['result_file'] ?? '';
                $isVerified = false;
                if (!empty($resFile)) {
                    $p1 = $rootPath . '/public' . (str_starts_with($resFile, '/') ? '' : '/') . $resFile;
                    $p2 = $rootPath . '/storage' . (str_starts_with($resFile, '/') ? '' : '/') . $resFile;
                    $isVerified = (file_exists($p1) && filesize($p1) >= 64) || (file_exists($p2) && filesize($p2) >= 64);
                }
                if ($isVerified) {
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => true,
                        'already_completed' => true,
                        'app_id' => $appId,
                        'status' => $app['status'],
                        'result_file' => $resFile,
                        'message' => 'Certificate is already attached and verified for this application.'
                    ]);
                    exit;
                }
            }

            // 3. Prevent duplicate requests from parallel processing if already checking recently
            $chkQ = $db->prepare("SELECT id, status, last_checked_at FROM rtps_certificate_queue WHERE application_id = ?");
            $chkQ->execute([$appId]);
            $existingQ = $chkQ->fetch(PDO::FETCH_ASSOC);

            if ($existingQ) {
                if ($existingQ['status'] === 'ATTACHED') {
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => true,
                        'already_completed' => true,
                        'app_id' => $appId,
                        'status' => 'ATTACHED',
                        'message' => 'Certificate already attached in queue.'
                    ]);
                    exit;
                }

                if ($existingQ['status'] === 'CHECKING') {
                    $lastTs = $existingQ['last_checked_at'] ? strtotime($existingQ['last_checked_at']) : 0;
                    if (time() - $lastTs < 60) {
                        header('Content-Type: application/json');
                        echo json_encode([
                            'success' => true,
                            'in_background' => true,
                            'already_checking' => true,
                            'app_id' => $appId,
                            'status' => 'CHECKING',
                            'message' => 'RTPS Portal verification already in progress for this application.'
                        ]);
                        exit;
                    }
                }
            }

            // 4. Reuse existing queue service method to ensure all required fields are populated
            // (application_code, application_number, applicant_name, district_id, block_id, service_type)
            RtpsCertificateService::enqueueApplication($appId);

            // 5. Update queue status to CHECKING immediately (protecting ATTACHED status)
            $db->prepare("
                UPDATE rtps_certificate_queue
                SET status = 'CHECKING',
                    last_checked_at = NOW(),
                    last_status_message = 'Background verification initiated',
                    updated_at = NOW()
                WHERE application_id = ? AND status != 'ATTACHED'
            ")->execute([$appId]);

            // 6. Build correct background worker path (root/cli/check_rtps_certificates.php)
            $cliScript = $rootPath . DIRECTORY_SEPARATOR . 'cli' . DIRECTORY_SEPARATOR . 'check_rtps_certificates.php';
            if (!file_exists($cliScript)) {
                throw new \Exception("Background worker script not found at {$cliScript}");
            }

            // 7. Platform-compatible worker execution (Windows / Linux)
            $phpBin = PHP_BINARY;
            if (empty($phpBin) || !file_exists($phpBin)) {
                if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                    $defaultWinPhp = 'C:\\xampp\\php\\php.exe';
                    $phpBin = file_exists($defaultWinPhp) ? $defaultWinPhp : 'php';
                } else {
                    $phpBin = 'php';
                }
            }

            $launched = false;
            $cmd = '';
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                $cmd = sprintf('start /B "" %s %s --app-id=%d > NUL 2>&1', escapeshellarg($phpBin), escapeshellarg($cliScript), $appId);
                $p = popen($cmd, 'r');
                if ($p !== false) {
                    pclose($p);
                    $launched = true;
                }
            } else {
                $cmd = sprintf('nohup %s %s --app-id=%d > /dev/null 2>&1 &', escapeshellarg($phpBin), escapeshellarg($cliScript), $appId);
                exec($cmd, $out, $ret);
                $launched = ($ret === 0);
            }

            // 8. If worker launch failed, do not return success: log and set recoverable queue status
            if (!$launched) {
                $db->prepare("
                    UPDATE rtps_certificate_queue
                    SET status = 'QUEUED',
                        last_status_message = 'Worker process launch failed; scheduled cycle will retry',
                        updated_at = NOW()
                    WHERE application_id = ? AND status != 'ATTACHED'
                ")->execute([$appId]);

                error_log("Failed to launch RTPS background worker for App ID {$appId} (Command: {$cmd})");
                http_response_code(500);
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'message' => 'Background worker process could not be launched. Queue status has been safely preserved for automatic retry.'
                ]);
                exit;
            }

            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'in_background' => true,
                'app_id' => $appId,
                'status' => 'CHECKING',
                'message' => 'RTPS Portal verification background me chal raha hai. Aap dusre pages par ja sakte hain, page bilkul nahi rukega.'
            ]);
            exit;
        } catch (\Throwable $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Error initiating background check: ' . $e->getMessage()
            ]);
            exit;
        }
    }

    /**
     * Fast non-blocking status poller for applications
     * GET /admin/applications/poll-status?id=123
     */
    public function pollApplicationStatus(): void
    {
        // Release session lock immediately so polling never blocks page navigation or clicks
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        header('Content-Type: application/json');

        // Enforce authentication
        if (!AuthService::check()) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Unauthenticated']);
            exit;
        }

        $appId = (int)($_GET['id'] ?? $_GET['application_id'] ?? 0);
        if ($appId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid application identifier']);
            exit;
        }

        $db = Database::getConnection();
        $user = AuthService::user();
        $role = $user['role'] ?? 'USER';
        $userId = (int)($user['id'] ?? 0);

        // Compatible query across all repository schemas (does not query nonexistent portal_status column)
        $stmt = $db->prepare("
            SELECT a.id, a.user_id, a.distributor_id, a.status, a.result_file, a.remarks, a.admin_remarks,
                   q.status as queue_status, q.last_status_message, q.certificate_file
            FROM applications a
            LEFT JOIN rtps_certificate_queue q ON a.id = q.application_id
            WHERE a.id = ?
            LIMIT 1
        ");
        $stmt->execute([$appId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Application not found']);
            exit;
        }

        // Enforce application-access permissions: Staff/Admin or owner (User/Distributor)
        if (!AuthService::isStaffOrAdmin()) {
            $appUserId = (int)$row['user_id'];
            $appDistId = (int)($row['distributor_id'] ?? 0);
            if ($role === 'DISTRIBUTOR' && $appDistId !== $userId) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Unauthorized access to application']);
                exit;
            } elseif ($role !== 'DISTRIBUTOR' && $appUserId !== $userId) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Unauthorized access to application']);
                exit;
            }
        }

        // Verify result / certificate file actually exists and is genuine on disk (>= 64 bytes)
        $resultFile = !empty($row['result_file']) ? $row['result_file'] : ($row['certificate_file'] ?? '');
        $isVerifiedFile = false;
        if (!empty($resultFile)) {
            $root = dirname(__DIR__, 3);
            $p1 = $root . '/public' . (str_starts_with($resultFile, '/') ? '' : '/') . $resultFile;
            $p2 = $root . '/storage' . (str_starts_with($resultFile, '/') ? '' : '/') . $resultFile;
            if ((file_exists($p1) && filesize($p1) >= 64) || (file_exists($p2) && filesize($p2) >= 64)) {
                $isVerifiedFile = true;
            }
        }

        // Success is ONLY shown when status is COMPLETED/APPROVED AND verified certificate file exists on disk
        $isCompleted = (($row['status'] === 'COMPLETED' || $row['status'] === 'APPROVED') && $isVerifiedFile);
        $isChecking = (($row['queue_status'] ?? '') === 'CHECKING');

        // Resolve portal status safely without assuming portal_status column exists in DB
        $portalStatus = $row['admin_remarks'] ?: ($row['remarks'] ?: ($row['last_status_message'] ?? ''));

        echo json_encode([
            'success' => true,
            'app_id' => $appId,
            'status' => $row['status'],
            'is_completed' => $isCompleted,
            'is_checking' => $isChecking,
            'queue_status' => $row['queue_status'] ?? '',
            'portal_status' => $portalStatus,
            'last_check_status' => $row['last_status_message'] ?? '',
            'result_file' => $isVerifiedFile ? $resultFile : '',
            'message' => $isCompleted ? 'Certificate attached successfully!' : ($row['last_status_message'] ?: 'Processing in background...')
        ]);
        exit;
    }
}
