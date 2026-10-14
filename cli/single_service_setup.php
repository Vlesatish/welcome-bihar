<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/Config/Env.php';
\App\Config\Env::load(__DIR__ . '/../.env');
require_once __DIR__ . '/../app/Config/Database.php';

$db = \App\Config\Database::getConnection();

echo "Running Single Service Setup for Welcome Bihar...\n";

// 1. Find srv_block_app
$stmt = $db->query("SELECT id, code, name_en, fee_amount, is_active FROM data_types WHERE code = 'srv_block_app'");
$blockApp = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$blockApp) {
    echo "Creating srv_block_app in data_types...\n";
    $ins = $db->prepare("
        INSERT INTO data_types (
            code, name_en, name_hi, category, processing_time, badge,
            description_en, description_hi, notice, announcement, icon,
            fee_amount, distributor_commission, duplicate_check_field, is_active, sort_order, created_at
        ) VALUES (
            'srv_block_app', 'Block & District Application Pass Service', 'Block & District Application Pass Service',
            'District & Block Services', '10-30 MIN', 'POPULAR',
            'Custom rate application verification/passing service based on State, District, Block & Application Code (BICCO, BCCCO, BRCCO, NCLCO).',
            'Special administrative pass and verification service for citizen applications.',
            'Please verify applicant application code and format before submitting.',
            'Standard fee applies per application. Exact rate calculated based on District, Block, and Application Code.',
            'bi-building', 50.00, 10.00, 'application_number', 1, 1, NOW()
        )
    ");
    $ins->execute();
    $blockAppId = (int)$db->lastInsertId();
} else {
    $blockAppId = (int)$blockApp['id'];
    // Ensure it is active and has correct sort order
    $db->prepare("
        UPDATE data_types
        SET is_active = 1, sort_order = 1, processing_time = '10-30 MIN'
        WHERE id = ?
    ")->execute([$blockAppId]);
}

echo "srv_block_app ID is: {$blockAppId}\n";

// 2. Deactivate all OTHER services (preserve old applications, transactions, audit history)
$deactivated = $db->exec("UPDATE data_types SET is_active = 0 WHERE id != {$blockAppId}");
echo "Deactivated {$deactivated} other services from active listings.\n";

// 3. Check portal settings for fallback rates
$db->exec("
    CREATE TABLE IF NOT EXISTS settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) NOT NULL UNIQUE,
        setting_value TEXT NULL,
        description VARCHAR(255) NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

// Insert default fallbacks if not present:
// BICCO/BCCCO/BRCCO fallback: 100
// BOBC/NCLCO/OTHER fallback: 130
$fallbacks = [
    ['fallback_rate_standard', '100.00', 'Fallback rate for BICCO, BCCCO, BRCCO when no block custom rate is configured'],
    ['fallback_rate_other', '130.00', 'Fallback rate for BOBC, NCLCO, OTHER when no block custom rate is configured']
];

foreach ($fallbacks as $fb) {
    $stmt = $db->prepare("SELECT id FROM settings WHERE setting_key = ?");
    $stmt->execute([$fb[0]]);
    if (!$stmt->fetchColumn()) {
        $db->prepare("INSERT INTO settings (setting_key, setting_value, description) VALUES (?, ?, ?)")
           ->execute($fb);
        echo "Configured setting {$fb[0]} = {$fb[1]}\n";
    }
}

// 4. Verify existing rate rules
$ruleCount = (int)$db->query("SELECT COUNT(*) FROM service_rate_rules WHERE data_type_id = {$blockAppId}")->fetchColumn();
echo "Currently {$ruleCount} rate rules configured for srv_block_app.\n";

echo "Single Service Setup Completed Successfully!\n";
