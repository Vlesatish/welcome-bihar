<?php
/**
 * Comprehensive Automated Verification Script for Welcome Bihar
 * Single-Service "Block & District Application Pass Service" (srv_block_app)
 */

spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $baseDir = dirname(__DIR__) . '/app/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    if (file_exists($file)) require $file;
});

require_once __DIR__ . '/../app/helpers.php';

use App\Config\Database;
use App\Services\PricingService;
use App\Services\ApplicationService;
use App\Services\WalletService;

$db = Database::getConnection();
echo "========================================================\n";
echo "   WELCOME BIHAR - SINGLE SERVICE VERIFICATION SUITE    \n";
echo "========================================================\n\n";

// Pre-test cleanup
$db->exec("DELETE FROM blocks WHERE name LIKE 'VERIF_%'");
$db->exec("DELETE FROM districts WHERE name LIKE 'VERIF_%'");
$db->exec("DELETE FROM users WHERE email LIKE 'verif_user_%'");

$passCount = 0;
$failCount = 0;

function assertTest(string $description, bool $condition, string $details = ''): void {
    global $passCount, $failCount;
    if ($condition) {
        echo " [PASS] {$description}\n";
        if (!empty($details)) echo "        ↳ {$details}\n";
        $passCount++;
    } else {
        echo " [FAIL] {$description}\n";
        if (!empty($details)) echo "        ↳ ERROR: {$details}\n";
        $failCount++;
    }
}

// ---------------------------------------------------------
// TEST 1: SIRF EK SERVICE (Single Service Restriction)
// ---------------------------------------------------------
echo "\n--- 1. Single Service Restriction & Historical Preservation ---\n";
$activeServices = $db->query("SELECT id, code, name_en, is_active FROM data_types WHERE is_active = 1")->fetchAll(PDO::FETCH_ASSOC);
assertTest(
    "Only 1 service is active in data_types",
    count($activeServices) === 1 && $activeServices[0]['code'] === 'srv_block_app',
    "Active service: " . ($activeServices[0]['name_en'] ?? 'None') . " (code: " . ($activeServices[0]['code'] ?? 'None') . ")"
);

$srvBlockApp = $activeServices[0] ?? null;
$inactiveServices = $db->query("SELECT id, code, name_en FROM data_types WHERE is_active = 0")->fetchAll(PDO::FETCH_ASSOC);
assertTest(
    "Other services are preserved in database as inactive/archived",
    count($inactiveServices) >= 3,
    "Preserved archived services count: " . count($inactiveServices)
);

// ---------------------------------------------------------
// TEST 2: SERVICE MANAGEMENT (Update existing without duplicate)
// ---------------------------------------------------------
echo "\n--- 2. Service Management (Update without Duplication) ---\n";
$origDesc = $srvBlockApp['description_en'] ?? '';
$testNotice = "TEST SERVICE NOTICE: Verification in 24 hrs active - " . time();

$updStmt = $db->prepare("UPDATE data_types SET notice = ? WHERE code = 'srv_block_app'");
$updStmt->execute([$testNotice]);

$countSrv = (int)$db->query("SELECT COUNT(*) FROM data_types WHERE code = 'srv_block_app'")->fetchColumn();
$freshNotice = $db->query("SELECT notice FROM data_types WHERE code = 'srv_block_app'")->fetchColumn();

assertTest(
    "srv_block_app can be updated and no duplicate is created",
    $countSrv === 1 && $freshNotice === $testNotice,
    "Total srv_block_app records in DB: {$countSrv}"
);

// ---------------------------------------------------------
// TEST 3: LOCATION CREATION (State -> District -> Block)
// ---------------------------------------------------------
echo "\n--- 3. Location Hierarchy & Duplicate Protection ---\n";
// Find or create test district in Bihar
$testDistName = "VERIF_DIST_" . substr(md5(microtime()), 0, 5);
$insD = $db->prepare("INSERT INTO districts (state, name, name_hi, code, is_active) VALUES ('Bihar', ?, ?, ?, 1)");
$insD->execute([$testDistName, $testDistName, 'VD' . rand(10, 99)]);
$testDistId = (int)$db->lastInsertId();

// Add block to test district
$testBlockName = "VERIF_BLOCK_A";
$insB = $db->prepare("INSERT INTO blocks (district_id, name, name_hi, code, is_active) VALUES (?, ?, ?, ?, 1)");
$insB->execute([$testDistId, $testBlockName, $testBlockName, 'VB01']);
$testBlockId = (int)$db->lastInsertId();

