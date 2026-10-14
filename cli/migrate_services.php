<?php
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

$pdo = \App\Config\Database::getConnection();

echo "Starting migration for extended service flow...\n";

// 1. Update data_types table
$dtCols = $pdo->query("DESCRIBE data_types")->fetchAll(PDO::FETCH_COLUMN);

if (!in_array('category', $dtCols, true)) {
    $pdo->exec("ALTER TABLE data_types ADD COLUMN `category` VARCHAR(100) NOT NULL DEFAULT 'General' AFTER `name_hi`");
    echo "Added category to data_types\n";
}
if (!in_array('processing_time', $dtCols, true)) {
    $pdo->exec("ALTER TABLE data_types ADD COLUMN `processing_time` VARCHAR(100) NOT NULL DEFAULT '24-48 Hours' AFTER `category`");
    echo "Added processing_time to data_types\n";
}
if (!in_array('badge', $dtCols, true)) {
    $pdo->exec("ALTER TABLE data_types ADD COLUMN `badge` VARCHAR(50) NULL AFTER `processing_time`");
    echo "Added badge to data_types\n";
}
if (!in_array('notice', $dtCols, true)) {
    $pdo->exec("ALTER TABLE data_types ADD COLUMN `notice` TEXT NULL AFTER `description_hi`");
    echo "Added notice to data_types\n";
}
if (!in_array('announcement', $dtCols, true)) {
    $pdo->exec("ALTER TABLE data_types ADD COLUMN `announcement` TEXT NULL AFTER `notice`");
    echo "Added announcement to data_types\n";
}

// 2. Update data_type_fields table
$dtfCols = $pdo->query("DESCRIBE data_type_fields")->fetchAll(PDO::FETCH_COLUMN);
if (!in_array('file_types', $dtfCols, true)) {
    $pdo->exec("ALTER TABLE data_type_fields ADD COLUMN `file_types` VARCHAR(150) NULL DEFAULT 'pdf,jpg,jpeg,png' AFTER `validation_pattern`");
    echo "Added file_types to data_type_fields\n";
}
if (!in_array('max_size_mb', $dtfCols, true)) {
    $pdo->exec("ALTER TABLE data_type_fields ADD COLUMN `max_size_mb` INT NOT NULL DEFAULT 2 AFTER `file_types`");
    echo "Added max_size_mb to data_type_fields\n";
}

// 3. Update applications table status ENUM and result columns
$appCols = $pdo->query("DESCRIBE applications")->fetchAll(PDO::FETCH_COLUMN);

$pdo->exec("ALTER TABLE applications MODIFY COLUMN `status` ENUM('PENDING','UNDER_REVIEW','APPROVED','REJECTED','COMPLETED') NOT NULL DEFAULT 'PENDING'");
echo "Updated status ENUM in applications to include COMPLETED\n";

if (!in_array('admin_remarks', $appCols, true)) {
    $pdo->exec("ALTER TABLE applications ADD COLUMN `admin_remarks` TEXT NULL AFTER `remarks`");
    echo "Added admin_remarks to applications\n";
}
if (!in_array('result_file', $appCols, true)) {
    $pdo->exec("ALTER TABLE applications ADD COLUMN `result_file` VARCHAR(255) NULL AFTER `admin_remarks`");
    echo "Added result_file to applications\n";
}
if (!in_array('completed_at', $appCols, true)) {
    $pdo->exec("ALTER TABLE applications ADD COLUMN `completed_at` DATETIME NULL AFTER `final_action_at`");
    echo "Added completed_at to applications\n";
}
if (!in_array('completed_by', $appCols, true)) {
    $pdo->exec("ALTER TABLE applications ADD COLUMN `completed_by` INT NULL AFTER `final_action_by`");
    echo "Added completed_by to applications\n";
}

