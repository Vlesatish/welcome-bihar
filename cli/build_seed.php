<?php
// CLI script to build production-grade database/seed.sql

$labourSeedPath = 'F:\\code\\labour card  2.0\\database\\seed.sql';
if (!file_exists($labourSeedPath)) {
    die("Labour seed not found at $labourSeedPath\n");
}

$labourContent = file_get_contents($labourSeedPath);

// Extract block lines
preg_match_all("/\(([0-9]+),\s*([0-9]+),\s*'([^']+)',\s*'([^']*)',\s*([01])\)/", $labourContent, $matches, PREG_SET_ORDER);

echo "Extracted " . count($matches) . " block rows.\n";

$districts = [
    [1, 'Araria', 'Araria', 'ARA'],
    [2, 'Arwal', 'Arwal', 'ARW'],
    [3, 'Aurangabad', 'Aurangabad', 'AUR'],
    [4, 'Banka', 'Banka', 'BAN'],
    [5, 'Begusarai', 'Begusarai', 'BEG'],
    [6, 'Bhagalpur', 'Bhagalpur', 'BHA'],
    [7, 'Bhojpur', 'Bhojpur', 'BHO'],
    [8, 'Buxar', 'Buxar', 'BUX'],
    [9, 'Darbhanga', 'Darbhanga', 'DAR'],
    [10, 'East Champaran (Motihari)', 'East Champaran', 'EAS'],
    [11, 'Gaya', 'Gaya', 'GAY'],
    [12, 'Gopalganj', 'Gopalganj', 'GOP'],
    [13, 'Jamui', 'Jamui', 'JAM'],
    [14, 'Jehanabad', 'Jehanabad', 'JEH'],
    [15, 'Kaimur (Bhabua)', 'Kaimur', 'KAI'],
    [16, 'Katihar', 'Katihar', 'KAT'],
    [17, 'Khagaria', 'Khagaria', 'KHA'],
    [18, 'Kishanganj', 'Kishanganj', 'KIS'],
    [19, 'Lakhisarai', 'Lakhisarai', 'LAK'],
    [20, 'Madhepura', 'Madhepura', 'MAD'],
    [21, 'Madhubani', 'Madhubani', 'MDB'],
    [22, 'Munger', 'Munger', 'MUN'],
    [23, 'Muzaffarpur', 'Muzaffarpur', 'MUZ'],
    [24, 'Nalanda', 'Nalanda', 'NAL'],
    [25, 'Nawada', 'Nawada', 'NAW'],
    [26, 'Patna', 'Patna', 'PAT'],
    [27, 'Purnia', 'Purnia', 'PUR'],
    [28, 'Rohtas', 'Rohtas', 'ROH'],
    [29, 'Saharsa', 'Saharsa', 'SAH'],
    [30, 'Samastipur', 'Samastipur', 'SAM'],
    [31, 'Saran (Chhapra)', 'Saran', 'SAR'],
    [32, 'Sheikhpura', 'Sheikhpura', 'SHE'],
    [33, 'Sheohar', 'Sheohar', 'SHR'],
    [34, 'Sitamarhi', 'Sitamarhi', 'SIT'],
    [35, 'Siwan', 'Siwan', 'SIW'],
    [36, 'Supaul', 'Supaul', 'SUP'],
    [37, 'Vaishali (Hajipur)', 'Vaishali', 'VAI'],
    [38, 'West Champaran (Bettiah)', 'West Champaran', 'WES']
];

$sql = "-- ========================================================\n";
$sql .= "-- WELCOME BIHAR - PRODUCTION SEED DATA\n";
$sql .= "-- Compatible with phpMyAdmin, MySQL 8.0+, MariaDB 10.3+\n";
$sql .= "-- ========================================================\n\n";
$sql .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";

// 1. ROLES
$sql .= "-- 1. ROLES\n";
$sql .= "INSERT INTO `roles` (`id`, `code`, `name_en`, `name_hi`, `description`) VALUES\n";
$sql .= "(1, 'ADMIN', 'Administrator', 'Administrator', 'Full system access and settings management'),\n";
$sql .= "(2, 'DISTRIBUTOR', 'Distributor', 'Distributor', 'Manages assigned operators and permitted blocks'),\n";
$sql .= "(3, 'USER', 'Operator / User', 'Operator / User', 'Submits data applications for assigned locations')\n";
$sql .= "ON DUPLICATE KEY UPDATE `name_en`=VALUES(`name_en`);\n\n";