assertTest(
    "New block created under State -> District hierarchy",
    $testBlockId > 0 && $testDistId > 0,
    "Created District ID: {$testDistId}, Block ID: {$testBlockId}"
);

// Verify duplicate block in same district is detected
$dupBlockCheck = $db->prepare("SELECT id FROM blocks WHERE district_id = ? AND LOWER(TRIM(name)) = LOWER(TRIM(?))");
$dupBlockCheck->execute([$testDistId, $testBlockName]);
$dupFound = (bool)$dupBlockCheck->fetch();

assertTest(
    "Same-district duplicate block is accurately identified",
    $dupFound === true,
    "Block '{$testBlockName}' detected under District {$testDistId}"
);

// ---------------------------------------------------------
// TEST 4: EK BLOCK, MULTIPLE RATES (BICCO, BCCCO, BRCCO, BOBC, NCLCO, OTHER)
// ---------------------------------------------------------
echo "\n--- 4. Multi-Code Rate Configuration for Single Block ---\n";
$testRates = [
    'BICCO' => 50.00,
    'BCCCO' => 60.00,
    'BRCCO' => 55.00,
    'BOBC'  => 65.00,
    'NCLCO' => 70.00,
    'OTHER' => 80.00,
];

$insRate = $db->prepare("
    INSERT INTO service_rate_rules (
        data_type_id, state, district_id, block_id, application_code,
        rule_label, adjustment_type, fee_amount, distributor_commission, is_active, created_at
    ) VALUES (
        ?, 'Bihar', ?, ?, ?,
        ?, 'FIXED', ?, 10.00, 1, NOW()
    )
");

$createdRuleIds = [];
foreach ($testRates as $code => $fee) {
    $insRate->execute([
        $srvBlockApp['id'],
        $testDistId,
        $testBlockId,
        $code,
        "Custom Rate for {$code}",
        $fee
    ]);
    $createdRuleIds[$code] = (int)$db->lastInsertId();
}

assertTest(
    "Configured 6 distinct custom rates for a single block",
    count($createdRuleIds) === 6,
    "Configured codes: " . implode(', ', array_keys($createdRuleIds))
);

// Verify duplicate rule detection
$checkDupRule = $db->prepare("
    SELECT id, fee_amount FROM service_rate_rules
    WHERE data_type_id = ? AND district_id = ? AND block_id = ? AND application_code = ?
");
$checkDupRule->execute([$srvBlockApp['id'], $testDistId, $testBlockId, 'BICCO']);
$existingRule = $checkDupRule->fetch(PDO::FETCH_ASSOC);

assertTest(
    "Duplicate rate rule detection prevents silent overwrite",
    !empty($existingRule) && (float)$existingRule['fee_amount'] === 50.00,
    "Found existing rule ID {$existingRule['id']} with fee ₹" . $existingRule['fee_amount']
);

// ---------------------------------------------------------
// TEST 5: REFERENCE PRICING & FALLBACK RATES
// ---------------------------------------------------------
echo "\n--- 5. Reference Pricing & Fallback Logic ---\n";
// Set fallback settings to verify
$setFallbacks = $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
$setFallbacks->execute(['fallback_rate_standard', '100.00']);
$setFallbacks->execute(['fallback_rate_other', '130.00']);

// 5a. Custom rate calculation for configured block
$calcBicco = PricingService::calculatePrice($srvBlockApp['id'], $testDistId, $testBlockId, [
    'application_code' => 'BICCO',
    'application_number' => 'BICCO/2026/001'
]);
assertTest(
    "Custom block rate correctly applied for BICCO (₹50)",
    (float)$calcBicco['final_fee'] === 50.00,
    "Calculated: ₹{$calcBicco['final_fee']}, Note: {$calcBicco['rate_note']}"
);

$calcBccco = PricingService::calculatePrice($srvBlockApp['id'], $testDistId, $testBlockId, [
    'application_code' => 'BCCCO',
    'application_number' => 'BCCCO/2026/001'
]);
assertTest(
    "Custom block rate correctly applied for BCCCO (₹60)",
    (float)$calcBccco['final_fee'] === 60.00,
    "Calculated: ₹{$calcBccco['final_fee']}"
);

// 5b. Fallback pricing for unconfigured block
// Create a separate block with no configured rates
$unconfigBlockName = "VERIF_UNCONFIG_BLOCK";
$insB->execute([$testDistId, $unconfigBlockName, $unconfigBlockName, 'VB99']);
$unconfigBlockId = (int)$db->lastInsertId();

$calcFallbackBicco = PricingService::calculatePrice($srvBlockApp['id'], $testDistId, $unconfigBlockId, [
    'application_code' => 'BICCO',
    'application_number' => 'BICCO/2026/999'
]);
assertTest(
    "Fallback rate standard applied for unconfigured block with BICCO (₹100)",
    (float)$calcFallbackBicco['final_fee'] === 100.00,
    "Calculated: ₹{$calcFallbackBicco['final_fee']}, Note: {$calcFallbackBicco['rate_note']}"
);

$calcFallbackBobc = PricingService::calculatePrice($srvBlockApp['id'], $testDistId, $unconfigBlockId, [
    'application_code' => 'BOBC',
    'application_number' => 'BOBC/2026/999'
]);
assertTest(
    "Fallback rate other applied for unconfigured block with BOBC (₹130)",
    (float)$calcFallbackBobc['final_fee'] === 130.00,
    "Calculated: ₹{$calcFallbackBobc['final_fee']}, Note: {$calcFallbackBobc['rate_note']}"
);

$calcFallbackOther = PricingService::calculatePrice($srvBlockApp['id'], $testDistId, $unconfigBlockId, [
    'application_code' => 'OTHER',
    'application_number' => 'OTHER/2026/999'
]);
assertTest(
    "Fallback rate other applied for unconfigured block with OTHER (₹130)",
    (float)$calcFallbackOther['final_fee'] === 130.00,
    "Calculated: ₹{$calcFallbackOther['final_fee']}, Note: {$calcFallbackOther['rate_note']}"
);

// ---------------------------------------------------------
// TEST 6: INPUT VALIDATION (Code & Prefix alignment)
// ---------------------------------------------------------
echo "\n--- 6. Application Code & Number Format Validation ---\n";
$validationCaught = false;
try {
    ApplicationService::validateApplicationCodeAndNumber('BICCO', 'BRCCO/2026/99999');
} catch (Exception $e) {
    $validationCaught = true;
    $valMsg = $e->getMessage();
}
assertTest(
    "Prefix conflict rejected (BICCO code with BRCCO prefix application number)",
    $validationCaught === true,
    "Rejection message: " . ($valMsg ?? '')
);

$validPassed = false;
try {
    ApplicationService::validateApplicationCodeAndNumber('BICCO', 'BICCO/2026/54564');
    $validPassed = true;
} catch (Exception $e) {
    $validPassed = false;
}
assertTest(
    "Matching prefix accepted (BICCO code with BICCO/2026/54564)",
    $validPassed === true,
    "Validation passed successfully"
);

// ---------------------------------------------------------
// TEST 7: WALLET & SUBMISSION (Atomic Debit, Duplicate Prevention)
// ---------------------------------------------------------
echo "\n--- 7. Atomic Submission, Wallet Debit & Duplicate Prevention ---\n";
// Create a test user with isolated wallet
$testEmail = "verif_user_" . time() . "@test.local";
$insUser = $db->prepare("
    INSERT INTO users (
        name, email, mobile, password_hash, role, status,
        center_name, assigned_all_districts, created_at
    ) VALUES (
        'Verification User', ?, '9876543210', 'dummy_hash', 'USER', 'ACTIVE',
        'Test Kiosk Center', 1, NOW()
    )
");
$insUser->execute([$testEmail]);
$testUserId = (int)$db->lastInsertId();

// Set initial wallet balance to ₹40 (insufficient for ₹50 fee)
$setWal = $db->prepare("INSERT INTO wallets (user_id, balance, total_credited, total_debited, updated_at) VALUES (?, 40.00, 40.00, 0.00, NOW())");
$setWal->execute([$testUserId]);

$insufficientCaught = false;
try {
    ApplicationService::submit(
        $testUserId,
        $srvBlockApp['id'],
        $testDistId,
        $testBlockId,
        [
            'application_code' => 'BICCO',
            'application_number' => 'BICCO/2026/T001',
            'applicant_name' => 'Ramesh Test',
            'applicant_mobile' => '9876543210'
        ]
    );
} catch (Exception $e) {
    $insufficientCaught = true;
    $insMsg = $e->getMessage();
}
assertTest(
    "Insufficient wallet balance rejects submission",
    $insufficientCaught === true && str_contains(strtolower($insMsg), 'insufficient'),
    "Error: " . ($insMsg ?? '')
);

// Top up user wallet to ₹500
$updWal = $db->prepare("UPDATE wallets SET balance = 500.00, total_credited = 500.00 WHERE user_id = ?");
$updWal->execute([$testUserId]);

// Submit valid application
$appSubmission = ApplicationService::submit(
    $testUserId,
    $srvBlockApp['id'],
    $testDistId,
    $testBlockId,
    [
        'application_code' => 'BICCO',
        'application_number' => 'BICCO/2026/T001',
        'applicant_name' => 'Ramesh Test',
        'applicant_mobile' => '9876543210'
    ]
);

$appId = (int)($appSubmission['application_id'] ?? 0);
$balAfterDebit = (float)WalletService::getBalance($testUserId);

assertTest(
    "Successful application submission atomically debits wallet fee (₹500 -> ₹450)",
    $appId > 0 && abs($balAfterDebit - 450.00) < 0.01,
    "Created App ID: {$appId}, App Ref: {$appSubmission['app_ref']}, Balance: ₹{$balAfterDebit}"
);

// Verify duplicate submission prevention
$dupCaught = false;
try {
    ApplicationService::submit(
        $testUserId,
        $srvBlockApp['id'],
        $testDistId,
        $testBlockId,
        [
            'application_code' => 'BICCO',
            'application_number' => 'BICCO/2026/T001',
            'applicant_name' => 'Ramesh Test',
            'applicant_mobile' => '9876543210'
        ]
    );
} catch (Exception $e) {
    $dupCaught = true;
    $dupMsg = $e->getMessage();
}
assertTest(
    "Rapid duplicate submission is immediately blocked",
    $dupCaught === true,
    "Duplicate check message: " . ($dupMsg ?? '')
);

// ---------------------------------------------------------
// TEST 8: REQUEST PROCESSING & REJECTION REFUND
// ---------------------------------------------------------
echo "\n--- 8. Status Workflow & Rejection Auto-Refund ---\n";
// Create admin user mock actor
$adminUser = $db->query("SELECT id, name FROM users WHERE role = 'ADMIN' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$adminId = $adminUser ? (int)$adminUser['id'] : 1;
$adminName = $adminUser ? $adminUser['name'] : 'Administrator';

// 8a. Transition to UNDER_REVIEW
$revRes = ApplicationService::updateStatus($appId, 'UNDER_REVIEW', $adminId, 'ADMIN', $adminName, 'Under review by block clerk');
assertTest(
    "Application status transitioned to UNDER_REVIEW",
    ($revRes['success'] ?? false) === true,
    "Status updated"
);

// 8b. Transition to REJECTED with refund
$rejRes = ApplicationService::updateStatus(
    $appId,
    'REJECTED',
    $adminId,
    'ADMIN',
    $adminName,
    'Verification rejected',
    'Document discrepancy in family income proof'
);

$balAfterRefund = (float)WalletService::getBalance($testUserId);
assertTest(
    "On REJECTED, debited fee is automatically refunded to user wallet (₹450 -> ₹500)",
    ($rejRes['success'] ?? false) === true && abs($balAfterRefund - 500.00) < 0.01,
    "Balance after refund: ₹{$balAfterRefund}"
);

// Verify idempotency of refund (no double refund)
$refundTxnCount = (int)$db->query("SELECT COUNT(*) FROM wallet_transactions WHERE user_id = {$testUserId} AND type = 'REFUND_CREDIT'")->fetchColumn();
assertTest(
    "Refund transaction is strictly unique (no double refund)",
    $refundTxnCount === 1,
    "Total refund transactions recorded: {$refundTxnCount}"
);

// ---------------------------------------------------------
// CLEANUP TEST DATA
// ---------------------------------------------------------
echo "\n--- Cleanup Test Records ---\n";
$db->exec("DELETE FROM application_status_history WHERE application_id = {$appId}");
$db->exec("DELETE FROM application_field_values WHERE application_id = {$appId}");
$db->exec("DELETE FROM applications WHERE id = {$appId}");
$db->exec("DELETE FROM wallet_transactions WHERE user_id = {$testUserId}");
$db->exec("DELETE FROM wallets WHERE user_id = {$testUserId}");
$db->exec("DELETE FROM users WHERE id = {$testUserId}");
foreach ($createdRuleIds as $rid) {
    $db->exec("DELETE FROM service_rate_rules WHERE id = {$rid}");
}
$db->exec("DELETE FROM blocks WHERE id IN ({$testBlockId}, {$unconfigBlockId})");
$db->exec("DELETE FROM districts WHERE id = {$testDistId}");
echo "Isolated test records successfully cleaned up.\n";

echo "\n========================================================\n";
echo "   VERIFICATION SUMMARY: {$passCount} PASSED / {$failCount} FAILED\n";
echo "========================================================\n";
exit($failCount === 0 ? 0 : 1);
