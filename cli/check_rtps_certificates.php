<?php
/**
 * Welcome Bihar - RTPS Certificate Check & Queue Worker
 * Runs every 5 hours and daily at 7:00 AM Asia/Kolkata timezone
 *
 * Usage:
 *   php cli/check_rtps_certificates.php [--limit=50] [--force]
 *   php cli/check_rtps_certificates.php --daemon [--interval=300]
 *   php cli/check_rtps_certificates.php --set-7am
 */

declare(strict_types=1);

date_default_timezone_set('Asia/Kolkata');

spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $baseDir = dirname(__DIR__) . '/app/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    if (file_exists($file)) require $file;
});

require_once dirname(__DIR__) . '/app/helpers.php';
\App\Config\Config::init();
\App\Services\SystemUpdateService::ensureSchema();

use App\Services\RtpsCertificateService;

$options = getopt('', ['limit:', 'force', 'daemon', 'interval:', 'set-7am', 'app-id:']);
$appIdParam = isset($options['app-id']) ? (int)$options['app-id'] : 0;
if ($appIdParam > 0) {
    echo "[" . date('Y-m-d H:i:s') . "] Background worker triggered for Single App ID: {$appIdParam}\n";
    try {
        $res = RtpsCertificateService::checkAndProcessApplication($appIdParam);
        echo json_encode($res, JSON_PRETTY_PRINT) . "\n";
    } catch (\Throwable $e) {
        echo "[-] Error in single application check: " . $e->getMessage() . "\n";
    }
    exit(0);
}
$limit = isset($options['limit']) ? max(1, (int)$options['limit']) : 50;
$force = isset($options['force']);
$daemon = isset($options['daemon']);
$sleepSeconds = isset($options['interval']) ? max(10, (int)$options['interval']) : 300;

// Test utility option: Set pending items' next_check_at to 7:00 AM today
if (isset($options['set-7am'])) {
    $db = \App\Config\Database::getConnection();
    $targetTime = date('Y-m-d 07:00:00');
    $stmt = $db->prepare("
        UPDATE rtps_certificate_queue
        SET next_check_at = ?,
            last_status_message = 'Scheduled check set to 7:00 AM Asia/Kolkata (Every 5 Hours cycle)'
        WHERE status != 'ATTACHED'
    ");
    $stmt->execute([$targetTime]);
    $affected = $stmt->rowCount();
    echo "[+] Updated {$affected} pending queue items to 7:00 AM ({$targetTime}). They are now DUE NOW for checking.\n";
    exit(0);
}

// Single-instance lock
$lockDir = dirname(__DIR__) . '/storage/locks';
if (!is_dir($lockDir)) {
    @mkdir($lockDir, 0755, true);
}
$lockFile = $lockDir . '/rtps_queue.lock';
$lockFp = fopen($lockFile, 'c+');

if (!$lockFp || !flock($lockFp, LOCK_EX | LOCK_NB)) {
    echo "[" . date('Y-m-d H:i:s') . "] Another RTPS check worker instance is currently running. Skipping execution.\n";
    exit(0);
}

echo "========================================================\n";
echo "  WELCOME BIHAR - RTPS CERTIFICATE 5-HOUR SCHEDULE WORKER\n";
echo "  Timezone: Asia/Kolkata | Current Time: " . date('Y-m-d H:i:s') . "\n";
echo "  Cycle: Every 5 Hours & 7:00 AM Morning Check\n";
echo "========================================================\n";

do {
    try {
        $res = RtpsCertificateService::processScheduledQueue($limit, $force);

        echo "[" . date('Y-m-d H:i:s') . "] RTPS Check Run Completed:\n";
        echo "    - Total Eligible Applications : " . $res['total_eligible'] . "\n";
        echo "    - Processed in this run       : " . $res['processed'] . "\n";
        echo "    - Attached & Completed        : " . $res['ready'] . "\n";
        echo "    - Unavailable (In 5 Hours)    : " . $res['unavailable'] . "\n";
        echo "    - Errors (Retrying)           : " . $res['errors'] . "\n";

        $stats = RtpsCertificateService::getQueueStats();
        echo "\n[*] Overall RTPS Queue Status:\n";
        echo "    - Total Queued Applications   : " . $stats['total_queued'] . "\n";
        echo "    - Pending Check               : " . $stats['pending_check'] . "\n";
        echo "    - Due Right Now               : " . $stats['due_now'] . "\n";
        echo "    - Unavailable (Next 5-Hr Run) : " . $stats['unavailable'] . "\n";
        echo "    - Attached & Completed        : " . $stats['attached_completed'] . "\n";

    } catch (\Throwable $e) {
        echo "[-] Error during RTPS certificate check run: " . $e->getMessage() . "\n";
    }

    if ($daemon) {
        echo "\n[i] Daemon active: Sleeping {$sleepSeconds}s before next check cycle...\n";
        sleep($sleepSeconds);
        // After first run, don't force all
        $force = false;
    }
} while ($daemon);

if ($lockFp) {
    flock($lockFp, LOCK_UN);
    fclose($lockFp);
}