// 2. SETTINGS
$sql .= "-- 2. SYSTEM SETTINGS\n";
$sql .= "INSERT INTO `settings` (`setting_key`, `setting_value`, `setting_group`, `description`) VALUES\n";
$sql .= "('portal_name', 'Welcome Bihar', 'GENERAL', 'Portal display title'),\n";
$sql .= "('portal_tagline', 'Bihar Unified District & Block Citizen Services Data Portal', 'GENERAL', 'Portal subtitle'),\n";
$sql .= "('default_language', 'en', 'GENERAL', 'Default interface language (en)'),\n";
$sql .= "('distributor_review_required', '1', 'WORKFLOW', 'If 1, distributor review is mandatory before admin final approval'),\n";
$sql .= "('refund_on_rejection', '1', 'WORKFLOW', 'If 1, application fee is automatically refunded to wallet upon rejection'),\n";
$sql .= "('gateway_provider', 'ALL_API', 'PAYMENT', 'Active UPI QR provider service'),\n";
$sql .= "('allapi_mode', 'SANDBOX', 'PAYMENT', 'ALL API mode: SANDBOX or LIVE'),\n";
$sql .= "('allapi_base_url', 'https://allapi.in', 'PAYMENT', 'ALL API base URL'),\n";
$sql .= "('allapi_create_endpoint', '/order/create', 'PAYMENT', 'ALL API QR creation endpoint'),\n";
$sql .= "('allapi_status_endpoint', '/order/status', 'PAYMENT', 'ALL API status check endpoint'),\n";
$sql .= "('allapi_token', 'mock_dev_token', 'PAYMENT', 'Merchant connected token (keep in env in production)'),\n";
$sql .= "('allapi_redirect_url', 'http://localhost:8080/wallet/topup/callback', 'PAYMENT', 'User browser return URL'),\n";
$sql .= "('support_phone', '+91 1800-345-6262', 'SUPPORT', 'Toll-free citizen helpline'),\n";
$sql .= "('support_email', 'support@welcomebihar.gov.in', 'SUPPORT', 'Citizen support email'),\n";
$sql .= "('currency', 'INR', 'FINANCE', 'Currency code')\n";
$sql .= "ON DUPLICATE KEY UPDATE `setting_value`=VALUES(`setting_value`);\n\n";

// 3. DISTRICTS
$sql .= "-- 3. BIHAR DISTRICTS (All 38 Districts)\n";
$sql .= "INSERT INTO `districts` (`id`, `name`, `name_hi`, `code`, `is_active`) VALUES\n";
$dRows = [];
foreach ($districts as $d) {
    $dRows[] = "({$d[0]}, " . json_encode($d[1]) . ", " . json_encode($d[2]) . ", '{$d[3]}', 1)";
}
$sql .= implode(",\n", $dRows) . "\n";
$sql .= "ON DUPLICATE KEY UPDATE `name`=VALUES(`name`), `name_hi`=VALUES(`name_hi`);\n\n";

// 4. BLOCKS
$sql .= "-- 4. BIHAR BLOCKS (All 708 Administrative Blocks & Urban Bodies)\n";
$sql .= "INSERT INTO `blocks` (`id`, `district_id`, `name`, `name_hi`, `code`, `is_active`) VALUES\n";
$bRows = [];
foreach ($matches as $m) {
    $bId = (int)$m[1];
    $dId = (int)$m[2];
    $bName = $m[3];
    $bCode = $m[4];
    $bActive = (int)$m[5];
    // We provide name as name_hi as fallback or transliteration
    $bRows[] = "($bId, $dId, " . json_encode($bName) . ", " . json_encode($bName) . ", " . json_encode($bCode) . ", $bActive)";
}
$sql .= implode(",\n", $bRows) . "\n";
$sql .= "ON DUPLICATE KEY UPDATE `name`=VALUES(`name`), `code`=VALUES(`code`);\n\n";

