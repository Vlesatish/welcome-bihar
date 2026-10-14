<?php
/**
 * CLI/Cron Dispatcher for WhatsApp Notification Outbox
 * Usage: php cli/dispatch_whatsapp.php [--limit=50]
 */

declare(strict_types=1);

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

use App\Services\WhatsAppNotificationService;

// Single-instance process lock
$lockDir = dirname(__DIR__) . '/storage/locks';
if (!is_dir($lockDir)) {
    @mkdir($lockDir, 0755, true);
}
$lockFile = $lockDir . '/whatsapp_dispatcher.lock';
$lockFp = fopen($lockFile, 'c+');

if (!$lockFp || !flock($lockFp, LOCK_EX | LOCK_NB)) {
    echo "[" . date('Y-m-d H:i:s') . "] Another WhatsApp dispatcher instance is currently running. Skipping execution.\n";
    exit(0);
}

$options = getopt('', ['limit:']);
$limit = isset($options['limit']) ? max(1, (int)$options['limit']) : 50;

echo "[" . date('Y-m-d H:i:s') . "] Starting WhatsApp Outbox Dispatcher (limit: {$limit})...\n";

try {
    $summary = WhatsAppNotificationService::dispatchPending($limit);
    if (isset($summary['status']) && $summary['status'] === 'disabled') {
        echo "[*] WhatsApp sending is currently disabled in system settings.\n";
    } elseif (isset($summary['status']) && $summary['status'] === 'blocked') {
        echo "[!] WhatsApp sending is currently blocked: " . ($summary['message'] ?? 'Authentication failure') . "\n";
    } else {
        echo "[+] Completed dispatch run:\n";
        echo "    - Total processed : " . ($summary['processed'] ?? 0) . "\n";
        echo "    - Sent successful : " . ($summary['sent'] ?? 0) . "\n";
        echo "    - Retrying later  : " . ($summary['retrying'] ?? 0) . "\n";
        echo "    - Hard failed     : " . ($summary['failed'] ?? 0) . "\n";
    }
} catch (\Throwable $e) {
    echo "[-] Error during WhatsApp dispatch: " . $e->getMessage() . "\n";
} finally {
    if ($lockFp) {
        flock($lockFp, LOCK_UN);
        fclose($lockFp);
    }
}

exit(0);
