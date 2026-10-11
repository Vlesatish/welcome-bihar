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
    [1, 'Araria', 'अररिया', 'ARA'],
    [2, 'Arwal', 'अरवल', 'ARW'],
    [3, 'Aurangabad', 'औरंगाबाद', 'AUR'],
    [4, 'Banka', 'बांका', 'BAN'],
    [5, 'Begusarai', 'बेगूसराय', 'BEG'],
    [6, 'Bhagalpur', 'भागलपुर', 'BHA'],
    [7, 'Bhojpur', 'भोजपुर', 'BHO'],
    [8, 'Buxar', 'बक्सर', 'BUX'],
    [9, 'Darbhanga', 'दरभंगा', 'DAR'],
    [10, 'East Champaran (Motihari)', 'पूर्वी चंपारण (मोतिहारी)', 'EAS'],
    [11, 'Gaya', 'गया', 'GAY'],
    [12, 'Gopalganj', 'गोपालगंज', 'GOP'],
    [13, 'Jamui', 'जमुई', 'JAM'],
    [14, 'Jehanabad', 'जहानाबाद', 'JEH'],
    [15, 'Kaimur (Bhabua)', 'कैमूर (भभुआ)', 'KAI'],
    [16, 'Katihar', 'कटिहार', 'KAT'],
    [17, 'Khagaria', 'खगड़िया', 'KHA'],
    [18, 'Kishanganj', 'किशनगंज', 'KIS'],
    [19, 'Lakhisarai', 'लखीसराय', 'LAK'],
    [20, 'Madhepura', 'मधेपुरा', 'MAD'],
    [21, 'Madhubani', 'मधुबनी', 'MDB'],
    [22, 'Munger', 'मुंगेर', 'MUN'],
    [23, 'Muzaffarpur', 'मुजफ्फरपुर', 'MUZ'],
    [24, 'Nalanda', 'नालंदा', 'NAL'],
    [25, 'Nawada', 'नवादा', 'NAW'],
    [26, 'Patna', 'पटना', 'PAT'],
    [27, 'Purnia', 'पूर्णिया', 'PUR'],
    [28, 'Rohtas', 'रोहतास', 'ROH'],
    [29, 'Saharsa', 'सहरसा', 'SAH'],
    [30, 'Samastipur', 'समस्तीपुर', 'SAM'],
    [31, 'Saran (Chhapra)', 'सारण (छपरा)', 'SAR'],
    [32, 'Sheikhpura', 'शेखपुरा', 'SHE'],
    [33, 'Sheohar', 'शिवहर', 'SHR'],
    [34, 'Sitamarhi', 'सीतामढ़ी', 'SIT'],
    [35, 'Siwan', 'सीवान', 'SIW'],
    [36, 'Supaul', 'सुपौल', 'SUP'],
    [37, 'Vaishali (Hajipur)', 'वैशाली (हाजीपुर)', 'VAI'],
    [38, 'West Champaran (Bettiah)', 'पश्चिम चंपारण (बेतिया)', 'WES']
];

$sql = "-- ========================================================\n";
$sql .= "-- WELCOME BIHAR - PRODUCTION SEED DATA\n";
$sql .= "-- Compatible with phpMyAdmin, MySQL 8.0+, MariaDB 10.3+\n";
$sql .= "-- ========================================================\n\n";
$sql .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";

// 1. ROLES
$sql .= "-- 1. ROLES\n";
$sql .= "INSERT INTO `roles` (`id`, `code`, `name_en`, `name_hi`, `description`) VALUES\n";
$sql .= "(1, 'ADMIN', 'Administrator', 'प्रशासक', 'Full system access and settings management'),\n";
$sql .= "(2, 'DISTRIBUTOR', 'Distributor', 'वितरक / डिस्ट्रीब्यूटर', 'Manages assigned operators and permitted blocks'),\n";
$sql .= "(3, 'USER', 'Operator / User', 'उपयोगकर्ता / ऑपरेटर', 'Submits data applications for assigned locations')\n";
$sql .= "ON DUPLICATE KEY UPDATE `name_en`=VALUES(`name_en`);\n\n";

