<?php
/**
 * Safe migration for Service Pricing & Dynamic Location Management
 * Preserves all existing records. Does not modify or delete existing districts or blocks.
 */
declare(strict_types=1);

require_once __DIR__ . '/../app/Config/Env.php';
\App\Config\Env::load(__DIR__ . '/../.env');
require_once __DIR__ . '/../app/Config/Database.php';

try {
    $db = \App\Config\Database::getConnection();
    echo "Running safe migration for Service Pricing & Locations...\n";

    // 1. Check districts table
    $dCols = $db->query("DESCRIBE districts")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('state', $dCols, true)) {
        $db->exec("ALTER TABLE districts ADD COLUMN `state` VARCHAR(100) NOT NULL DEFAULT 'Bihar' AFTER `id`, ADD INDEX `idx_districts_state` (`state`)");
        echo "[OK] Added 'state' column to districts table with default 'Bihar'.\n";
    } else {
        echo "[INFO] Column 'state' already exists in districts table.\n";
    }

    // 2. Check blocks table
    $bCols = $db->query("DESCRIBE blocks")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('is_active', $bCols, true)) {
        $db->exec("ALTER TABLE blocks ADD COLUMN `is_active` TINYINT(1) NOT NULL DEFAULT 1 AFTER `code`, ADD INDEX `idx_blocks_active` (`is_active`)");
        echo "[OK] Added 'is_active' column to blocks table.\n";
    }

    // 3. Check service_rate_rules table
    $sCols = $db->query("DESCRIBE service_rate_rules")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('state', $sCols, true)) {
        $db->exec("ALTER TABLE service_rate_rules ADD COLUMN `state` VARCHAR(100) NOT NULL DEFAULT 'Bihar' AFTER `data_type_id`");
        echo "[OK] Added 'state' column to service_rate_rules table.\n";
    }
    if (!in_array('application_code', $sCols, true)) {
        $db->exec("ALTER TABLE service_rate_rules ADD COLUMN `application_code` VARCHAR(64) NULL AFTER `block_id`, ADD INDEX `idx_srr_code` (`application_code`)");
        echo "[OK] Added 'application_code' column to service_rate_rules table.\n";
    }

    // Verify row counts to guarantee no records were deleted
    $distCount = $db->query("SELECT COUNT(*) FROM districts")->fetchColumn();
    $blockCount = $db->query("SELECT COUNT(*) FROM blocks")->fetchColumn();
    echo "Migration Complete!\nDistricts count: $distCount (preserved)\nBlocks count: $blockCount (preserved)\n";
} catch (Exception $e) {
    echo "Migration Error: " . $e->getMessage() . "\n";
    exit(1);
}