// 4. Create or update service_rate_rules table
$pdo->exec("
CREATE TABLE IF NOT EXISTS `service_rate_rules` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `data_type_id` INT NOT NULL,
  `state` VARCHAR(100) NOT NULL DEFAULT 'Bihar',
  `district_id` INT NULL,
  `block_id` INT NULL,
  `application_code` VARCHAR(64) NULL,
  `condition_field` VARCHAR(100) NULL,
  `condition_value` VARCHAR(100) NULL,
  `adjustment_type` ENUM('FIXED', 'ADD', 'SUBTRACT') NOT NULL DEFAULT 'FIXED',
  `fee_amount` DECIMAL(12,2) NOT NULL,
  `distributor_commission` DECIMAL(12,2) NULL,
  `rule_label` VARCHAR(150) NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_srr_dt` (`data_type_id`),
  INDEX `idx_srr_dist` (`district_id`),
  INDEX `idx_srr_block` (`block_id`),
  INDEX `idx_srr_code` (`application_code`),
  CONSTRAINT `fk_srr_dt` FOREIGN KEY (`data_type_id`) REFERENCES `data_types` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

$srrCols = $pdo->query("DESCRIBE service_rate_rules")->fetchAll(PDO::FETCH_COLUMN);
if (!in_array('state', $srrCols, true)) {
    $pdo->exec("ALTER TABLE service_rate_rules ADD COLUMN `state` VARCHAR(100) NOT NULL DEFAULT 'Bihar' AFTER `data_type_id`");
    echo "Added state to service_rate_rules\n";
}
if (!in_array('application_code', $srrCols, true)) {
    $pdo->exec("ALTER TABLE service_rate_rules ADD COLUMN `application_code` VARCHAR(64) NULL AFTER `block_id`, ADD INDEX `idx_srr_code` (`application_code`)");
    echo "Added application_code to service_rate_rules\n";
}
echo "Verified service_rate_rules table and columns\n";

// 5. Create notifications table
$pdo->exec("
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `title` VARCHAR(200) NOT NULL,
  `message` TEXT NOT NULL,
  `type` ENUM('INFO', 'SUCCESS', 'WARNING', 'DANGER') NOT NULL DEFAULT 'INFO',
  `link` VARCHAR(255) NULL,
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_notif_user` (`user_id`, `is_read`, `created_at`),
  CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");
echo "Verified notifications table\n";

// 6. Idempotently seed srv_block_app in data_types
$stmt = $pdo->prepare("SELECT id FROM data_types WHERE code = 'srv_block_app'");
$stmt->execute();
$srvBlockId = $stmt->fetchColumn();

if (!$srvBlockId) {
    $ins = $pdo->prepare("
        INSERT INTO data_types (
            code, name_en, name_hi, category, processing_time, badge,
            description_en, description_hi, notice, announcement, icon,
            fee_amount, distributor_commission, duplicate_check_field, is_active, sort_order
        ) VALUES (
            'srv_block_app', 'Block & District Application Pass Service', 'Block & District Application Pass Service',
            'District & Block Services', '24-48 Hours', 'POPULAR',
            'Specialized administrative pass and status verification for Block and District citizen applications.',
            'Specialized administrative pass and status verification for Block and District citizen applications.',
            'Please verify applicant application code and format before submitting.',
            'Standard fee applies per application. Exact rate calculated based on District, Block, and Application Code.',
            'bi-file-earmark-check', 50.00, 10.00, 'application_number', 1, 4
        )
    ");
    $ins->execute();
    $srvBlockId = (int)$pdo->lastInsertId();
    echo "Seeded srv_block_app service (ID: {$srvBlockId})\n";
} else {
    echo "Service srv_block_app already exists (ID: {$srvBlockId})\n";
}

// 7. Idempotently configure dynamic fields for srv_block_app
$blockAppFields = [
    [
        'field_name' => 'state',
        'label_en' => 'State',
        'label_hi' => 'State',
        'field_type' => 'select',
        'placeholder_en' => 'Select State',
        'placeholder_hi' => 'Select State',
        'help_text_en' => 'Designated state jurisdiction',
        'help_text_hi' => 'Designated state jurisdiction',
        'options_json' => json_encode([
            ['value' => 'Bihar', 'label_en' => 'Bihar', 'label_hi' => 'Bihar']
        ]),
        'is_required' => 1,
        'is_unique' => 0,
        'validation_pattern' => null,
        'sort_order' => 1
    ],
    [
        'field_name' => 'application_code',
        'label_en' => 'Application Code / Type',
        'label_hi' => 'Application Code / Type',
        'field_type' => 'select',
        'placeholder_en' => 'Select Application Code',
        'placeholder_hi' => 'Select Application Code',
        'help_text_en' => 'Select government service / application type code',
        'help_text_hi' => 'Select government service / application type code',
        'options_json' => json_encode([
            ['value' => 'BICCO', 'label_en' => 'Income Certificate (BICCO)', 'label_hi' => 'Income Certificate (BICCO)'],
            ['value' => 'BCCCO', 'label_en' => 'Caste Certificate (BCCCO)', 'label_hi' => 'Caste Certificate (BCCCO)'],
            ['value' => 'BRCCO', 'label_en' => 'Residential Certificate (BRCCO)', 'label_hi' => 'Residential Certificate (BRCCO)'],
            ['value' => 'BOBC', 'label_en' => 'OBC / Backward Class Certificate (BOBC)', 'label_hi' => 'OBC / Backward Class Certificate (BOBC)'],
            ['value' => 'NCLCO', 'label_en' => 'Non-Creamy Layer (NCLCO)', 'label_hi' => 'Non-Creamy Layer (NCLCO)'],
            ['value' => 'RTPS', 'label_en' => 'RTPS General Certificate', 'label_hi' => 'RTPS General Certificate'],
            ['value' => 'OTHER', 'label_en' => 'Other Citizen Service', 'label_hi' => 'Other Citizen Service']
        ]),
        'is_required' => 1,
        'is_unique' => 0,
        'validation_pattern' => null,
        'sort_order' => 2
    ],
    [
        'field_name' => 'application_number',
        'label_en' => 'Full Application Number',
        'label_hi' => 'Full Application Number',
        'field_type' => 'text',
        'placeholder_en' => 'e.g. BICCO/2026/12345 or BRCCO/2026/00192',
        'placeholder_hi' => 'e.g. BICCO/2026/12345 or BRCCO/2026/00192',
        'help_text_en' => 'Must match the prefix of the selected Application Code',
        'help_text_hi' => 'Must match the prefix of the selected Application Code',
        'options_json' => null,
        'is_required' => 1,
        'is_unique' => 1,
        'validation_pattern' => '^[A-Za-z0-9/_ -]{5,64}$',
        'sort_order' => 3
    ],
    [
        'field_name' => 'applicant_name',
        'label_en' => 'Applicant / Firm Name',
        'label_hi' => 'Applicant / Firm Name',
        'field_type' => 'text',
        'placeholder_en' => 'Full name of individual applicant or firm',
        'placeholder_hi' => 'Full name of individual applicant or firm',
        'help_text_en' => 'As registered on official application form',
        'help_text_hi' => 'As registered on official application form',
        'options_json' => null,
        'is_required' => 1,
        'is_unique' => 0,
        'validation_pattern' => null,
        'sort_order' => 4
    ],
    [
        'field_name' => 'applicant_mobile',
        'label_en' => 'Contact Mobile Number',
        'label_hi' => 'Contact Mobile Number',
        'field_type' => 'tel',
        'placeholder_en' => '10-digit mobile number',
        'placeholder_hi' => '10-digit mobile number',
        'help_text_en' => 'Used for pass status SMS alerts',
        'help_text_hi' => 'Used for pass status SMS alerts',
        'options_json' => null,
        'is_required' => 1,
        'is_unique' => 0,
        'validation_pattern' => '^[6-9][0-9]{9}$',
        'sort_order' => 5
    ],
    [
        'field_name' => 'applicant_remarks',
        'label_en' => 'Remarks / Purpose Notes',
        'label_hi' => 'Remarks / Purpose Notes',
        'field_type' => 'textarea',
        'placeholder_en' => 'Optional notes regarding urgency, pass purpose, or references',
        'placeholder_hi' => 'Optional notes regarding urgency, pass purpose, or references',
        'help_text_en' => 'Optional',
        'help_text_hi' => 'Optional',
        'options_json' => null,
        'is_required' => 0,
        'is_unique' => 0,
        'validation_pattern' => null,
        'sort_order' => 6
    ]
];

$fldUpsert = $pdo->prepare("
    INSERT INTO data_type_fields (
        data_type_id, field_name, label_en, label_hi, field_type,
        placeholder_en, placeholder_hi, help_text_en, help_text_hi,
        options_json, is_required, is_unique, validation_pattern, sort_order
    ) VALUES (
        ?, ?, ?, ?, ?,
        ?, ?, ?, ?,
        ?, ?, ?, ?, ?
    )
    ON DUPLICATE KEY UPDATE
        label_en = VALUES(label_en),
        label_hi = VALUES(label_hi),
        field_type = VALUES(field_type),
        placeholder_en = VALUES(placeholder_en),
        placeholder_hi = VALUES(placeholder_hi),
        options_json = VALUES(options_json),
        is_required = VALUES(is_required),
        validation_pattern = VALUES(validation_pattern),
        sort_order = VALUES(sort_order)
");

foreach ($blockAppFields as $f) {
    $fldUpsert->execute([
        $srvBlockId, $f['field_name'], $f['label_en'], $f['label_hi'], $f['field_type'],
        $f['placeholder_en'], $f['placeholder_hi'], $f['help_text_en'], $f['help_text_hi'],
        $f['options_json'], $f['is_required'], $f['is_unique'], $f['validation_pattern'], $f['sort_order']
    ]);
}
echo "Verified dynamic fields for srv_block_app\n";

// Populate sample categories, badges, processing times if empty
$pdo->exec("
UPDATE data_types
SET category = CASE 
    WHEN code = 'bocw_registration' THEN 'Labour & Welfare'
    WHEN code = 'epds_ration_card' THEN 'Food & Consumer Protection'
    WHEN code = 'rtps_certificates' THEN 'Revenue & Social Welfare'
    WHEN code = 'srv_block_app' THEN 'District & Block Services'
    ELSE 'General'
END,
processing_time = CASE
    WHEN code = 'bocw_registration' THEN '2-3 Working Days'
    WHEN code = 'epds_ration_card' THEN '3-5 Working Days'
    WHEN code = 'rtps_certificates' THEN '24-48 Hours'
    WHEN code = 'srv_block_app' THEN '24-48 Hours'
    ELSE '24-48 Hours'
END,
badge = CASE
    WHEN code = 'bocw_registration' THEN 'Most Popular'
    WHEN code = 'epds_ration_card' THEN 'Instant Tracking'
    WHEN code = 'rtps_certificates' THEN 'Govt Approved'
    WHEN code = 'srv_block_app' THEN 'POPULAR'
    ELSE 'Active'
END
WHERE category = 'General' OR category IS NULL OR category = '';
");
echo "Updated default categories & badges for existing services\n";

echo "Migration finished successfully!\n";