// 5. 3 DATA TYPES
$sql .= "-- 5. CONFIGURABLE DATA TYPES (3 Initial Services)\n";
$sql .= "INSERT INTO `data_types` (`id`, `code`, `name_en`, `name_hi`, `description_en`, `description_hi`, `icon`, `fee_amount`, `distributor_commission`, `duplicate_check_field`, `is_active`, `sort_order`) VALUES\n";
$sql .= "(1, 'bocw_registration', 'BOCW Shramik Registration', 'BOCW Shramik Registration', 'Official Bihar Building & Construction Worker Registration under BOCW Board. Enables accidental insurance, educational assistance, and pension benefits.', 'Official Bihar Building & Construction Worker Registration under BOCW Board. Enables accidental insurance, educational assistance, and pension benefits.', 'bi-person-badge', 50.00, 10.00, 'aadhaar_no', 1, 1),\n";
$sql .= "(2, 'ration_verification', 'Ration Card Family Census', 'Ration Card Family Census', 'Food & Civil Supplies Department Family Census, member verification, and subsidized ration entitlement verification.', 'Food & Civil Supplies Department Family Census, member verification, and subsidized ration entitlement verification.', 'bi-card-checklist', 30.00, 5.00, 'ration_card_no', 1, 2),\n";
$sql .= "(3, 'rtps_assistance', 'RTPS Citizen Certificate Assistance', 'RTPS Citizen Certificate Assistance', 'Right to Public Services citizen certificates assistance (Income, Caste, Domicile/Residential, Non-Creamy Layer, EWS).', 'Right to Public Services citizen certificates assistance (Income, Caste, Domicile/Residential, Non-Creamy Layer, EWS).', 'bi-file-earmark-medical', 40.00, 8.00, 'aadhaar_no', 1, 3)\n";
$sql .= "ON DUPLICATE KEY UPDATE `name_en`=VALUES(`name_en`);\n\n";

// 6. DATA TYPE FIELDS
$sql .= "-- 6. DYNAMIC CONFIGURABLE FIELDS FOR EACH DATA TYPE\n";
$sql .= "INSERT INTO `data_type_fields` (`id`, `data_type_id`, `field_name`, `label_en`, `label_hi`, `field_type`, `placeholder_en`, `placeholder_hi`, `help_text_en`, `help_text_hi`, `options_json`, `is_required`, `is_unique`, `validation_pattern`, `sort_order`) VALUES\n";

