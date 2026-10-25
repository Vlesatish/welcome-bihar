<?php
/**
 * Safe Migration: Add New Pan Card Apply Service (PAN_CARD)
 * - Adds pan_card in data_types
 * - Adds all 19 application_code_fields for PAN_CARD
 * - Adds standard rate rule in service_rate_rules
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Config/Database.php';

$db = \App\Config\Database::getConnection();

echo "Running Migration: New Pan Card Apply Service...\n";

// 1. Ensure pan_card exists in data_types
$dtStmt = $db->prepare("SELECT id FROM data_types WHERE code = 'pan_card' LIMIT 1");
$dtStmt->execute();
$panDataTypeId = $dtStmt->fetchColumn();

if (!$panDataTypeId) {
    $ins = $db->prepare("
        INSERT INTO data_types (
            code, name_en, name_hi, category, processing_time, badge,
            description_en, description_hi, notice, notice_text, notice_active,
            icon, fee_amount, distributor_commission, duplicate_check_field,
            is_active, chat_enabled, enable_chat, sort_order, created_at, updated_at
        ) VALUES (
            'pan_card',
            'New Pan Card Apply',
            'नया पैन कार्ड आवेदन (सिर्फ आधार कार्ड से)',
            'PAN & Identity Services',
            '24-48 Hours',
            'New Service',
            'Apply New Physical & E-PAN Card using only Aadhaar card details with photo and signature upload.',
            'सिर्फ आधार कार्ड, फोटो और हस्ताक्षर से नया फिजिकल व ई-पैन कार्ड आवेदन करें।',
            'Physical Pan Card ke sath E-Pan Card Bhi Milega',
            'Physical Pan Card ke sath E-Pan Card Bhi Milega',
            1,
            'bi-credit-card-2-front',
            107.00,
            7.00,
            'aadhaar_no',
            1,
            1,
            1,
            2,
            NOW(),
            NOW()
        )
    ");
    $ins->execute();
    $panDataTypeId = (int)$db->lastInsertId();
    echo "[PASS] Created 'pan_card' in data_types (ID: {$panDataTypeId})\n";
} else {
    $panDataTypeId = (int)$panDataTypeId;
    $upd = $db->prepare("
        UPDATE data_types
        SET is_active = 1,
            fee_amount = 107.00,
            notice = 'Physical Pan Card ke sath E-Pan Card Bhi Milega',
            notice_text = 'Physical Pan Card ke sath E-Pan Card Bhi Milega',
            notice_active = 1
        WHERE id = ?
    ");
    $upd->execute([$panDataTypeId]);
    echo "[PASS] 'pan_card' already exists in data_types (ID: {$panDataTypeId}), updated.\n";
}

// 2. Fetch srv_block_app ID (id 4 or by code)
$blockAppId = (int)($db->query("SELECT id FROM data_types WHERE code = 'srv_block_app' LIMIT 1")->fetchColumn() ?: 4);

// 3. Define all 19 fields matching the image
$fields = [
    [
        'field_name' => 'full_name',
        'label' => 'FULL NAME *',
        'field_type' => 'text',
        'placeholder' => 'e.g. Ramesh Kumar',
        'help_text' => 'Applicant full name as per Aadhaar card',
        'options_json' => null,
        'validation_pattern' => null,
        'min_length' => 2,
        'max_length' => 150,
        'allowed_file_types' => null,
        'max_file_size_mb' => null,
        'is_required' => 1,
        'is_unique' => 0,
        'sort_order' => 1
    ],
    [
        'field_name' => 'gender',
        'label' => 'GENDER *',
        'field_type' => 'select',
        'placeholder' => 'Select Gender',
        'help_text' => null,
        'options_json' => json_encode([
            ['value' => 'Male', 'label_en' => 'Male', 'label_hi' => 'पुरुष'],
            ['value' => 'Female', 'label_en' => 'Female', 'label_hi' => 'महिला'],
            ['value' => 'Transgender', 'label_en' => 'Transgender / Other', 'label_hi' => 'अन्य']
        ]),
        'validation_pattern' => null,
        'min_length' => null,
        'max_length' => null,
        'allowed_file_types' => null,
        'max_file_size_mb' => null,
        'is_required' => 1,
        'is_unique' => 0,
        'sort_order' => 2
    ],
    [
        'field_name' => 'dob',
        'label' => 'DATE OF BIRTH *',
        'field_type' => 'date',
        'placeholder' => 'e.g. 29-01-1981',
        'help_text' => 'Format: DD-MM-YYYY (Date of Birth on Aadhaar Card)',
        'options_json' => null,
        'validation_pattern' => null,
        'min_length' => null,
        'max_length' => null,
        'allowed_file_types' => null,
        'max_file_size_mb' => null,
        'is_required' => 1,
        'is_unique' => 0,
        'sort_order' => 3
    ],
    [
        'field_name' => 'state',
        'label' => 'SELECT STATE *',
        'field_type' => 'select',
        'placeholder' => 'Select State',
        'help_text' => 'Applicant state of residence',
        'options_json' => json_encode([
            ['value' => 'Bihar', 'label_en' => 'Bihar'],
            ['value' => 'Jharkhand', 'label_en' => 'Jharkhand'],
            ['value' => 'Uttar Pradesh', 'label_en' => 'Uttar Pradesh'],
            ['value' => 'West Bengal', 'label_en' => 'West Bengal'],
            ['value' => 'Delhi', 'label_en' => 'Delhi'],
            ['value' => 'Madhya Pradesh', 'label_en' => 'Madhya Pradesh'],
            ['value' => 'Rajasthan', 'label_en' => 'Rajasthan'],
            ['value' => 'Maharashtra', 'label_en' => 'Maharashtra'],
            ['value' => 'Punjab', 'label_en' => 'Punjab'],
            ['value' => 'Haryana', 'label_en' => 'Haryana'],
            ['value' => 'Gujarat', 'label_en' => 'Gujarat'],
            ['value' => 'Odisha', 'label_en' => 'Odisha'],
            ['value' => 'Assam', 'label_en' => 'Assam'],
            ['value' => 'Other', 'label_en' => 'Other State']
        ]),
        'validation_pattern' => null,
        'min_length' => null,
        'max_length' => null,
        'allowed_file_types' => null,
        'max_file_size_mb' => null,
        'is_required' => 1,
        'is_unique' => 0,
        'sort_order' => 4
    ],
    [
        'field_name' => 'aadhaar_no',
        'label' => 'AADHAAR NO *',
        'field_type' => 'aadhaar',
        'placeholder' => 'e.g. 989800000000',
        'help_text' => '12-digit Aadhaar Card Number',
        'options_json' => null,
        'validation_pattern' => '^[0-9]{12}$',
        'min_length' => 12,
        'max_length' => 12,
        'allowed_file_types' => null,
        'max_file_size_mb' => null,
        'is_required' => 1,
        'is_unique' => 0,
        'sort_order' => 5
    ],
    [
        'field_name' => 'mobile_no',
        'label' => 'MOBILE NO *',
        'field_type' => 'mobile',
        'placeholder' => 'e.g. 9800000000',
        'help_text' => '10-digit applicant mobile number',
        'options_json' => null,
        'validation_pattern' => '^[6-9][0-9]{9}$',
        'min_length' => 10,
        'max_length' => 10,
        'allowed_file_types' => null,
        'max_file_size_mb' => null,
        'is_required' => 1,
        'is_unique' => 0,
        'sort_order' => 6
    ],
    [
        'field_name' => 'email_id',
        'label' => 'EMAIL ID *',
        'field_type' => 'email',
        'placeholder' => 'e.g. fgn@gmail.com',
        'help_text' => 'E-Pan card will be delivered to this email',
        'options_json' => null,
        'validation_pattern' => null,
        'min_length' => 5,
        'max_length' => 150,
        'allowed_file_types' => null,
        'max_file_size_mb' => null,
        'is_required' => 1,
        'is_unique' => 0,
        'sort_order' => 7
    ],
    [
        'field_name' => 'father_name',
        'label' => 'FATHER NAME *',
        'field_type' => 'text',
        'placeholder' => 'Enter Father Name',
        'help_text' => 'Father full name (as per official documents)',
        'options_json' => null,
        'validation_pattern' => null,
        'min_length' => 2,
        'max_length' => 150,
        'allowed_file_types' => null,
        'max_file_size_mb' => null,
        'is_required' => 1,
        'is_unique' => 0,
        'sort_order' => 8
    ],
    [
        'field_name' => 'mother_name',
        'label' => 'MOTHER NAME *',
        'field_type' => 'text',
        'placeholder' => 'Enter Mother Name',
        'help_text' => 'Mother full name',
        'options_json' => null,
        'validation_pattern' => null,
        'min_length' => 2,
        'max_length' => 150,
        'allowed_file_types' => null,
        'max_file_size_mb' => null,
        'is_required' => 1,
        'is_unique' => 0,
        'sort_order' => 9
    ],
    [
        'field_name' => 'husband_name',
        'label' => 'HUSBAND NAME',
        'field_type' => 'text',
        'placeholder' => 'Enter Husband Name',
        'help_text' => 'Applicable for married women (Optional)',
        'options_json' => null,
        'validation_pattern' => null,
        'min_length' => 0,
        'max_length' => 150,
        'allowed_file_types' => null,
        'max_file_size_mb' => null,
        'is_required' => 0,
        'is_unique' => 0,
        'sort_order' => 10
    ],
    [
        'field_name' => 'village',
        'label' => 'VILLAGE *',
        'field_type' => 'text',
        'placeholder' => 'Enter Village',
        'help_text' => 'Village / Ward / Street name',
        'options_json' => null,
        'validation_pattern' => null,
        'min_length' => 2,
        'max_length' => 150,
        'allowed_file_types' => null,
        'max_file_size_mb' => null,
        'is_required' => 1,
        'is_unique' => 0,
        'sort_order' => 11
    ],
    [
        'field_name' => 'post_office',
        'label' => 'POST OFFICE *',
        'field_type' => 'text',
        'placeholder' => 'Enter Post Office',
        'help_text' => 'Post Office name',
        'options_json' => null,
        'validation_pattern' => null,
        'min_length' => 2,
        'max_length' => 100,
        'allowed_file_types' => null,
        'max_file_size_mb' => null,
        'is_required' => 1,
        'is_unique' => 0,
        'sort_order' => 12
    ],
    [
        'field_name' => 'block_name',
        'label' => 'BLOCK NAME *',
        'field_type' => 'text',
        'placeholder' => 'Enter Block Name',
        'help_text' => 'Block / Tehsil / Sub-district',
        'options_json' => null,
        'validation_pattern' => null,
        'min_length' => 2,
        'max_length' => 100,
        'allowed_file_types' => null,
        'max_file_size_mb' => null,
        'is_required' => 1,
        'is_unique' => 0,
        'sort_order' => 13
    ],
    [
        'field_name' => 'district_name',
        'label' => 'DISTRICT NAME *',
        'field_type' => 'text',
        'placeholder' => 'Enter District Name',
        'help_text' => 'District name',
        'options_json' => null,
        'validation_pattern' => null,
        'min_length' => 2,
        'max_length' => 100,
        'allowed_file_types' => null,
        'max_file_size_mb' => null,
        'is_required' => 1,
        'is_unique' => 0,
        'sort_order' => 14
    ],
    [
        'field_name' => 'pincode',
        'label' => 'PINCODE *',
        'field_type' => 'number',
        'placeholder' => 'e.g. 824206',
        'help_text' => '6-digit postal pincode',
        'options_json' => null,
        'validation_pattern' => '^[1-9][0-9]{5}$',
        'min_length' => 6,
        'max_length' => 6,
        'allowed_file_types' => null,
        'max_file_size_mb' => null,
        'is_required' => 1,
        'is_unique' => 0,
        'sort_order' => 15
    ],
    [
        'field_name' => 'photo',
        'label' => 'PHOTO *',
        'field_type' => 'file',
        'placeholder' => 'Upload Document / Photo (PDF / JPG / PNG)',
        'help_text' => 'Drag & Drop or फ़ाइल चुनें (अधिकतम 2 MB)',
        'options_json' => null,
        'validation_pattern' => null,
        'min_length' => null,
        'max_length' => null,
        'allowed_file_types' => 'jpg,jpeg,png,pdf',
        'max_file_size_mb' => 2,
        'is_required' => 1,
        'is_unique' => 0,
        'sort_order' => 16
    ],
    [
        'field_name' => 'signature',
        'label' => 'SIGNATURE *',
        'field_type' => 'file',
        'placeholder' => 'Upload Document / Photo (PDF / JPG / PNG)',
        'help_text' => 'Drag & Drop or फ़ाइल चुनें (अधिकतम 2 MB)',
        'options_json' => null,
        'validation_pattern' => null,
        'min_length' => null,
        'max_length' => null,
        'allowed_file_types' => 'jpg,jpeg,png,pdf',
        'max_file_size_mb' => 2,
        'is_required' => 1,
        'is_unique' => 0,
        'sort_order' => 17
    ],
    [
        'field_name' => 'aadhar_card',
        'label' => 'AADHAR CARD *',
        'field_type' => 'file',
        'placeholder' => 'Upload Document / Photo (PDF / JPG / PNG)',
        'help_text' => 'Drag & Drop or फ़ाइल चुनें (अधिकतम 2 MB)',
        'options_json' => null,
        'validation_pattern' => null,
        'min_length' => null,
        'max_length' => null,
        'allowed_file_types' => 'pdf,jpg,jpeg,png',
        'max_file_size_mb' => 2,
        'is_required' => 1,
        'is_unique' => 0,
        'sort_order' => 18
    ],
    [
        'field_name' => 'pan_form',
        'label' => 'PAN FORM *',
        'field_type' => 'file',
        'placeholder' => 'Upload Document / Photo (PDF / JPG / PNG)',
        'help_text' => 'Drag & Drop or फ़ाइल चुनें (अधिकतम 2 MB)',
        'options_json' => null,
        'validation_pattern' => null,
        'min_length' => null,
        'max_length' => null,
        'allowed_file_types' => 'pdf,jpg,jpeg,png',
        'max_file_size_mb' => 2,
        'is_required' => 1,
        'is_unique' => 0,
        'sort_order' => 19
    ]
];

// Seed fields for BOTH data_type_id $panDataTypeId and $blockAppId so it is available across all systems!
$targetDataTypeIds = array_unique([$panDataTypeId, $blockAppId]);

foreach ($targetDataTypeIds as $dtId) {
    foreach ($fields as $f) {
        $stmt = $db->prepare("
            INSERT INTO application_code_fields (
                data_type_id, application_code, field_name, label, field_type,
                placeholder, help_text, options_json, validation_pattern,
                min_length, max_length, allowed_file_types, max_file_size_mb,
                is_required, is_unique, is_active, sort_order, created_at, updated_at
            ) VALUES (
                ?, 'PAN_CARD', ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, 1, ?, NOW(), NOW()
            )
            ON DUPLICATE KEY UPDATE
                label = VALUES(label),
                field_type = VALUES(field_type),
                placeholder = VALUES(placeholder),
                help_text = VALUES(help_text),
                options_json = VALUES(options_json),
                validation_pattern = VALUES(validation_pattern),
                min_length = VALUES(min_length),
                max_length = VALUES(max_length),
                allowed_file_types = VALUES(allowed_file_types),
                max_file_size_mb = VALUES(max_file_size_mb),
                is_required = VALUES(is_required),
                is_active = 1,
                sort_order = VALUES(sort_order),
                updated_at = NOW()
        ");
        $stmt->execute([
            $dtId,
            $f['field_name'],
            $f['label'],
            $f['field_type'],
            $f['placeholder'],
            $f['help_text'],
            $f['options_json'],
            $f['validation_pattern'],
            $f['min_length'],
            $f['max_length'],
            $f['allowed_file_types'],
            $f['max_file_size_mb'],
            $f['is_required'],
            $f['is_unique'],
            $f['sort_order']
        ]);
    }
}
echo "[PASS] All 19 fields configured for PAN_CARD in application_code_fields.\n";

// 4. Ensure rate rule exists in service_rate_rules
$srrCheck = $db->prepare("SELECT id FROM service_rate_rules WHERE application_code = 'PAN_CARD' LIMIT 1");
$srrCheck->execute();
if (!$srrCheck->fetch()) {
    $insSrr = $db->prepare("
        INSERT INTO service_rate_rules (
            data_type_id, state, district_id, block_id, application_code,
            adjustment_type, fee_amount, distributor_commission, rule_label,
            is_active, created_at, updated_at
        ) VALUES (
            ?, 'Bihar', NULL, NULL, 'PAN_CARD',
            'FIXED', 107.00, 7.00, 'New Pan Card Apply (Aadhaar)',
            1, NOW(), NOW()
        )
    ");
    $insSrr->execute([$panDataTypeId]);
    echo "[PASS] Inserted rate rule for PAN_CARD (Fee: ₹107.00)\n";
} else {
    echo "[PASS] Rate rule for PAN_CARD already exists.\n";
}

echo "Migration Complete!\n";
