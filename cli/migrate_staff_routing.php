<?php
/**
 * Migration: Application Code Based Staff Routing System
 * Idempotent migration for Welcome Bihar portal.
 */

declare(strict_types=1);

require_once __DIR__ . '/../app/Config/Config.php';
require_once __DIR__ . '/../app/Config/Database.php';

$db = \App\Config\Database::getConnection();

echo "=== Running Migration: Staff Routing System ===\n";

// Helper to check if table exists
function tableExists(PDO $db, string $table): bool {
    $q = $db->query("SHOW TABLES LIKE " . $db->quote($table));
    return (bool)$q->fetch();
}

// Helper to check column exists
function columnExists(PDO $db, string $table, string $column): bool {
    $q = $db->query("SHOW COLUMNS FROM `{$table}` LIKE " . $db->quote($column));
    return (bool)$q->fetch();
}

// Helper to check index exists
function indexExists(PDO $db, string $table, string $indexName): bool {
    $q = $db->query("SHOW INDEX FROM `{$table}` WHERE Key_name = " . $db->quote($indexName));
    return (bool)$q->fetch();
}

// 1. Create operator_application_code_assignments table
if (!tableExists($db, 'operator_application_code_assignments')) {
    $sql = "CREATE TABLE `operator_application_code_assignments` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `operator_user_id` INT NOT NULL,
        `district_id` INT NOT NULL,
        `block_id` INT NOT NULL,
        `application_code` VARCHAR(64) NOT NULL,
        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
        `priority` INT NOT NULL DEFAULT 1,
        `assigned_by` INT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT `fk_oaca_operator` FOREIGN KEY (`operator_user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
        CONSTRAINT `fk_oaca_district` FOREIGN KEY (`district_id`) REFERENCES `districts`(`id`) ON DELETE CASCADE,
        CONSTRAINT `fk_oaca_block` FOREIGN KEY (`block_id`) REFERENCES `blocks`(`id`) ON DELETE CASCADE,
        UNIQUE KEY `uq_op_dist_blk_code` (`operator_user_id`, `district_id`, `block_id`, `application_code`),
        INDEX `idx_routing_lookup` (`district_id`, `block_id`, `application_code`, `is_active`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    $db->exec($sql);
    echo "  [OK] Created table: operator_application_code_assignments\n";
} else {
    echo "  [EXISTS] Table: operator_application_code_assignments\n";
}

// 2. Add columns to applications table
if (!columnExists($db, 'applications', 'application_code')) {
    $db->exec("ALTER TABLE `applications` ADD COLUMN `application_code` VARCHAR(64) NULL AFTER `data_type_id`");
    echo "  [OK] Added column: applications.application_code\n";
} else {
    echo "  [EXISTS] Column: applications.application_code\n";
}

if (!columnExists($db, 'applications', 'assigned_operator_id')) {
    $db->exec("ALTER TABLE `applications` ADD COLUMN `assigned_operator_id` INT NULL AFTER `distributor_id`");
    echo "  [OK] Added column: applications.assigned_operator_id\n";
} else {
    echo "  [EXISTS] Column: applications.assigned_operator_id\n";
}

if (!columnExists($db, 'applications', 'assigned_at')) {
    $db->exec("ALTER TABLE `applications` ADD COLUMN `assigned_at` DATETIME NULL AFTER `assigned_operator_id`");
    echo "  [OK] Added column: applications.assigned_at\n";
} else {
    echo "  [EXISTS] Column: applications.assigned_at\n";
}

if (!columnExists($db, 'applications', 'assignment_method')) {
    $db->exec("ALTER TABLE `applications` ADD COLUMN `assignment_method` VARCHAR(64) NULL AFTER `assigned_at`");
    echo "  [OK] Added column: applications.assignment_method\n";
} else {
    echo "  [EXISTS] Column: applications.assignment_method\n";
}

// Indexes on applications table
if (!indexExists($db, 'applications', 'idx_app_code')) {
    $db->exec("ALTER TABLE `applications` ADD INDEX `idx_app_code` (`application_code`)");
    echo "  [OK] Added index: idx_app_code\n";
}
if (!indexExists($db, 'applications', 'idx_assigned_operator')) {
    $db->exec("ALTER TABLE `applications` ADD INDEX `idx_assigned_operator` (`assigned_operator_id`)");
    echo "  [OK] Added index: idx_assigned_operator\n";
}
if (!indexExists($db, 'applications', 'idx_dist_blk_app_code')) {
    $db->exec("ALTER TABLE `applications` ADD INDEX `idx_dist_blk_app_code` (`district_id`, `block_id`, `application_code`)");
    echo "  [OK] Added index: idx_dist_blk_app_code\n";
}
if (!indexExists($db, 'applications', 'idx_status_assigned_op')) {
    $db->exec("ALTER TABLE `applications` ADD INDEX `idx_status_assigned_op` (`status`, `assigned_operator_id`)");
    echo "  [OK] Added index: idx_status_assigned_op\n";
}

// 3. Create application_assignment_history table
if (!tableExists($db, 'application_assignment_history')) {
    $sql = "CREATE TABLE `application_assignment_history` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `application_id` INT NOT NULL,
        `previous_operator_id` INT NULL,
        `new_operator_id` INT NULL,
        `application_code` VARCHAR(64) NOT NULL,
        `assigned_by` INT NULL,
        `assignment_type` ENUM('AUTO', 'MANUAL', 'REASSIGN') NOT NULL DEFAULT 'AUTO',
        `reason` TEXT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT `fk_aah_app` FOREIGN KEY (`application_id`) REFERENCES `applications`(`id`) ON DELETE CASCADE,
        INDEX `idx_aah_app_id` (`application_id`),
        INDEX `idx_aah_new_op` (`new_operator_id`),
        INDEX `idx_aah_prev_op` (`previous_operator_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    $db->exec($sql);
    echo "  [OK] Created table: application_assignment_history\n";
} else {
    echo "  [EXISTS] Table: application_assignment_history\n";
}

// 4. Default Admin setting: allow_submission_when_no_staff_assigned = 1
$setChk = $db->query("SELECT COUNT(*) FROM settings WHERE setting_key = 'allow_submission_when_no_staff_assigned'")->fetchColumn();
if ((int)$setChk === 0) {
    $stmt = $db->prepare("
        INSERT INTO settings (setting_key, setting_value, setting_group, description, updated_at)
        VALUES ('allow_submission_when_no_staff_assigned', '1', 'ROUTING', 'Allow retailer to submit application when no staff is routed yet', NOW())
    ");
    $stmt->execute();
    echo "  [OK] Added setting: allow_submission_when_no_staff_assigned = 1\n";
} else {
    echo "  [EXISTS] Setting: allow_submission_when_no_staff_assigned\n";
}

// 5. Backfill application_code on applications table from application_field_values
$backfillCount = 0;
$appsWithoutCode = $db->query("SELECT id FROM applications WHERE application_code IS NULL OR application_code = ''")->fetchAll(PDO::FETCH_COLUMN);

if (!empty($appsWithoutCode)) {
    $bfStmt = $db->prepare("
        SELECT field_value FROM application_field_values
        WHERE application_id = ? AND field_name IN ('application_code', 'app_code') AND field_value != ''
        LIMIT 1
    ");
    $updStmt = $db->prepare("UPDATE applications SET application_code = ? WHERE id = ?");

    foreach ($appsWithoutCode as $aId) {
        $bfStmt->execute([$aId]);
        $val = trim((string)$bfStmt->fetchColumn());
        if ($val !== '') {
            $updStmt->execute([strtoupper($val), $aId]);
            $backfillCount++;
        }
    }
}
echo "  [OK] Backfilled application_code for {$backfillCount} existing applications.\n";

echo "=== Migration Complete ===\n";
