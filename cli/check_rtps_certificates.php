<?php
/**
 * Welcome Bihar - RTPS Certificate Check & Queue Worker
 * Runs every day at 12:00 AM (midnight) Asia/Kolkata timezone
 *
 * Usage:
 *   php cli/check_rtps_certificates.php [--limit=50] [--force]
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

$options = getopt('', ['limit:', 'force']);
$limit = isset($options['limit']) ? max(1, (int)$options['limit']) : 50;
$force = isset($options['force']);

echo "========================================================\n";
echo "  WELCOME BIHAR - RTPS CERTIFICATE SCHEDULED CHECKER   \n";
echo "  Timezone: Asia/Kolkata | Current Time: " . date('Y-m-d H:i:s') . "\n";
echo "========================================================\n";

try {
    $res = RtpsCertificateService::processScheduledQueue($limit, $force);

    echo "[+] RTPS Check Run Completed:\n";
    echo "    - Total Eligible Applications : " . $res['total_eligible'] . "\n";
    echo "    - Processed in this run       : " . $res['processed'] . "\n";
    echo "    - Ready for Download / Sync   : " . $res['ready'] . "\n";
    echo "    - Unavailable (Rescheduled)   : " . $res['unavailable'] . "\n";
    echo "    - Errors (Retrying later)     : " . $res['errors'] . "\n";

    $stats = RtpsCertificateService::getQueueStats();
    echo "\n[*] Overall RTPS Queue Status:\n";
    echo "    - Total Queued Applications   : " . $stats['total_queued'] . "\n";
    echo "    - Pending / Scheduled Check   : " . $stats['pending_check'] . "\n";
    echo "    - Unavailable (Next 12:00 AM) : " . $stats['unavailable'] . "\n";
    echo "    - Attached & Completed        : " . $stats['attached_completed'] . "\n";

} catch (\Throwable $e) {
    echo "[-] Error during RTPS certificate check run: " . $e->getMessage() . "\n";
} finally {
    if ($lockFp) {
        flock($lockFp, LOCK_UN);
        fclose($lockFp);
    }
}
