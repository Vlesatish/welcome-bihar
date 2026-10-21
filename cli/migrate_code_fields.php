<?php
/**
 * Migration: Create application_code_fields table and adjust application_field_values
 */

require_once 'f:/code/welcome bihar/app/Config/Database.php';

$db = \App\Config\Database::getConnection();

echo "Running Migration for Application Code Dynamic Form System...\n";

// 1. Create table application_code_fields
$db->exec("
    CREATE TABLE IF NOT EXISTS `application_code_fields` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `data_type_id` INT NOT NULL,
        `application_code` VARCHAR(64) NOT NULL,
        `field_name` VARCHAR(100) NOT NULL,
        `label` VARCHAR(150) NOT NULL,
        `field_type` ENUM('text', 'number', 'mobile', 'aadhaar', 'date', 'select', 'radio', 'textarea', 'file', 'email') NOT NULL DEFAULT 'text',
        `placeholder` VARCHAR(255) NULL,
        `help_text` VARCHAR(255) NULL,
        `options_json` TEXT NULL,
        `validation_pattern` VARCHAR(255) NULL,
        `min_length` INT NULL,
        `max_length` INT NULL,
        `min_value` DECIMAL(15,2) NULL,
        `max_value` DECIMAL(15,2) NULL,
        `allowed_file_types` VARCHAR(150) NULL,
        `max_file_size_mb` INT NULL,
        `is_required` TINYINT(1) NOT NULL DEFAULT 1,
        `is_unique` TINYINT(1) NOT NULL DEFAULT 0,
        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
        `sort_order` INT NOT NULL DEFAULT 0,
        `created_at` DATETIME NOT NULL,
        `updated_at` DATETIME NOT NULL,
        UNIQUE KEY `uniq_code_field` (`data_type_id`, `application_code`, `field_name`),
        KEY `idx_code_active` (`data_type_id`, `application_code`, `is_active`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");
echo "[PASS] Table application_code_fields created or verified.\n";

// 2. Adjust application_field_values foreign key if present
try {
    // Check if fk_app_fvalues_field exists
    $fkCheck = $db->query("
        SELECT CONSTRAINT_NAME
        FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'application_field_values'
          AND CONSTRAINT_NAME = 'fk_app_fvalues_field'
    ")->fetchColumn();

    if ($fkCheck) {
        $db->exec("ALTER TABLE `application_field_values` DROP FOREIGN KEY `fk_app_fvalues_field`");
        echo "[PASS] Foreign key fk_app_fvalues_field dropped successfully.\n";
    }
} catch (\Throwable $e) {
    echo "[INFO] FK check notice: " . $e->getMessage() . "\n";
}

// 3. Make field_id nullable and add code_field_id if not present
try {
    $db->exec("ALTER TABLE `application_field_values` MODIFY `field_id` INT NULL");
    echo "[PASS] Column field_id modified to nullable.\n";
} catch (\Throwable $e) {
    echo "[INFO] field_id modify notice: " . $e->getMessage() . "\n";
}

$colCheck = $db->query("
    SELECT COLUMN_NAME
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'application_field_values'
      AND COLUMN_NAME = 'code_field_id'
")->fetchColumn();

if (!$colCheck) {
    $db->exec("ALTER TABLE `application_field_values` ADD COLUMN `code_field_id` INT NULL AFTER `field_id`, ADD KEY `idx_code_field_id` (`code_field_id`)");
    echo "[PASS] Column code_field_id added to application_field_values.\n";
}

// 4. Seed initial code fields for srv_block_app
$dtId = (int)($db->query("SELECT id FROM data_types WHERE code = 'srv_block_app'")->fetchColumn() ?: 4);

$seedFields = [
    // LABOURCARD Form
    [
        'code' => 'LABOURCARD',
        'field_name' => 'application_number',
        'label' => 'Application Number',
        'field_type' => 'text',
        'placeholder' => 'e.g. LC/2026/12345',
        'help_text' => 'Enter application receipt number',
        'is_required' => 1,
        'is_unique' => 1,
        'min_length' => 5,
        'max_length' => 64,
        'validation_pattern' => '^[A-Za-z0-9/_ -]{5,64}$',
        'sort_order' => 1
    ],
    [
        'code' => 'LABOURCARD',
        'field_name' => 'aadhaar_number',
        'label' => 'Aadhaar Number',
        'field_type' => 'aadhaar',
        'placeholder' => '12-digit Aadhaar Number',
        'help_text' => 'Applicant 12-digit UID without spaces',
        'is_required' => 1,
        'is_unique' => 0,
        'min_length' => 12,
        'max_length' => 12,
        'validation_pattern' => '^[0-9]{12}$',
        'sort_order' => 2
    ],
    [
        'code' => 'LABOURCARD',
        'field_name' => 'applicant_name',
        'label' => 'Applicant Name',
        'field_type' => 'text',
        'placeholder' => 'Full Name as per Aadhaar',
        'help_text' => null,
        'is_required' => 1,
        'is_unique' => 0,
        'min_length' => 2,
        'max_length' => 150,
        'validation_pattern' => null,
        'sort_order' => 3
    ],

    // BICCO (Income Certificate) Form
    [
        'code' => 'BICCO',
        'field_name' => 'application_number',
        'label' => 'Application Number',
        'field_type' => 'text',
        'placeholder' => 'e.g. BICCO/2026/12345',
        'help_text' => 'Enter BICCO application number',
        'is_required' => 1,
        'is_unique' => 1,
        'min_length' => 5,
        'max_length' => 64,
        'validation_pattern' => '^[A-Za-z0-9/_ -]{5,64}$',
        'sort_order' => 1
    ],
    [
        'code' => 'BICCO',
        'field_name' => 'applicant_name',
        'label' => 'Applicant Name',
        'field_type' => 'text',
        'placeholder' => 'Full Name of Applicant',
        'help_text' => null,
        'is_required' => 1,
        'is_unique' => 0,
        'min_length' => 2,
        'max_length' => 150,
        'validation_pattern' => null,
        'sort_order' => 2
    ],

    // BCCCO (Caste Certificate) Form
    [
        'code' => 'BCCCO',
        'field_name' => 'application_number',
        'label' => 'Application Number',
        'field_type' => 'text',
        'placeholder' => 'e.g. BCCCO/2026/12345',
        'help_text' => 'Enter BCCCO application number',
        'is_required' => 1,
        'is_unique' => 1,
        'min_length' => 5,
        'max_length' => 64,
        'validation_pattern' => '^[A-Za-z0-9/_ -]{5,64}$',
        'sort_order' => 1
    ],
    [
        'code' => 'BCCCO',
        'field_name' => 'registration_number',
        'label' => 'Registration Number',
        'field_type' => 'text',
        'placeholder' => 'Optional registration or token number',
        'help_text' => null,
        'is_required' => 0,
        'is_unique' => 0,
        'min_length' => 0,
        'max_length' => 64,
        'validation_pattern' => null,
        'sort_order' => 2
    ],
    [
        'code' => 'BCCCO',
        'field_name' => 'applicant_name',
        'label' => 'Applicant Name',
        'field_type' => 'text',
        'placeholder' => 'Full Name of Applicant',
        'help_text' => null,
        'is_required' => 1,
        'is_unique' => 0,
        'min_length' => 2,
        'max_length' => 150,
        'validation_pattern' => null,
        'sort_order' => 3
    ]
];

// Add default core fields for other active codes (BRCCO, BOBC, NCLCO, OTHER)
$otherCodes = ['BRCCO', 'BOBC', 'NCLCO', 'OTHER', 'RTPS'];
foreach ($otherCodes as $oc) {
    $seedFields[] = [
        'code' => $oc,
        'field_name' => 'application_number',
        'label' => 'Application Number',
        'field_type' => 'text',
        'placeholder' => "e.g. {$oc}/2026/12345",
        'help_text' => "Enter {$oc} receipt number",
        'is_required' => 1,
        'is_unique' => 1,
        'min_length' => 5,
        'max_length' => 64,
        'validation_pattern' => '^[A-Za-z0-9/_ -]{5,64}$',
        'sort_order' => 1
    ];
    $seedFields[] = [
        'code' => $oc,
        'field_name' => 'applicant_name',
        'label' => 'Applicant Name',
        'field_type' => 'text',
        'placeholder' => 'Full Name of Applicant',
        'help_text' => null,
        'is_required' => 1,
        'is_unique' => 0,
        'min_length' => 2,
        'max_length' => 150,
        'validation_pattern' => null,
        'sort_order' => 2
    ];
}

$insStmt = $db->prepare("
    INSERT INTO `application_code_fields` (
        `data_type_id`, `application_code`, `field_name`, `label`, `field_type`,
        `placeholder`, `help_text`, `options_json`, `validation_pattern`,
        `min_length`, `max_length`, `is_required`, `is_unique`, `is_active`,
        `sort_order`, `created_at`, `updated_at`
    ) VALUES (
        ?, UPPER(?), ?, ?, ?,
        ?, ?, ?, ?,
        ?, ?, ?, ?, 1,
        ?, NOW(), NOW()
    )
    ON DUPLICATE KEY UPDATE
        `label` = VALUES(`label`),
        `field_type` = VALUES(`field_type`),
        `placeholder` = VALUES(`placeholder`),
        `help_text` = VALUES(`help_text`),
        `validation_pattern` = VALUES(`validation_pattern`),
        `min_length` = VALUES(`min_length`),
        `max_length` = VALUES(`max_length`),
        `is_required` = VALUES(`is_required`),
        `is_unique` = VALUES(`is_unique`),
        `is_active` = 1,
        `sort_order` = VALUES(`sort_order`),
        `updated_at` = NOW()
");

foreach ($seedFields as $sf) {
    $insStmt->execute([
        $dtId,
        strtoupper($sf['code']),
        $sf['field_name'],
        $sf['label'],
        $sf['field_type'],
        $sf['placeholder'] ?? null,
        $sf['help_text'] ?? null,
        $sf['options_json'] ?? null,
        $sf['validation_pattern'] ?? null,
        $sf['min_length'] ?? null,
        $sf['max_length'] ?? null,
        $sf['is_required'] ?? 1,
        $sf['is_unique'] ?? 0,
        $sf['sort_order'] ?? 1
    ]);
}

echo "[PASS] Initial application_code_fields seeded successfully (" . count($seedFields) . " fields).\n";
echo "Migration complete!\n";
