<?php
/**
 * Migration: Dual Role System and User Role Assignments
 * Idempotent migration for Welcome Bihar portal.
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

use App\Config\Database;
use App\Config\Config;

Config::init();
$db = Database::getConnection();

echo "=== Welcome Bihar: Dual Role Migration ===\n";

// 1. Create user_role_assignments table
$db->exec("
    CREATE TABLE IF NOT EXISTS `user_role_assignments` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `role_code` ENUM('USER', 'OPERATOR') NOT NULL,
        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
        `granted_by` INT NULL,
        `granted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `revoked_by` INT NULL,
        `revoked_at` DATETIME NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY `uk_user_role` (`user_id`, `role_code`),
        INDEX `idx_role_active` (`role_code`, `is_active`),
        INDEX `idx_ura_user_id` (`user_id`),
        CONSTRAINT `fk_ura_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
        CONSTRAINT `fk_ura_granted_by` FOREIGN KEY (`granted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
        CONSTRAINT `fk_ura_revoked_by` FOREIGN KEY (`revoked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");
echo "1. Checked/Created table `user_role_assignments`.\n";

// 2. Backfill existing USER accounts (idempotent, does not overwrite revoked status if already present)
$stmtUser = $db->exec("
    INSERT INTO `user_role_assignments` (`user_id`, `role_code`, `is_active`, `granted_by`, `granted_at`, `created_at`, `updated_at`)
    SELECT u.id, 'USER', 1, NULL, NOW(), NOW(), NOW()
    FROM `users` u
    WHERE u.role IN ('USER', 'DISTRIBUTOR')
    ON DUPLICATE KEY UPDATE `is_active` = `user_role_assignments`.`is_active`;
");
echo "2. Backfilled USER capability for existing users.\n";

// 3. Backfill existing OPERATOR accounts (idempotent, preserves revoked status on re-run)
$stmtOp = $db->exec("
    INSERT INTO `user_role_assignments` (`user_id`, `role_code`, `is_active`, `granted_by`, `granted_at`, `created_at`, `updated_at`)
    SELECT u.id, 'OPERATOR', IF(u.status = 'ACTIVE', 1, 0), NULL, NOW(), NOW(), NOW()
    FROM `users` u
    WHERE u.role = 'OPERATOR'
    ON DUPLICATE KEY UPDATE `is_active` = `user_role_assignments`.`is_active`;
");
echo "3. Backfilled OPERATOR capability for existing operators.\n";

$count = $db->query("SELECT COUNT(*) FROM `user_role_assignments`")->fetchColumn();
echo "4. Total active role assignments in table: {$count}\n";
echo "=== Migration Complete ===\n";