// 2. SETTINGS
$sql .= "-- 2. SYSTEM SETTINGS\n";
$sql .= "INSERT INTO `settings` (`setting_key`, `setting_value`, `setting_group`, `description`) VALUES\n";
$sql .= "('portal_name', 'Welcome Bihar / वेलकम बिहार', 'GENERAL', 'Portal display title'),\n";
$sql .= "('portal_tagline', 'Bihar Unified District & Block Citizen Services Data Portal', 'GENERAL', 'Portal subtitle'),\n";
$sql .= "('default_language', 'hi', 'GENERAL', 'Default interface language (hi/en)'),\n";
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
$sql .= "(1, 'bocw_registration', 'BOCW Shramik Registration', 'बिहार भवन एवं सन्निर्माण कर्मकार निबंधन', 'Official Bihar Building & Construction Worker Registration under BOCW Board. Enables accidental insurance, educational assistance, and pension benefits.', 'बिहार भवन एवं अन्य सन्निर्माण कर्मकार कल्याण बोर्ड के अंतर्गत असंगठित निर्माण श्रमिकों का आधिकारिक निबंधन।', 'bi-person-badge', 50.00, 10.00, 'aadhaar_no', 1, 1),\n";
$sql .= "(2, 'ration_verification', 'Ration Card Family Census', 'राशन कार्ड परिवार विवरण सत्यापन', 'Food & Civil Supplies Department Family Census, member verification, and subsidized ration entitlement verification.', 'खाद्य एवं उपभोक्ता संरक्षण विभाग के अंतर्गत राशन कार्ड परिवार सदस्यों का सत्यापन एवं खाद्यान्न पात्रता जांच।', 'bi-card-checklist', 30.00, 5.00, 'ration_card_no', 1, 2),\n";
$sql .= "(3, 'rtps_assistance', 'RTPS Citizen Certificate Assistance', 'आरटीपीएस नागरिक सेवा सहायता', 'Right to Public Services citizen certificates assistance (Income, Caste, Domicile/Residential, Non-Creamy Layer, EWS).', 'लोक सेवाओं का अधिकार (RTPS) के अंतर्गत जाति, आय, निवास, ईडब्ल्यूएस एवं क्रीमीलेयर रहित प्रमाण पत्र सहायता।', 'bi-file-earmark-medical', 40.00, 8.00, 'aadhaar_no', 1, 3)\n";
$sql .= "ON DUPLICATE KEY UPDATE `name_en`=VALUES(`name_en`);\n\n";

// 6. DATA TYPE FIELDS
$sql .= "-- 6. DYNAMIC CONFIGURABLE FIELDS FOR EACH DATA TYPE\n";
$sql .= "INSERT INTO `data_type_fields` (`id`, `data_type_id`, `field_name`, `label_en`, `label_hi`, `field_type`, `placeholder_en`, `placeholder_hi`, `help_text_en`, `help_text_hi`, `options_json`, `is_required`, `is_unique`, `validation_pattern`, `sort_order`) VALUES\n";

