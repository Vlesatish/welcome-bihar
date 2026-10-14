<?php
require_once __DIR__ . '/../app/Config/Database.php';

try {
    $db = App\Config\Database::getConnection();

    // 1. Check if batch_ref column exists on applications
    $cols = $db->query("SHOW COLUMNS FROM applications LIKE 'batch_ref'")->fetchAll();
    if (empty($cols)) {
        $db->exec("ALTER TABLE applications ADD COLUMN batch_ref VARCHAR(64) NULL AFTER app_ref, ADD INDEX idx_apps_batch_ref (batch_ref)");
        echo "[OK] Added batch_ref column to applications table.\n";
    } else {
        echo "[OK] batch_ref column already exists.\n";
    }

    // 2. Deactivate any lingering test rate rules
    $cleanedRules = $db->exec("UPDATE service_rate_rules SET is_active = 0 WHERE rule_label LIKE '%Test%' AND is_active = 1");
    echo "[OK] Deactivated {$cleanedRules} lingering test rules in service_rate_rules.\n";

    // 3. Verify srv_block_app service
    $srv = $db->query("SELECT id, code, name_en, fee_amount FROM data_types WHERE code = 'srv_block_app'")->fetch(PDO::FETCH_ASSOC);
    echo "[OK] srv_block_app service ID: {$srv['id']}, Base Fee: ₹{$srv['fee_amount']}\n";

} catch (Exception $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
    exit(1);
}
