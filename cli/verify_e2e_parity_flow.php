<?php
/**
 * End-to-End Verification Test Script for eCyberCafe Parity
 * Block & District Application Pass Service (srv_block_app)
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

require_once dirname(__DIR__) . '/app/helpers.php';
\App\Config\Config::init();

use App\Config\Database;
use App\Services\PricingService;
use App\Services\ApplicationService;
use App\Services\WalletService;

$db = Database::getConnection();

echo "=================================================================\n";
echo "WELCOME BIHAR: E2E VERIFICATION SUITE - ECYBERCAFE PARITY\n";
echo "Service: Block & District Application Pass Service (srv_block_app)\n";
echo "=================================================================\n\n";

$passCount = 0;
$failCount = 0;

function assertTest(bool $condition, string $testName, string $details = '') {
    global $passCount, $failCount;
    if ($condition) {
        $passCount++;
        echo "  [PASS] {$testName}\n";
    } else {
        $failCount++;
        echo "  [FAIL] {$testName} - {$details}\n";
    }
}

try {
    // -------------------------------------------------------------
    // TEST 1: Service Configuration & Enforced Single Service
    // -------------------------------------------------------------
    echo "[TEST GROUP 1] Service Configuration & Single Active Service\n";
    $dt = $db->query("SELECT id, code, name_en, fee_amount, is_active FROM data_types WHERE code = 'srv_block_app'")->fetch(PDO::FETCH_ASSOC);
    assertTest(!empty($dt), "srv_block_app exists in data_types", "Service record not found");
    assertTest((int)$dt['is_active'] === 1, "srv_block_app is active & live", "Service is not active");

    $otherActive = $db->query("SELECT COUNT(*) FROM data_types WHERE code != 'srv_block_app' AND is_active = 1")->fetchColumn();
    assertTest((int)$otherActive === 0, "No other services are active (single service enforced)", "Found {$otherActive} other active services");

    $srvId = (int)$dt['id'];

    // -------------------------------------------------------------
    // TEST 2: Location Dependent Pricing & Specificity Rules
    // -------------------------------------------------------------
    echo "\n[TEST GROUP 2] Location Dependent Pricing & Specificity Rules\n";

    // 2.1 Fallback defaults when no rule exists:
    // BICCO/BCCCO/BRCCO -> ₹100, BOBC/NCLCO/OTHER -> ₹130
    $pBicco = PricingService::calculatePrice($srvId, 999999, 999999, ['application_code' => 'BICCO']);
    assertTest(abs((float)$pBicco['final_fee'] - 100.00) < 0.01, "Fallback default for BICCO is ₹100.00", "Got " . $pBicco['final_fee']);

    $pBobc = PricingService::calculatePrice($srvId, 999999, 999999, ['application_code' => 'BOBC']);
    assertTest(abs((float)$pBobc['final_fee'] - 130.00) < 0.01, "Fallback default for BOBC is ₹130.00", "Got " . $pBobc['final_fee']);

    $pNcl = PricingService::calculatePrice($srvId, 999999, 999999, ['application_code' => 'NCLCO']);
    assertTest(abs((float)$pNcl['final_fee'] - 130.00) < 0.01, "Fallback default for NCLCO is ₹130.00", "Got " . $pNcl['final_fee']);

    // 2.2 Test exact custom block rate rule
    $testDist = $db->query("SELECT id FROM districts WHERE is_active = 1 LIMIT 1")->fetchColumn();
    $testBlk = $db->query("SELECT id FROM blocks WHERE district_id = {$testDist} AND is_active = 1 LIMIT 1")->fetchColumn();

    // Insert isolated test rate rule
    $testRateCode = 'BICCO';
    $testCustomRate = 185.50;
    $db->prepare("
        INSERT INTO service_rate_rules (data_type_id, district_id, block_id, application_code, fee_amount, adjustment_type, rule_label, is_active, created_at)
        VALUES (?, ?, ?, ?, ?, 'FIXED', 'Test E2E Custom Rate', 1, NOW())
    ")->execute([$srvId, $testDist, $testBlk, $testRateCode, $testCustomRate]);
    $ruleId = (int)$db->lastInsertId();

    $pCustom = PricingService::calculatePrice($srvId, (int)$testDist, (int)$testBlk, [
        'application_code' => $testRateCode,
        'application_number' => 'BICCO/2026/001'
    ]);
    assertTest(abs((float)$pCustom['final_fee'] - $testCustomRate) < 0.01, "Exact block + code rate rule matches (₹{$testCustomRate})", "Got " . $pCustom['final_fee']);
    assertTest(str_contains($pCustom['rate_note'], 'Configured Rate Applied'), "Rate note indicates Configured Rate Applied", "Got: {$pCustom['rate_note']}");

    // -------------------------------------------------------------
    // TEST 3: Retailer Account, Wallet & Atomic Submission
    // -------------------------------------------------------------
    echo "\n[TEST GROUP 3] Retailer Submission & Atomic Wallet Fee Debit\n";

    // Create test distributor with unique email
    $randSeed = rand(100000, 999999);
    $testDistMobile = '9900' . $randSeed;
    $db->prepare("
        INSERT INTO users (uuid, mobile, name, email, password_hash, role, status, created_at)
        VALUES (UUID(), ?, 'Test E2E Distributor', ?, 'test', 'DISTRIBUTOR', 'ACTIVE', NOW())
    ")->execute([$testDistMobile, 'test_dist_' . $randSeed . '@example.com']);
    $distId = (int)$db->lastInsertId();
    $db->prepare("INSERT INTO wallets (user_id, balance, total_credited, total_debited, created_at) VALUES (?, 0.00, 0.00, 0.00, NOW())")->execute([$distId]);

    // Create test retailer under distributor
    $testRetMobile = '9800' . $randSeed;
    $db->prepare("
        INSERT INTO users (uuid, mobile, name, email, password_hash, role, parent_distributor_id, assigned_all_districts, status, created_at)
        VALUES (UUID(), ?, 'Test E2E Retailer', ?, 'test', 'USER', ?, 1, 'ACTIVE', NOW())
    ")->execute([$testRetMobile, 'test_ret_' . $randSeed . '@example.com', $distId]);
    $retId = (int)$db->lastInsertId();

    // Initial wallet balance = ₹500.00
    $initialBalance = 500.00;
    $db->prepare("INSERT INTO wallets (user_id, balance, total_credited, total_debited, created_at) VALUES (?, ?, ?, 0.00, NOW())")->execute([$retId, $initialBalance, $initialBalance]);

    // Submit test application
    $testAppNumber = 'BICCO/2026/E2E' . rand(1000, 9999);
    $submitPayload = [
        'state' => 'Bihar',
        'district_id' => $testDist,
        'block_id' => $testBlk,
        'application_code' => 'BICCO',
        'application_number' => $testAppNumber,
        'applicant_name' => 'Ramesh Kumar Test',
        'applicant_mobile' => $testRetMobile,
        'mobile' => $testRetMobile,
        'remarks' => 'Automated verification run'
    ];

    $submitRes = ApplicationService::submit($retId, $srvId, (int)$testDist, (int)$testBlk, $submitPayload, []);
    $appId = (int)$submitRes['application_id'];

    assertTest($submitRes['success'] === true, "Application submission returns success", "Error in response");
    assertTest(!empty($submitRes['app_ref']), "Application received reference number: {$submitRes['app_ref']}");
    assertTest(abs((float)$submitRes['price'] - $testCustomRate) < 0.01, "Debited fee matches rate (₹{$testCustomRate})", "Got " . $submitRes['price']);

    // Check retailer wallet deducted
    $postSubmitBalance = WalletService::getBalance($retId);
    $expectedBalance = $initialBalance - $testCustomRate;
    assertTest(abs($postSubmitBalance - $expectedBalance) < 0.01, "Wallet balance accurately debited (Available: ₹{$postSubmitBalance})", "Expected ₹{$expectedBalance}, got ₹{$postSubmitBalance}");

    // Verify distributor commission is NOT credited yet (deferred to COMPLETED status)
    $distPreBal = WalletService::getBalance($distId);
    assertTest($distPreBal == 0.00, "Distributor commission deferred upon submission (Balance: ₹{$distPreBal})", "Distributor was debited/credited early");

    // -------------------------------------------------------------
    // TEST 4: Operator Claim & Status Flow to IN_PROCESS
    // -------------------------------------------------------------
    echo "\n[TEST GROUP 4] Operator Claim & Processing Flow\n";

    // Create test operator assigned to the target district and block
    $testOpMobile = '9700' . $randSeed;
    $db->prepare("
        INSERT INTO users (uuid, mobile, name, email, password_hash, role, status, assigned_district_id, assigned_block_id, created_at)
        VALUES (UUID(), ?, 'Operator Test Agent', ?, 'test', 'OPERATOR', 'ACTIVE', ?, ?, NOW())
    ")->execute([$testOpMobile, 'test_op_' . $randSeed . '@example.com', $testDist, $testBlk]);
    $opId = (int)$db->lastInsertId();

    // Operator starts processing assigned application
    $startRes = ApplicationService::updateStatus($appId, 'IN_PROCESS', $opId, 'OPERATOR', 'Operator Test Agent', 'Starting verification');
    assertTest($startRes['success'] === true, "Operator started processing request successfully");

    $appRecord = $db->query("SELECT status FROM applications WHERE id = {$appId}")->fetch(PDO::FETCH_ASSOC);
    $historyCount = (int)$db->query("SELECT COUNT(*) FROM application_status_history WHERE application_id = {$appId} AND actor_user_id = {$opId} AND to_status = 'IN_PROCESS'")->fetchColumn();
    assertTest($historyCount >= 1, "Status history recorded operator action with ID {$opId}");
    assertTest($appRecord['status'] === 'IN_PROCESS', "Status successfully transitioned to IN_PROCESS");

    // -------------------------------------------------------------
    // TEST 5: Completion, Output Document & Exactly-Once Commission
    // -------------------------------------------------------------
    echo "\n[TEST GROUP 5] Completion, Output Document & Commission Payout\n";

    $outputDocPath = '/uploads/results/test_cert_' . time() . '.pdf';
    $completeRes = ApplicationService::updateStatus(
        $appId,
        'COMPLETED',
        $opId,
        'OPERATOR',
        'Operator Test Agent',
        'Approved and certificate issued',
        null,
        'Verified successfully',
        $outputDocPath
    );
    assertTest($completeRes['success'] === true, "Status updated to COMPLETED");

    $completedApp = $db->query("SELECT status, result_file, is_commission_paid, commission_credited FROM applications WHERE id = {$appId}")->fetch(PDO::FETCH_ASSOC);
    assertTest($completedApp['status'] === 'COMPLETED', "Application status is COMPLETED");
    assertTest($completedApp['result_file'] === $outputDocPath, "Output document path persisted properly");
    assertTest((int)$completedApp['is_commission_paid'] === 1, "is_commission_paid flag is set to 1");

    // Check distributor received commission exactly once
    $distPostBal = WalletService::getBalance($distId);
    $commCredited = (float)$completedApp['commission_credited'];
    assertTest($distPostBal > 0 && abs($distPostBal - $commCredited) < 0.01, "Distributor credited exactly once (₹{$distPostBal})", "Distributor balance: {$distPostBal}");

    // -------------------------------------------------------------
    // TEST 6: Rejection & Exactly-Once Auto-Refund
    // -------------------------------------------------------------
    echo "\n[TEST GROUP 6] Rejection & Auto-Refund Flow\n";

    // Submit a 2nd application to test rejection & refund
    $testAppNumber2 = 'BICCO/2026/REJ' . rand(1000, 9999);
    $submitPayload2 = [
        'state' => 'Bihar',
        'district_id' => $testDist,
        'block_id' => $testBlk,
        'application_code' => 'BICCO',
        'application_number' => $testAppNumber2,
        'applicant_name' => 'Sunil Verma Test',
        'applicant_mobile' => $testRetMobile,
        'mobile' => $testRetMobile,
        'remarks' => 'Rejection test run'
    ];
    $submitRes2 = ApplicationService::submit($retId, $srvId, (int)$testDist, (int)$testBlk, $submitPayload2, []);
    $appId2 = (int)$submitRes2['application_id'];

    $balBeforeRej = WalletService::getBalance($retId);

    // Reject application with mandatory rejection reason
    $rejReason = 'Document mismatched and signature unverified.';
    $rejRes = ApplicationService::updateStatus(
        $appId2,
        'REJECTED',
        $opId,
        'OPERATOR',
        'Operator Test Agent',
        'Application rejected by verifying officer',
        $rejReason,
        'Rejected due to invalid document'
    );
    assertTest($rejRes['success'] === true, "Status updated to REJECTED");

    $rejApp = $db->query("SELECT status, is_refunded, fee_debited FROM applications WHERE id = {$appId2}")->fetch(PDO::FETCH_ASSOC);
    assertTest($rejApp['status'] === 'REJECTED', "Application is REJECTED");
    assertTest((int)$rejApp['is_refunded'] === 1, "is_refunded flag marked as 1");

    $balAfterRej = WalletService::getBalance($retId);
    $refundedFee = (float)$rejApp['fee_debited'];
    assertTest(abs($balAfterRej - ($balBeforeRej + $refundedFee)) < 0.01, "Fee of ₹{$refundedFee} refunded exactly once to retailer wallet", "Before: ₹{$balBeforeRej}, After: ₹{$balAfterRej}");

    // -------------------------------------------------------------
    // TEST 7: Chat System (Retailer - Operator - Admin Messaging)
    // -------------------------------------------------------------
    echo "\n[TEST GROUP 7] Retailer & Operator Chat Threading\n";

    $chat1 = ApplicationService::addChat($appId, $retId, 'Test Retailer', 'RETAILER', 'Sir please check my application urgently.');
    assertTest(!empty($chat1['id']), "Retailer sent message #{$chat1['id']}");

    $chat2 = ApplicationService::addChat($appId, $opId, 'Operator Test Agent', 'OPERATOR', 'Document verified, processing now.');
    assertTest(!empty($chat2['id']), "Operator replied message #{$chat2['id']}");

    $allChats = ApplicationService::getChats($appId);
    assertTest(count($allChats) >= 2, "Chat history retrieved correctly (" . count($allChats) . " messages in thread)");

    // -------------------------------------------------------------
    // CLEANUP TEST DATA (Preserve real production / existing records)
    // -------------------------------------------------------------
    echo "\n[CLEANUP] Cleaning temporary test records...\n";
    $db->prepare("DELETE FROM service_rate_rules WHERE id = ?")->execute([$ruleId]);
    $db->prepare("DELETE FROM application_chats WHERE application_id IN (?, ?)")->execute([$appId, $appId2]);
    $db->prepare("DELETE FROM application_field_values WHERE application_id IN (?, ?)")->execute([$appId, $appId2]);
    $db->prepare("DELETE FROM application_status_history WHERE application_id IN (?, ?)")->execute([$appId, $appId2]);
    $db->prepare("DELETE FROM wallet_transactions WHERE user_id IN (?, ?, ?)")->execute([$retId, $distId, $opId]);
    $db->prepare("DELETE FROM applications WHERE id IN (?, ?)")->execute([$appId, $appId2]);
    $db->prepare("DELETE FROM wallets WHERE user_id IN (?, ?, ?)")->execute([$retId, $distId, $opId]);
    $db->prepare("DELETE FROM users WHERE id IN (?, ?, ?)")->execute([$retId, $distId, $opId]);
    echo "  [OK] Cleaned test applications, wallets, rules, and chat messages.\n";

} catch (Exception $e) {
    echo "\n[EXCEPTION OCCURRED] " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    $failCount++;
}

echo "\n=================================================================\n";
echo "TEST RESULTS SUMMARY: {$passCount} PASSED, {$failCount} FAILED\n";
echo "=================================================================\n";