// Fields for BOCW (data_type_id = 1)
$fields = [
    // BOCW
    [1, 1, 'applicant_name', 'Applicant Full Name', 'आवेदक का पूरा नाम', 'text', 'Enter name as per Aadhaar', 'आधार अनुसार नाम दर्ज करें', 'Must match government identity card', 'नाम आधार कार्ड के अनुसार होना चाहिए', null, 1, 0, null, 1],
    [2, 1, 'father_or_husband_name', 'Father / Husband Name', 'पिता / पति का नाम', 'text', 'Enter father or husband name', 'पिता अथवा पति का नाम दर्ज करें', null, null, null, 1, 0, null, 2],
    [3, 1, 'gender', 'Gender', 'लिंग', 'select', null, null, null, null, json_encode([['value'=>'Male','label_en'=>'Male','label_hi'=>'पुरुष'],['value'=>'Female','label_en'=>'Female','label_hi'=>'महिला'],['value'=>'Other','label_en'=>'Other','label_hi'=>'अन्य']]), 1, 0, null, 3],
    [4, 1, 'dob', 'Date of Birth', 'जन्म तिथि', 'date', null, null, 'Must be at least 18 years old', 'न्यूनतम आयु 18 वर्ष होनी चाहिए', null, 1, 0, null, 4],
    [5, 1, 'mobile_no', 'Mobile Number', 'मोबाइल नंबर', 'tel', '10-digit mobile number', '10 अंकों का मोबाइल नंबर', 'Used for SMS alerts and status updates', 'एसएमएस सूचनाओं हेतु सक्रिय नंबर दें', null, 1, 0, '^[6-9][0-9]{9}$', 5],
    [6, 1, 'aadhaar_no', 'Aadhaar Number', 'आधार संख्या', 'text', '12-digit Aadhaar number', '12 अंकों की आधार संख्या', 'Unique identification number', 'अनिवार्य विशिष्ट पहचान संख्या', null, 1, 1, '^[0-9]{12}$', 6],
    [7, 1, 'work_nature', 'Nature of Construction Work', 'कार्य की प्रकृति', 'select', null, null, null, null, json_encode([['value'=>'Mason','label_en'=>'Mason / राजमिस्त्री','label_hi'=>'राजमिस्त्री'],['value'=>'Carpenter','label_en'=>'Carpenter / बढ़ई','label_hi'=>'बढ़ई'],['value'=>'Electrician','label_en'=>'Electrician / इलेक्ट्रीशियन','label_hi'=>'इलेक्ट्रीशियन'],['value'=>'Plumber','label_en'=>'Plumber / प्लंबर','label_hi'=>'प्लंबर'],['value'=>'Construction_Labour','label_en'=>'Construction Helper / निर्माण मजदूर','label_hi'=>'निर्माण मजदूर'],['value'=>'Painter','label_en'=>'Painter / पेंटर','label_hi'=>'पेंटर'],['value'=>'Tile_Worker','label_en'=>'Tiles / Marble Worker / टाइल्स मिस्त्री','label_hi'=>'टाइल्स मिस्त्री'],['value'=>'Other','label_en'=>'Other Skilled Worker / अन्य कुशल श्रमिक','label_hi'=>'अन्य']]), 1, 0, null, 7],
    [8, 1, 'working_days_90', 'Completed 90 Days Work in Last 12 Months', 'पिछले 12 माह में 90 दिन कार्य पूरा किया', 'select', null, null, 'Mandatory requirement under BOCW rules', 'बीओसीडब्ल्यू अधिनियम अनुसार अनिवार्य', json_encode([['value'=>'Yes','label_en'=>'Yes / हाँ','label_hi'=>'हाँ'],['value'=>'No','label_en'=>'No / नहीं','label_hi'=>'नहीं']]), 1, 0, null, 8],
    [9, 1, 'bank_account_no', 'Bank Account Number', 'बैंक खाता संख्या', 'text', 'Enter bank account number', 'बैंक खाता संख्या दर्ज करें', 'Account must be Aadhaar linked (DBT)', 'खाता आधार से जुड़ा (DBT सक्रिय) होना चाहिए', null, 1, 0, '^[0-9]{9,18}$', 9],
    [10, 1, 'bank_ifsc', 'Bank IFSC Code', 'आईएफएससी कोड (IFSC)', 'text', 'e.g. SBIN0001234', 'उदा. SBIN0001234', '11-character alphanumeric code', '11 अक्षरों का बैंक कोड', null, 1, 0, '^[A-Z]{4}0[A-Z0-9]{6}$', 10],
    [11, 1, 'present_address', 'Village / Ward / Full Address', 'ग्राम / वार्ड / पूरा पता', 'textarea', 'Complete residential address', 'स्थानीय निवास का पूरा पता लिखें', null, null, null, 1, 0, null, 11],

    // Ration Card Census (data_type_id = 2)
    [12, 2, 'head_of_family', 'Head of Family Name', 'परिवार के मुखिया का नाम', 'text', 'Name of family head', 'मुखिया का नाम दर्ज करें', null, null, null, 1, 0, null, 1],
    [13, 2, 'ration_card_no', 'Ration Card Number', 'राशन कार्ड संख्या', 'text', 'Enter Ration Card Number', 'राशन कार्ड संख्या दर्ज करें', 'Existing ration card number', 'मौजूदा राशन कार्ड नंबर', null, 1, 1, '^[A-Za-z0-9]{8,25}$', 2],
    [14, 2, 'mobile_no', 'Contact Mobile Number', 'संपर्क मोबाइल नंबर', 'tel', '10-digit mobile number', '10 अंकों का मोबाइल नंबर', null, null, null, 1, 0, '^[6-9][0-9]{9}$', 3],
    [15, 2, 'aadhaar_no', 'Head Aadhaar Number', 'मुखिया का आधार नंबर', 'text', '12-digit Aadhaar number', '12 अंकों का आधार नंबर', null, null, null, 1, 0, '^[0-9]{12}$', 4],
    [16, 2, 'total_members', 'Total Family Members Count', 'कुल परिवार सदस्य संख्या', 'number', 'e.g. 5', 'उदा. 5', 'Number of members living together', 'परिवार के कुल सदस्यों की संख्या', null, 1, 0, '^[1-9][0-9]?$', 5],
    [17, 2, 'card_category', 'Card Category', 'राशन कार्ड श्रेणी', 'select', null, null, null, null, json_encode([['value'=>'PHH','label_en'=>'PHH (Priority Household) / प्राथमिकता गृहस्थी','label_hi'=>'प्राथमिकता गृहस्थी (PHH)'],['value'=>'AAY','label_en'=>'AAY (Antyodaya Anna Yojana) / अंत्योदय','label_hi'=>'अंत्योदय (AAY)'],['value'=>'BPL','label_en'=>'BPL / बीपीएल','label_hi'=>'बीपीएल']]), 1, 0, null, 6],
    [18, 2, 'fps_dealer_name', 'Fair Price Shop (Dealer) Name', 'उचित मूल्य विक्रेता (डीलर) का नाम', 'text', 'Enter ration dealer name / shop ID', 'डीलर का नाम अथवा दुकान संख्या', null, null, null, 0, 0, null, 7],
    [19, 2, 'village_ward', 'Village / Ward / Mohalla', 'ग्राम / वार्ड / मोहल्ला', 'text', 'e.g. Ward No. 04, Rampur', 'उदा. वार्ड सं. 04, रामपुर', null, null, null, 1, 0, null, 8],

    // RTPS Assistance (data_type_id = 3)
    [20, 3, 'applicant_name', 'Applicant Name', 'आवेदक का नाम', 'text', 'Full name as per official records', 'दस्तावेजों अनुसार पूरा नाम', null, null, null, 1, 0, null, 1],
    [21, 3, 'certificate_type', 'Required Certificate Type', 'अपेक्षित प्रमाण पत्र प्रकार', 'select', null, null, null, null, json_encode([['value'=>'Income','label_en'=>'Income Certificate / आय प्रमाण पत्र','label_hi'=>'आय प्रमाण पत्र'],['value'=>'Caste','label_en'=>'Caste Certificate / जाति प्रमाण पत्र','label_hi'=>'जाति प्रमाण पत्र'],['value'=>'Residential','label_en'=>'Residential / Domicile / निवास प्रमाण पत्र','label_hi'=>'निवास प्रमाण पत्र'],['value'=>'NCL','label_en'=>'Non-Creamy Layer (OBC/EBC) / क्रीमीलेयर रहित','label_hi'=>'क्रीमीलेयर रहित प्रमाण पत्र'],['value'=>'EWS','label_en'=>'Economically Weaker Section (EWS) / ईडब्ल्यूएस','label_hi'=>'ईडब्ल्यूएस प्रमाण पत्र']]), 1, 0, null, 2],
    [22, 3, 'mobile_no', 'Mobile Number', 'मोबाइल नंबर', 'tel', '10-digit mobile number', '10 अंकों का मोबाइल', 'For ServicePlus RTPS SMS dispatch', 'आरटीपीएस सेवा एसएमएस हेतु', null, 1, 0, '^[6-9][0-9]{9}$', 3],
    [23, 3, 'aadhaar_no', 'Aadhaar Number', 'आधार संख्या', 'text', '12-digit Aadhaar number', '12 अंकों का आधार', null, null, null, 1, 0, '^[0-9]{12}$', 4],
    [24, 3, 'annual_income', 'Estimated Annual Family Income (INR)', 'वार्षिक पारिवारिक अनुमानित आय (रु.)', 'number', 'e.g. 90000', 'उदा. 90000', 'Required for Income / EWS / NCL certificates', 'आय/ईडब्ल्यूएस प्रमाण पत्र हेतु आवश्यक', null, 0, 0, null, 5],
    [25, 3, 'purpose', 'Purpose of Certificate', 'आवेदन का उद्देश्य', 'text', 'e.g. Higher Education / Scholarship / Government Job', 'उदा. उच्च शिक्षा / छात्रवृत्ति / सरकारी नौकरी', null, null, null, 1, 0, null, 6],
    [26, 3, 'target_officer_level', 'Issuing Authority Level', 'जारीकर्ता स्तर', 'select', null, null, null, null, json_encode([['value'=>'RO_CO','label_en'=>'Revenue Officer (CO/RO) / अंचल स्तर','label_hi'=>'अंचल अधिकारी (CO/RO) स्तर'],['value'=>'SDO','label_en'=>'Sub-Divisional Officer (SDO) / अनुमंडल स्तर','label_hi'=>'अनुमंडल पदाधिकारी (SDO) स्तर'],['value'=>'DM','label_en'=>'District Magistrate (DM) / जिला स्तर','label_hi'=>'जिला पदाधिकारी (DM) स्तर']]), 1, 0, null, 7]
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
$sql .= "(1, '11111111-1111-1111-1111-111111111111', 'ADMIN', 'Super Admin (बिहार प्रशासक)', '9999900001', 'admin@welcomebihar.gov.in', '$demoHash', 'ACTIVE', NULL, 'hi', 'State Headquarters Patna', 1),\n";
$sql .= "(2, '22222222-2222-2222-2222-222222222222', 'DISTRIBUTOR', 'Patna Central Distributor (पटना वितरक)', '9999900002', 'distributor@welcomebihar.gov.in', '$demoHash', 'ACTIVE', NULL, 'hi', 'Patna Central Hub', 0),\n";
$sql .= "(3, '33333333-3333-3333-3333-333333333333', 'USER', 'Kiosk Operator Rajesh (किओस्क संचालक राजेश)', '9999900003', 'operator@welcomebihar.gov.in', '$demoHash', 'ACTIVE', 2, 'hi', 'Rajesh CSC Center Danapur', 0)\n";
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
$sql .= "(1, 'TXN-INIT-DIST-500', 2, 2, 'ADMIN_ADJUSTMENT', 'CREDIT', 500.00, 0.00, 500.00, 'SUCCESS', 'ADMIN_MANUAL', 'SEED_INIT_DIST', 'INIT_SEED_WALLET_DIST_002', 'Welcome opening credit by administration', 'प्रशासन द्वारा प्रारंभिक वॉलेट क्रेडिट', 'Initial demo balance allocated', 1),\n";
$sql .= "(2, 'TXN-INIT-USER-1000', 3, 3, 'ADMIN_ADJUSTMENT', 'CREDIT', 1000.00, 0.00, 1000.00, 'SUCCESS', 'ADMIN_MANUAL', 'SEED_INIT_USER', 'INIT_SEED_WALLET_USER_003', 'Welcome opening credit for test operations', 'परीक्षण कार्यों हेतु प्रारंभिक वॉलेट जमा', 'Initial demo balance allocated', 1)\n";
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