// Fields for BOCW (data_type_id = 1)
$fields = [
    // BOCW
    [1, 1, 'applicant_name', 'Applicant Full Name', 'Applicant Full Name', 'text', 'Enter name as per Aadhaar', 'Enter name as per Aadhaar', 'Must match government identity card', 'Must match government identity card', null, 1, 0, null, 1],
    [2, 1, 'father_or_husband_name', 'Father / Husband Name', 'Father / Husband Name', 'text', 'Enter father or husband name', 'Enter father or husband name', null, null, null, 1, 0, null, 2],
    [3, 1, 'gender', 'Gender', 'Gender', 'select', null, null, null, null, json_encode([['value'=>'Male','label_en'=>'Male','label_hi'=>'Male'],['value'=>'Female','label_en'=>'Female','label_hi'=>'Female'],['value'=>'Other','label_en'=>'Other','label_hi'=>'Other']]), 1, 0, null, 3],
    [4, 1, 'dob', 'Date of Birth', 'Date of Birth', 'date', null, null, 'Must be at least 18 years old', 'Must be at least 18 years old', null, 1, 0, null, 4],
    [5, 1, 'mobile_no', 'Mobile Number', 'Mobile Number', 'tel', '10-digit mobile number', '10-digit mobile number', 'Used for SMS alerts and status updates', 'Used for SMS alerts and status updates', null, 1, 0, '^[6-9][0-9]{9}$', 5],
    [6, 1, 'aadhaar_no', 'Aadhaar Number', 'Aadhaar Number', 'text', '12-digit Aadhaar number', '12-digit Aadhaar number', 'Unique identification number', 'Unique identification number', null, 1, 1, '^[0-9]{12}$', 6],
    [7, 1, 'work_nature', 'Nature of Construction Work', 'Nature of Construction Work', 'select', null, null, null, null, json_encode([['value'=>'Mason','label_en'=>'Mason','label_hi'=>'Mason'],['value'=>'Carpenter','label_en'=>'Carpenter','label_hi'=>'Carpenter'],['value'=>'Electrician','label_en'=>'Electrician','label_hi'=>'Electrician'],['value'=>'Plumber','label_en'=>'Plumber','label_hi'=>'Plumber'],['value'=>'Construction_Labour','label_en'=>'Construction Helper','label_hi'=>'Construction Helper'],['value'=>'Painter','label_en'=>'Painter','label_hi'=>'Painter'],['value'=>'Tile_Worker','label_en'=>'Tiles / Marble Worker','label_hi'=>'Tiles / Marble Worker'],['value'=>'Other','label_en'=>'Other Skilled Worker','label_hi'=>'Other Skilled Worker']]), 1, 0, null, 7],
    [8, 1, 'working_days_90', 'Completed 90 Days Work in Last 12 Months', 'Completed 90 Days Work in Last 12 Months', 'select', null, null, 'Mandatory requirement under BOCW rules', 'Mandatory requirement under BOCW rules', json_encode([['value'=>'Yes','label_en'=>'Yes','label_hi'=>'Yes'],['value'=>'No','label_en'=>'No','label_hi'=>'No']]), 1, 0, null, 8],
    [9, 1, 'bank_account_no', 'Bank Account Number', 'Bank Account Number', 'text', 'Enter bank account number', 'Enter bank account number', 'Account must be Aadhaar linked (DBT)', 'Account must be Aadhaar linked (DBT)', null, 1, 0, '^[0-9]{9,18}$', 9],
    [10, 1, 'bank_ifsc', 'Bank IFSC Code', 'Bank IFSC Code', 'text', 'e.g. SBIN0001234', 'e.g. SBIN0001234', '11-character alphanumeric code', '11-character alphanumeric code', null, 1, 0, '^[A-Z]{4}0[A-Z0-9]{6}$', 10],
    [11, 1, 'present_address', 'Village / Ward / Full Address', 'Village / Ward / Full Address', 'textarea', 'Complete residential address', 'Complete residential address', null, null, null, 1, 0, null, 11],

    // Ration Card Census (data_type_id = 2)
    [12, 2, 'head_of_family', 'Head of Family Name', 'Head of Family Name', 'text', 'Name of family head', 'Name of family head', null, null, null, 1, 0, null, 1],
    [13, 2, 'ration_card_no', 'Ration Card Number', 'Ration Card Number', 'text', 'Enter Ration Card Number', 'Enter Ration Card Number', 'Existing ration card number', 'Existing ration card number', null, 1, 1, '^[A-Za-z0-9]{8,25}$', 2],
    [14, 2, 'mobile_no', 'Contact Mobile Number', 'Contact Mobile Number', 'tel', '10-digit mobile number', '10-digit mobile number', null, null, null, 1, 0, '^[6-9][0-9]{9}$', 3],
    [15, 2, 'aadhaar_no', 'Head Aadhaar Number', 'Head Aadhaar Number', 'text', '12-digit Aadhaar number', '12-digit Aadhaar number', null, null, null, 1, 0, '^[0-9]{12}$', 4],
    [16, 2, 'total_members', 'Total Family Members Count', 'Total Family Members Count', 'number', 'e.g. 5', 'e.g. 5', 'Number of members living together', 'Number of members living together', null, 1, 0, '^[1-9][0-9]?$', 5],
    [17, 2, 'card_category', 'Card Category', 'Card Category', 'select', null, null, null, null, json_encode([['value'=>'PHH','label_en'=>'PHH (Priority Household)','label_hi'=>'PHH (Priority Household)'],['value'=>'AAY','label_en'=>'AAY (Antyodaya Anna Yojana)','label_hi'=>'AAY (Antyodaya Anna Yojana)'],['value'=>'BPL','label_en'=>'BPL','label_hi'=>'BPL']]), 1, 0, null, 6],
    [18, 2, 'fps_dealer_name', 'Fair Price Shop (Dealer) Name', 'Fair Price Shop (Dealer) Name', 'text', 'Enter ration dealer name / shop ID', 'Enter ration dealer name / shop ID', null, null, null, 0, 0, null, 7],
    [19, 2, 'village_ward', 'Village / Ward / Mohalla', 'Village / Ward / Mohalla', 'text', 'e.g. Ward No. 04, Rampur', 'e.g. Ward No. 04, Rampur', null, null, null, 1, 0, null, 8],

    // RTPS Assistance (data_type_id = 3)
    [20, 3, 'applicant_name', 'Applicant Name', 'Applicant Name', 'text', 'Full name as per official records', 'Full name as per official records', null, null, null, 1, 0, null, 1],
    [21, 3, 'certificate_type', 'Required Certificate Type', 'Required Certificate Type', 'select', null, null, null, null, json_encode([['value'=>'Income','label_en'=>'Income Certificate','label_hi'=>'Income Certificate'],['value'=>'Caste','label_en'=>'Caste Certificate','label_hi'=>'Caste Certificate'],['value'=>'Residential','label_en'=>'Residential / Domicile Certificate','label_hi'=>'Residential / Domicile Certificate'],['value'=>'NCL','label_en'=>'Non-Creamy Layer (OBC/EBC)','label_hi'=>'Non-Creamy Layer (OBC/EBC)'],['value'=>'EWS','label_en'=>'Economically Weaker Section (EWS)','label_hi'=>'Economically Weaker Section (EWS)']]), 1, 0, null, 2],
    [22, 3, 'mobile_no', 'Mobile Number', 'Mobile Number', 'tel', '10-digit mobile number', '10-digit mobile number', 'For ServicePlus RTPS SMS dispatch', 'For ServicePlus RTPS SMS dispatch', null, 1, 0, '^[6-9][0-9]{9}$', 3],
    [23, 3, 'aadhaar_no', 'Aadhaar Number', 'Aadhaar Number', 'text', '12-digit Aadhaar number', '12-digit Aadhaar number', null, null, null, 1, 0, '^[0-9]{12}$', 4],
    [24, 3, 'annual_income', 'Estimated Annual Family Income (INR)', 'Estimated Annual Family Income (INR)', 'number', 'e.g. 90000', 'e.g. 90000', 'Required for Income / EWS / NCL certificates', 'Required for Income / EWS / NCL certificates', null, 0, 0, null, 5],
    [25, 3, 'purpose', 'Purpose of Certificate', 'Purpose of Certificate', 'text', 'e.g. Higher Education / Scholarship / Government Job', 'e.g. Higher Education / Scholarship / Government Job', null, null, null, 1, 0, null, 6],
    [26, 3, 'target_officer_level', 'Issuing Authority Level', 'Issuing Authority Level', 'select', null, null, null, null, json_encode([['value'=>'RO_CO','label_en'=>'Revenue Officer (CO/RO)','label_hi'=>'Revenue Officer (CO/RO)'],['value'=>'SDO','label_en'=>'Sub-Divisional Officer (SDO)','label_hi'=>'Sub-Divisional Officer (SDO)'],['value'=>'DM','label_en'=>'District Magistrate (DM)','label_hi'=>'District Magistrate (DM)']]), 1, 0, null, 7]
];

