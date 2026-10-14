<?php
/**
 * Safe Migration for eCyberCafe Parity
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
require_once __DIR__ . '/../app/helpers.php';

use App\Config\Database;
$db = Database::getConnection();
echo "Applying database schema upgrades for eCyberCafe parity...\n";

// 1. Users role enum with OPERATOR
try {
    $db->exec("ALTER TABLE users MODIFY COLUMN role ENUM('ADMIN','OPERATOR','DISTRIBUTOR','USER') NOT NULL DEFAULT 'USER'");
    echo "[OK] users.role enum upgraded with OPERATOR.\n";
} catch (Exception $e) {
    echo "[SKIP] users.role: " . $e->getMessage() . "\n";
}

// 2. Applications status enum with IN_PROCESS
try {
    $db->exec("ALTER TABLE applications MODIFY COLUMN status ENUM('PENDING','IN_PROCESS','UNDER_REVIEW','APPROVED','REJECTED','COMPLETED') NOT NULL DEFAULT 'PENDING'");
    echo "[OK] applications.status enum upgraded with IN_PROCESS.\n";
} catch (Exception $e) {
    echo "[SKIP] applications.status: " . $e->getMessage() . "\n";
}

// 3. Applications extra columns
$appCols = $db->query("SHOW COLUMNS FROM applications")->fetchAll(PDO::FETCH_COLUMN);

if (!in_array('is_refunded', $appCols, true)) {
    $db->exec("ALTER TABLE applications ADD COLUMN is_refunded TINYINT(1) NOT NULL DEFAULT 0 AFTER is_locked");
    echo "[OK] Added applications.is_refunded.\n";
}
if (!in_array('is_commission_paid', $appCols, true)) {
    $db->exec("ALTER TABLE applications ADD COLUMN is_commission_paid TINYINT(1) NOT NULL DEFAULT 0 AFTER is_refunded");
    echo "[OK] Added applications.is_commission_paid.\n";
}
if (!in_array('assigned_operator_id', $appCols, true)) {
    $db->exec("ALTER TABLE applications ADD COLUMN assigned_operator_id INT(11) NULL AFTER distributor_id, ADD INDEX (assigned_operator_id)");
    echo "[OK] Added applications.assigned_operator_id.\n";
}
if (!in_array('claimed_at', $appCols, true)) {
    $db->exec("ALTER TABLE applications ADD COLUMN claimed_at DATETIME NULL AFTER assigned_operator_id");
    echo "[OK] Added applications.claimed_at.\n";
}

// 4. Data types extra columns
$dtCols = $db->query("SHOW COLUMNS FROM data_types")->fetchAll(PDO::FETCH_COLUMN);

if (!in_array('warning_image', $dtCols, true)) {
    $db->exec("ALTER TABLE data_types ADD COLUMN warning_image VARCHAR(255) NULL AFTER announcement");
    echo "[OK] Added data_types.warning_image.\n";
}
if (!in_array('enable_chat', $dtCols, true)) {
    $db->exec("ALTER TABLE data_types ADD COLUMN enable_chat TINYINT(1) NOT NULL DEFAULT 1 AFTER is_active");
    echo "[OK] Added data_types.enable_chat.\n";
}

// 5. Application Chats Table
$db->exec("
    CREATE TABLE IF NOT EXISTS application_chats (
        id INT AUTO_INCREMENT PRIMARY KEY,
        application_id INT NOT NULL,
        sender_id INT NOT NULL,
        sender_name VARCHAR(150) NOT NULL,
        sender_role ENUM('ADMIN','OPERATOR','DISTRIBUTOR','RETAILER','USER') NOT NULL,
        message TEXT NOT NULL,
        attachment_url VARCHAR(255) NULL,
        is_read TINYINT(1) NOT NULL DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_app_id (application_id),
        INDEX idx_created (created_at),
        CONSTRAINT fk_chat_app FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
echo "[OK] application_chats table ready.\n";

// 6. Clean up temporary test notice on srv_block_app
$db->exec("
    UPDATE data_types
    SET notice = 'Important Notice: Please enter accurate and complete application numbers. Incorrect details may lead to application rejection.',
        announcement = 'Block & District Application Pass Service is active 24x7 for expedited processing of citizen applications.'
    WHERE code = 'srv_block_app'
");
echo "[OK] Cleaned service notices.\n";

echo "Migration finished successfully!\n";
