<?php
/**
 * Migration: Multi-Block Operator Support
 * Creates operator_block_assignments table and migrates existing assignments idempotently.
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

use App\Config\Config;
use App\Config\Database;

Config::init();
$db = Database::getConnection();

echo "========================================================\n";
echo "   WELCOME BIHAR - MULTI-BLOCK OPERATOR MIGRATION      \n";
echo "========================================================\n\n";

$db->beginTransaction();
try {
    // 1. Create operator_block_assignments table
    $db->exec("
        CREATE TABLE IF NOT EXISTS `operator_block_assignments` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `operator_user_id` INT(11) NOT NULL,
            `district_id` INT(11) NOT NULL,
            `block_id` INT(11) NOT NULL,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `assigned_by` INT(11) DEFAULT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_op_dist_blk` (`operator_user_id`, `district_id`, `block_id`),
            KEY `idx_op_block_user` (`operator_user_id`, `is_active`),
            KEY `idx_op_block_dist_blk` (`district_id`, `block_id`, `is_active`),
            KEY `fk_oba_district` (`district_id`),
            KEY `fk_oba_block` (`block_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "[OK] Table 'operator_block_assignments' verified/created.\n";

    // 2. Ensure operator_application_code_assignments has multi-block unique index
    $idxCheck = $db->query("SHOW INDEX FROM `operator_application_code_assignments` WHERE Key_name = 'uq_op_dist_blk_code'")->fetchAll();
    if (empty($idxCheck)) {
        $db->exec("ALTER TABLE `operator_application_code_assignments` ADD UNIQUE KEY `uq_op_dist_blk_code` (`operator_user_id`, `district_id`, `block_id`, `application_code`)");
        echo "[OK] Added unique index 'uq_op_dist_blk_code' on operator_application_code_assignments.\n";
    } else {
        echo "[OK] Unique index 'uq_op_dist_blk_code' already exists on operator_application_code_assignments.\n";
    }

    // 3. Migrate existing assignments from users table
    $migratedUsersCount = 0;
    $userRows = $db->query("
        SELECT id, assigned_district_id, assigned_block_id, status
        FROM users
        WHERE assigned_district_id IS NOT NULL 
          AND assigned_block_id IS NOT NULL 
          AND assigned_district_id > 0 
          AND assigned_block_id > 0
          AND (
              role = 'OPERATOR'
              OR EXISTS (SELECT 1 FROM user_role_assignments WHERE user_id = users.id AND role_code = 'OPERATOR')
          )
    ")->fetchAll(PDO::FETCH_ASSOC);

    $insBlockStmt = $db->prepare("
        INSERT INTO operator_block_assignments (operator_user_id, district_id, block_id, is_active, assigned_by, created_at, updated_at)
        VALUES (?, ?, ?, ?, 1, NOW(), NOW())
        ON DUPLICATE KEY UPDATE is_active = VALUES(is_active), updated_at = NOW()
    ");

    foreach ($userRows as $u) {
        $isActive = ($u['status'] === 'ACTIVE') ? 1 : 0;
        $insBlockStmt->execute([
            (int)$u['id'],
            (int)$u['assigned_district_id'],
            (int)$u['assigned_block_id'],
            $isActive
        ]);
        $migratedUsersCount++;
    }
    echo "[OK] Migrated {$migratedUsersCount} location records from users to operator_block_assignments.\n";

    // 4. Ensure distinct blocks from existing operator_application_code_assignments are in operator_block_assignments
    $codeBlocks = $db->query("
        SELECT DISTINCT operator_user_id, district_id, block_id
        FROM operator_application_code_assignments
        WHERE is_active = 1
    ")->fetchAll(PDO::FETCH_ASSOC);

    $migratedFromCodes = 0;
    foreach ($codeBlocks as $cb) {
        $insBlockStmt->execute([
            (int)$cb['operator_user_id'],
            (int)$cb['district_id'],
            (int)$cb['block_id'],
            1
        ]);
        $migratedFromCodes++;
    }
    echo "[OK] Synchronized {$migratedFromCodes} block mappings from operator_application_code_assignments.\n";

    if ($db->inTransaction()) {
        $db->commit();
    }
    echo "\n=== MIGRATION COMPLETED SUCCESSFULLY ===\n";
} catch (\Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo "ERROR in migration: " . $e->getMessage() . "\n";
    exit(1);
}