$fRows = [];
foreach ($fields as $f) {
    $opt = $f[10] ? json_encode($f[10]) : 'NULL';
    $fRows[] = "({$f[0]}, {$f[1]}, " . json_encode($f[2]) . ", " . json_encode($f[3]) . ", " . json_encode($f[4]) . ", '{$f[5]}', " .
               json_encode($f[6]) . ", " . json_encode($f[7]) . ", " . json_encode($f[8]) . ", " . json_encode($f[9]) . ", $opt, {$f[11]}, {$f[12]}, " .
               json_encode($f[13]) . ", {$f[14]})";
}
$sql .= implode(",\n", $fRows) . "\n";
$sql .= "ON DUPLICATE KEY UPDATE `label_en`=VALUES(`label_en`);\n\n";

// 7. DEFAULT DEMO USERS (Bcrypt Passwords)
// Password for all three demo accounts: Welcome@2026
// Admin: admin@welcomebihar.gov.in / Welcome@2026
// Distributor: distributor@welcomebihar.gov.in / Welcome@2026
// User (Operator): operator@welcomebihar.gov.in / Welcome@2026
$demoHash = password_hash('Welcome@2026', PASSWORD_BCRYPT);

$sql .= "-- 7. SEED USERS & WALLETS (Demo Credentials: Login with password 'Welcome@2026')\n";
$sql .= "INSERT INTO `users` (`id`, `uuid`, `role`, `name`, `mobile`, `email`, `password_hash`, `status`, `parent_distributor_id`, `preferred_lang`, `center_name`, `assigned_all_districts`) VALUES\n";
$sql .= "(1, '11111111-1111-1111-1111-111111111111', 'ADMIN', 'Super Admin', '9999900001', 'admin@welcomebihar.gov.in', '$demoHash', 'ACTIVE', NULL, 'en', 'State Headquarters Patna', 1),\n";
$sql .= "(2, '22222222-2222-2222-2222-222222222222', 'DISTRIBUTOR', 'Patna Central Distributor', '9999900002', 'distributor@welcomebihar.gov.in', '$demoHash', 'ACTIVE', NULL, 'en', 'Patna Central Hub', 0),\n";
$sql .= "(3, '33333333-3333-3333-3333-333333333333', 'USER', 'Kiosk Operator Rajesh', '9999900003', 'operator@welcomebihar.gov.in', '$demoHash', 'ACTIVE', 2, 'en', 'Rajesh CSC Center Danapur', 0)\n";
$sql .= "ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);\n\n";

