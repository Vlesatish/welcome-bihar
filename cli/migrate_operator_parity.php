<?php
/**
 * Safe, Idempotent Database Migration for Operator System & Parity
 * Welcome Bihar
 */

spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $baseDir = dirname(__DIR__) . '/app/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    if (file_exists($file)) require $file;
});

use App\Config\Database;

echo "=== Running Operator Parity Migration ===\n";

$db = Database::getConnection();

function columnExists(PDO $db, string $table, string $column): bool {
    $cols = $db->query("SHOW COLUMNS FROM `{$table}`")->fetchAll(PDO::FETCH_COLUMN);
    return in_array($column, $cols, true);
}

function indexExists(PDO $db, string $table, string $indexName): bool {
    $indices = $db->query("SHOW INDEX FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($indices as $idx) {
        if ($idx['Key_name'] === $indexName) return true;
    }
    return false;
}

// 1. Check & Add user columns if missing
if (!columnExists($db, 'users', 'assigned_district_id')) {
    $db->exec("ALTER TABLE `users` ADD COLUMN `assigned_district_id` INT NULL AFTER `assigned_all_districts`");
    echo "  [OK] Added users.assigned_district_id\n";
} else {
    echo "  [EXISTS] users.assigned_district_id\n";
}

if (!columnExists($db, 'users', 'assigned_block_id')) {
    $db->exec("ALTER TABLE `users` ADD COLUMN `assigned_block_id` INT NULL AFTER `assigned_district_id`");
    echo "  [OK] Added users.assigned_block_id\n";
} else {
    echo "  [EXISTS] users.assigned_block_id\n";
}

// 2. Indexes for fast location scoping
if (!indexExists($db, 'users', 'idx_users_assigned_loc')) {
    $db->exec("ALTER TABLE `users` ADD INDEX `idx_users_assigned_loc` (`assigned_district_id`, `assigned_block_id`)");
    echo "  [OK] Added index idx_users_assigned_loc\n";
} else {
    echo "  [EXISTS] index idx_users_assigned_loc\n";
}

if (!indexExists($db, 'applications', 'idx_applications_district_block_status')) {
    $db->exec("ALTER TABLE `applications` ADD INDEX `idx_applications_district_block_status` (`district_id`, `block_id`, `status`)");
    echo "  [OK] Added index idx_applications_district_block_status\n";
} else {
    echo "  [EXISTS] index idx_applications_district_block_status\n";
}

if (!indexExists($db, 'topup_orders', 'idx_topup_orders_user_status')) {
    $db->exec("ALTER TABLE `topup_orders` ADD INDEX `idx_topup_orders_user_status` (`user_id`, `status`)");
    echo "  [OK] Added index idx_topup_orders_user_status\n";
} else {
    echo "  [EXISTS] index idx_topup_orders_user_status\n";
}

if (!indexExists($db, 'topup_orders', 'idx_topup_provider_txn')) {
    $db->exec("ALTER TABLE `topup_orders` ADD INDEX `idx_topup_provider_txn` (`provider_txn_id`)");
    echo "  [OK] Added index idx_topup_provider_txn\n";
} else {
    echo "  [EXISTS] index idx_topup_provider_txn\n";
}

// 3. Ensure essential system settings
$defaultSettings = [
    ['gateway_auto_upi', '1', 'PAYMENT', 'Automatic UPI QR Gateway Enabled'],
    ['gateway_merchant_name', 'Welcome Bihar', 'PAYMENT', 'Merchant Display Name for UPI'],
    ['gateway_upi_id', 'welcomebihar@upi', 'PAYMENT', 'Merchant UPI ID'],
    ['allapi_base_url', 'https://allapi.in', 'PAYMENT', 'ALL API Base URL'],
    ['allapi_create_endpoint', '/order/create', 'PAYMENT', 'ALL API Order Creation Endpoint'],
    ['allapi_status_endpoint', '/order/status', 'PAYMENT', 'ALL API Order Status Endpoint'],
    ['allapi_redirect_url', '/wallet/passbook', 'PAYMENT', 'ALL API Return / Redirect URL'],
    ['distributor_review_required', '1', 'WORKFLOW', 'Distributor review step requirement'],
    ['refund_on_rejection', '1', 'WORKFLOW', 'Automatic refund to user wallet on rejection'],
    ['fallback_rate_standard', '100.00', 'GENERAL', 'Fallback price for BICCO/BCCCO/BRCCO'],
    ['fallback_rate_other', '130.00', 'GENERAL', 'Fallback price for BOBC/NCLCO/OTHER']
];

$setStmt = $db->prepare("
    INSERT INTO `settings` (`setting_key`, `setting_value`, `setting_group`, `description`, `updated_at`)
    VALUES (?, ?, ?, ?, NOW())
    ON DUPLICATE KEY UPDATE
        setting_group = VALUES(setting_group),
        description = VALUES(description)
");

foreach ($defaultSettings as $ds) {
    // Only insert if key does not exist yet to avoid overwriting admin configured values
    $check = $db->prepare("SELECT COUNT(*) FROM `settings` WHERE `setting_key` = ?");
    $check->execute([$ds[0]]);
    if ((int)$check->fetchColumn() === 0) {
        $setStmt->execute([$ds[0], $ds[1], $ds[2], $ds[3]]);
        echo "  [INSERTED] Setting {$ds[0]} = {$ds[1]}\n";
    } else {
        echo "  [EXISTS] Setting {$ds[0]}\n";
    }
}

// 4. Ensure upload directories exist and are protected against execution
$uploadDirs = [
    dirname(__DIR__) . '/public/uploads',
    dirname(__DIR__) . '/public/uploads/documents',
    dirname(__DIR__) . '/public/uploads/results'
];

foreach ($uploadDirs as $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
        echo "  [CREATED] Directory {$dir}\n";
    }
}

$htaccessFile = dirname(__DIR__) . '/public/uploads/.htaccess';
if (!file_exists($htaccessFile)) {
    $htaccessContent = "# Prevent script execution in upload directory\n" .
        "<FilesMatch \"\.(php|phtml|php3|php4|php5|php7|php8|phps|pl|py|cgi|sh|bash|exe)$\">\n" .
        "    Order Deny,Allow\n" .
        "    Deny from all\n" .
        "</FilesMatch>\n" .
        "Options -ExecCGI\n";
    file_put_contents($htaccessFile, $htaccessContent);
    echo "  [CREATED] Uploads security .htaccess\n";
} else {
    echo "  [EXISTS] Uploads security .htaccess\n";
}

echo "=== Migration Complete: All checks passed. ===\n";
