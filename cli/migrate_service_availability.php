<?php
/**
 * Migration: Create service_block_availability table
 * Idempotent: safe to run multiple times without data loss.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Config/Env.php';
\App\Config\Env::load(dirname(__DIR__) . '/.env');
require_once dirname(__DIR__) . '/app/Config/Database.php';

use App\Config\Database;

try {
    $db = Database::getConnection();
    echo "[*] Connecting to database...\n";

    // 1. Create table service_block_availability if not exists
    $tableSql = "
        CREATE TABLE IF NOT EXISTS `service_block_availability` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `data_type_id` INT NOT NULL,
            `district_id` INT NOT NULL,
            `block_id` INT NOT NULL,
            `status` ENUM('INACTIVE', 'LIVE') NOT NULL DEFAULT 'INACTIVE',
            `activated_at` DATETIME NULL,
            `activated_by` INT NULL,
            `deactivated_at` DATETIME NULL,
            `deactivated_by` INT NULL,
            `internal_note` TEXT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY `uk_service_district_block` (`data_type_id`, `district_id`, `block_id`),
            INDEX `idx_sba_service_status` (`data_type_id`, `status`),
            INDEX `idx_sba_district` (`district_id`),
            INDEX `idx_sba_block` (`block_id`),
            INDEX `idx_sba_status` (`status`),
            CONSTRAINT `fk_sba_data_type` FOREIGN KEY (`data_type_id`) REFERENCES `data_types` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_sba_district` FOREIGN KEY (`district_id`) REFERENCES `districts` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_sba_block` FOREIGN KEY (`block_id`) REFERENCES `blocks` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_sba_activated_by` FOREIGN KEY (`activated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
            CONSTRAINT `fk_sba_deactivated_by` FOREIGN KEY (`deactivated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    $db->exec($tableSql);
    echo "[OK] Table 'service_block_availability' verified / created.\n";

    // 2. Fetch service ID for srv_block_app
    $dtStmt = $db->query("SELECT id FROM data_types WHERE code = 'srv_block_app' LIMIT 1");
    $serviceId = (int)($dtStmt->fetchColumn() ?: 4);

    // 3. Ensure an admin user ID is found for audit/activation attribution
    $adminStmt = $db->query("SELECT id FROM users WHERE role = 'ADMIN' ORDER BY id ASC LIMIT 1");
    $adminId = (int)($adminStmt->fetchColumn() ?: 1);

    // 4. Clean up availability records created for blocks with no configured rate
    $delStmt = $db->prepare("
        DELETE FROM service_block_availability
        WHERE (data_type_id, district_id, block_id) NOT IN (
            SELECT DISTINCT srr.data_type_id, srr.district_id, srr.block_id
            FROM service_rate_rules srr
            WHERE srr.district_id IS NOT NULL
              AND srr.block_id IS NOT NULL
              AND srr.is_active = 1
              AND srr.fee_amount > 0
              AND srr.application_code IS NOT NULL
              AND srr.application_code != ''
              AND srr.application_code != '*'
        )
    ");
    $delStmt->execute();
    $cleanedCount = $delStmt->rowCount();
    if ($cleanedCount > 0) {
        echo "[OK] Cleaned up {$cleanedCount} availability records for blocks without active rate rules.\n";
    }

    // 5. Create availability records ONLY for blocks having active block-specific rates
    // Re-running the migration must not reactivate blocks that Admin deliberately deactivated.
    $initStmt = $db->prepare("
        INSERT INTO service_block_availability
        (data_type_id, district_id, block_id, status, activated_at, activated_by, internal_note)
        SELECT DISTINCT srr.data_type_id, srr.district_id, srr.block_id, 'LIVE', NOW(), ?, 'Initial migration: rate-configured block initialized to LIVE'
        FROM service_rate_rules srr
        JOIN blocks b ON srr.block_id = b.id AND b.is_active = 1
        JOIN districts d ON srr.district_id = d.id AND d.is_active = 1
        WHERE srr.data_type_id = ?
          AND srr.district_id IS NOT NULL
          AND srr.block_id IS NOT NULL
          AND srr.is_active = 1
          AND srr.fee_amount > 0
          AND srr.application_code IS NOT NULL
          AND srr.application_code != ''
          AND srr.application_code != '*'
          AND NOT EXISTS (
              SELECT 1 FROM service_block_availability sba
              WHERE sba.data_type_id = srr.data_type_id
                AND sba.district_id = srr.district_id
                AND sba.block_id = srr.block_id
          )
    ");
    $initStmt->execute([$adminId, $serviceId]);
    $insertedCount = $initStmt->rowCount();
    echo "[OK] Initialized {$insertedCount} missing rate-configured block records to LIVE for srv_block_app.\n";

    $totalSba = $db->query("SELECT COUNT(*) FROM service_block_availability")->fetchColumn();
    $liveSba = $db->query("SELECT COUNT(*) FROM service_block_availability WHERE status = 'LIVE'")->fetchColumn();
    echo "[SUCCESS] Migration complete! Total availability records: {$totalSba}, LIVE records: {$liveSba}\n";

} catch (Exception $e) {
    echo "[ERROR] Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