// 8. WALLETS FOR SEED USERS
$sql .= "-- 8. WALLETS INITIALIZATION\n";
$sql .= "INSERT INTO `wallets` (`id`, `user_id`, `balance`, `total_credited`, `total_debited`) VALUES\n";
$sql .= "(1, 1, 0.00, 0.00, 0.00),\n";
$sql .= "(2, 2, 500.00, 500.00, 0.00),\n";
$sql .= "(3, 3, 1000.00, 1000.00, 0.00)\n";
$sql .= "ON DUPLICATE KEY UPDATE `balance`=VALUES(`balance`);\n\n";

// 9. INITIAL SEED WALLET TRANSACTIONS
$sql .= "-- 9. INITIAL IMMUTABLE WALLET LEDGER ENTRIES\n";
$sql .= "INSERT INTO `wallet_transactions` (`id`, `txn_ref`, `user_id`, `wallet_id`, `type`, `direction`, `amount`, `balance_before`, `balance_after`, `status`, `reference_type`, `reference_id`, `idempotency_key`, `description_en`, `description_hi`, `remarks`, `created_by_user_id`) VALUES\n";
$sql .= "(1, 'TXN-INIT-DIST-500', 2, 2, 'ADMIN_ADJUSTMENT', 'CREDIT', 500.00, 0.00, 500.00, 'SUCCESS', 'ADMIN_MANUAL', 'SEED_INIT_DIST', 'INIT_SEED_WALLET_DIST_002', 'Welcome opening credit by administration', 'Welcome opening credit by administration', 'Initial demo balance allocated', 1),\n";
$sql .= "(2, 'TXN-INIT-USER-1000', 3, 3, 'ADMIN_ADJUSTMENT', 'CREDIT', 1000.00, 0.00, 1000.00, 'SUCCESS', 'ADMIN_MANUAL', 'SEED_INIT_USER', 'INIT_SEED_WALLET_USER_003', 'Welcome opening credit for test operations', 'Welcome opening credit for test operations', 'Initial demo balance allocated', 1)\n";
$sql .= "ON DUPLICATE KEY UPDATE `amount`=VALUES(`amount`);\n\n";

// 10. LOCATION PERMISSIONS FOR DEMO DISTRIBUTOR & USER
// District 26 is Patna. Danapur block ID is 465 (or let's assign Patna district).
$sql .= "-- 10. DEMO LOCATION PERMISSIONS\n";
$sql .= "-- Distributor permitted for Patna (id: 26) and Nalanda (id: 24)\n";
$sql .= "INSERT INTO `location_permissions` (`user_id`, `district_id`, `block_id`) VALUES\n";
$sql .= "(2, 26, NULL),\n"; // Patna all blocks
$sql .= "(2, 24, NULL),\n"; // Nalanda all blocks
$sql .= "(3, 26, NULL)\n"; // User permitted for Patna
$sql .= "ON DUPLICATE KEY UPDATE `district_id`=VALUES(`district_id`);\n\n";

$sql .= "SET FOREIGN_KEY_CHECKS = 1;\n";

file_put_contents('F:\\code\\welcome bihar\\database\\seed.sql', $sql);
echo "Successfully generated database/seed.sql (" . strlen($sql) . " bytes)\n";
