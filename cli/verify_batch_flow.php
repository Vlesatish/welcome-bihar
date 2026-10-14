<?php
// Autoloader for App namespace
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $baseDir = dirname(__DIR__) . '/app/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

require_once dirname(__DIR__) . '/app/helpers.php';
\App\Config\Config::init();

use App\Config\Database;
use App\Services\ApplicationService;
use App\Services\PricingService;
use App\Services\WalletService;

echo "========================================================\n";
echo " WELCOME BIHAR: BATCH SUBMISSION & VERIFICATION SUITE\n";
echo "========================================================\n\n";

$db = Database::getConnection();
$testRunId = 'TEST_' . date('Ymd_His') . '_' . bin2hex(random_bytes(2));
$cleanupAppIds = [];
$cleanupUserIds = [];

    // Pre-cleanup any stale test users
    $staleUserIds = $db->query("SELECT id FROM users WHERE email LIKE 'test_%@example.com'")->fetchAll(PDO::FETCH_COLUMN);
    if (!empty($staleUserIds)) {
        $staleIn = implode(',', array_map('intval', $staleUserIds));
        $staleAppIds = $db->query("SELECT id FROM applications WHERE user_id IN ({$staleIn})")->fetchAll(PDO::FETCH_COLUMN);
        if (!empty($staleAppIds)) {
            $staleAppIn = implode(',', array_map('intval', $staleAppIds));
            $db->exec("DELETE FROM application_chats WHERE application_id IN ({$staleAppIn})");
            $db->exec("DELETE FROM application_status_history WHERE application_id IN ({$staleAppIn})");
            $db->exec("DELETE FROM application_field_values WHERE application_id IN ({$staleAppIn})");
            $db->exec("DELETE FROM applications WHERE id IN ({$staleAppIn})");
        }
        $db->exec("DELETE FROM audit_logs WHERE actor_user_id IN ({$staleIn})");
        $db->exec("DELETE FROM notifications WHERE user_id IN ({$staleIn})");
        $db->exec("DELETE FROM wallet_transactions WHERE user_id IN ({$staleIn})");
        $db->exec("DELETE FROM wallets WHERE user_id IN ({$staleIn})");
        $db->exec("DELETE FROM users WHERE id IN ({$staleIn})");
    }

try {
    // 0. Setup isolated test user
    $testMobile = '99' . str_pad((string)random_int(10000000, 99999999), 8, '0', STR_PAD_LEFT);
    $testUuid = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex(random_bytes(16)), 4));
    $insUser = $db->prepare("
        INSERT INTO users (uuid, name, mobile, email, password_hash, role, status, assigned_all_districts, created_at)
        VALUES (?, ?, ?, ?, ?, 'USER', 'ACTIVE', 1, NOW())
    ");
    $insUser->execute([
        $testUuid,
        "Batch Test User {$testRunId}",
        $testMobile,
        "test_{$testRunId}@example.com",
        password_hash('TestPass123!', PASSWORD_BCRYPT)
    ]);
    $testUserId = (int)$db->lastInsertId();
    $cleanupUserIds[] = $testUserId;

    // Create wallet for test user with ₹1000.00
    $db->prepare("INSERT INTO wallets (user_id, balance, created_at, updated_at) VALUES (?, 1000.00, NOW(), NOW())")
       ->execute([$testUserId]);

    echo "[SETUP] Created isolated test user ID {$testUserId} with ₹1000.00 wallet.\n";

    // 1. Fetch active srv_block_app service
    $dtRow = $db->query("SELECT id, code, name_en, fee_amount FROM data_types WHERE code = 'srv_block_app' AND is_active = 1")->fetch(PDO::FETCH_ASSOC);
    if (!$dtRow) {
        throw new Exception("srv_block_app data type not found or inactive.");
    }
    $dataTypeId = (int)$dtRow['id'];
    echo "[SETUP] srv_block_app ID: {$dataTypeId}, Base Fee: ₹{$dtRow['fee_amount']}\n";

    // Fetch valid district and block
    $distRow = $db->query("SELECT id, name FROM districts WHERE is_active = 1 ORDER BY id ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $blockRows = $db->query("SELECT id, name FROM blocks WHERE district_id = {$distRow['id']} AND is_active = 1 ORDER BY id ASC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);

    if (empty($distRow) || empty($blockRows)) {
        throw new Exception("Need at least 1 district and blocks for testing.");
    }
    $districtId = (int)$distRow['id'];
    echo "[SETUP] Selected District: {$distRow['name']} (ID: {$districtId}) with " . count($blockRows) . " blocks.\n\n";

    // -------------------------------------------------------------
    // TEST 1: Single Application Batch Submission
    // -------------------------------------------------------------
    echo "--- TEST 1: Single Application Submission via Batch ---\n";
    $appNum1 = "BICCO/2026/" . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
    $pricing1 = PricingService::calculatePrice($dataTypeId, $districtId, (int)$blockRows[0]['id'], ['application_code' => 'BICCO']);
    $serverFee1 = (float)$pricing1['final_fee'];

    // First test: intentional price change detection
    $resPriceChange = ApplicationService::submitBatch($testUserId, $dataTypeId, [
        [
            'state' => 'Bihar',
            'district_id' => $districtId,
            'block_id' => (int)$blockRows[0]['id'],
            'application_code' => 'BICCO',
            'application_number' => $appNum1,
            'applicant_name' => 'Amit Kumar',
            'client_fee' => $serverFee1 - 10.00 // Stale fee!
        ]
    ]);
    if (empty($resPriceChange['price_changed'])) {
        throw new Exception("Test 1 Failed: Price change was not flagged!");
    }
    echo "  [PASS] Price change correctly detected (old ₹" . ($serverFee1 - 10) . " vs new ₹{$serverFee1}).\n";

    // Second test: submit with accept_price_change or exact server fee
    $singleBatch = [
        [
            'state' => 'Bihar',
            'district_id' => $districtId,
            'block_id' => (int)$blockRows[0]['id'],
            'application_code' => 'BICCO',
            'application_number' => $appNum1,
            'applicant_name' => 'Amit Kumar',
            'client_fee' => $serverFee1
        ]
    ];

    $res1 = ApplicationService::submitBatch($testUserId, $dataTypeId, $singleBatch);
    if (!$res1['success'] || $res1['total_items'] !== 1) {
        print_r($res1);
        throw new Exception("Test 1 Failed: Expected 1 application created.");
    }

    $createdApp1 = $res1['applications'][0];
    $cleanupAppIds[] = (int)$createdApp1['id'];
    $batchRef1 = $res1['batch_ref'];

    $balAfter1 = WalletService::getBalance($testUserId);
    $expectedBal1 = 1000.00 - (float)$createdApp1['fee_debited'];

    if (abs($balAfter1 - $expectedBal1) > 0.01) {
        throw new Exception("Test 1 Failed: Wallet balance mismatch. Expected ₹{$expectedBal1}, got ₹{$balAfter1}");
    }
    echo "  [PASS] Created App #{$createdApp1['app_ref']} (ID: {$createdApp1['id']}) in Batch #{$batchRef1}.\n";
    echo "  [PASS] Fee debited: ₹{$createdApp1['fee_debited']}, New Balance: ₹{$balAfter1}\n\n";

    // -------------------------------------------------------------
    // TEST 2: Multiple (5) Applications with Mixed Pricing
    // -------------------------------------------------------------
    echo "--- TEST 2: 5 Mixed Applications in Single Atomic Batch ---\n";
    $fiveBatch = [];
    $expectedTotalFee = 0.00;
    $codes = ['BICCO', 'BCCCO', 'BRCCO', 'BOBC', 'NCLCO'];

    for ($i = 0; $i < 5; $i++) {
        $blkId = (int)($blockRows[$i % count($blockRows)]['id']);
        $code = $codes[$i];
        $appNum = "{$code}/2026/" . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
        $pricing = PricingService::calculatePrice($dataTypeId, $districtId, $blkId, ['application_code' => $code]);
        $rowFee = (float)$pricing['final_fee'];
        $expectedTotalFee += $rowFee;

        $fiveBatch[] = [
            'state' => 'Bihar',
            'district_id' => $districtId,
            'block_id' => $blkId,
            'application_code' => $code,
            'application_number' => $appNum,
            'applicant_name' => "Applicant {$i} ({$code})",
            'client_fee' => $rowFee
        ];
    }

    $balBefore2 = WalletService::getBalance($testUserId);
    $res2 = ApplicationService::submitBatch($testUserId, $dataTypeId, $fiveBatch);

    if (!$res2['success'] || $res2['total_items'] !== 5) {
        throw new Exception("Test 2 Failed: Expected 5 applications created.");
    }

    if (abs($res2['total_fee'] - $expectedTotalFee) > 0.01) {
        throw new Exception("Test 2 Failed: Expected total fee ₹{$expectedTotalFee}, got ₹{$res2['total_fee']}");
    }

    $commonBatchRef = $res2['batch_ref'];
    foreach ($res2['applications'] as $app) {
        $cleanupAppIds[] = (int)$app['id'];
        if ($app['batch_ref'] !== $commonBatchRef) {
            throw new Exception("Test 2 Failed: batch_ref not shared across items.");
        }
    }

    $balAfter2 = WalletService::getBalance($testUserId);
    $expectedBal2 = $balBefore2 - $expectedTotalFee;

    if (abs($balAfter2 - $expectedBal2) > 0.01) {
        throw new Exception("Test 2 Failed: Wallet balance mismatch after 5-batch.");
    }
    echo "  [PASS] 5 applications created with common Batch Ref #{$commonBatchRef}.\n";
    echo "  [PASS] Sum of individual fees: ₹{$expectedTotalFee}, debited exactly once.\n";
    echo "  [PASS] Wallet before: ₹{$balBefore2}, Wallet after: ₹{$balAfter2}\n\n";

    // -------------------------------------------------------------
    // TEST 3: Intra-Batch Duplicate Application Number Rejection
    // -------------------------------------------------------------
    echo "--- TEST 3: Intra-Batch Duplicate Detection ---\n";
    $dupNumber = "BICCO/2026/DUP999";
    $dupBatch = [
        [
            'state' => 'Bihar',
            'district_id' => $districtId,
            'block_id' => (int)$blockRows[0]['id'],
            'application_code' => 'BICCO',
            'application_number' => $dupNumber,
            'applicant_name' => 'User One'
        ],
        [
            'state' => 'Bihar',
            'district_id' => $districtId,
            'block_id' => (int)$blockRows[0]['id'],
            'application_code' => 'BICCO',
            'application_number' => $dupNumber, // Duplicate!
            'applicant_name' => 'User Two'
        ]
    ];

    $caughtDup = false;
    try {
        ApplicationService::submitBatch($testUserId, $dataTypeId, $dupBatch);
    } catch (Exception $e) {
        if (str_contains($e->getMessage(), 'Repeated application number')) {
            $caughtDup = true;
            echo "  [PASS] Caught intra-batch duplicate: '{$e->getMessage()}'\n";
        } else {
            echo "  [FAIL] Unexpected exception: {$e->getMessage()}\n";
        }
    }
    if (!$caughtDup) {
        throw new Exception("Test 3 Failed: Intra-batch duplicate was NOT rejected!");
    }
    echo "\n";

    // -------------------------------------------------------------
    // TEST 4: Insufficient Balance Rejection & Exact Shortfall
    // -------------------------------------------------------------
    echo "--- TEST 4: Insufficient Wallet Balance ---\n";
    // Set wallet balance to ₹10.00
    $db->prepare("UPDATE wallets SET balance = 10.00 WHERE user_id = ?")->execute([$testUserId]);

    $caughtShortfall = false;
    try {
        ApplicationService::submitBatch($testUserId, $dataTypeId, [
            [
                'state' => 'Bihar',
                'district_id' => $districtId,
                'block_id' => (int)$blockRows[0]['id'],
                'application_code' => 'BICCO',
                'application_number' => 'BICCO/2026/NOBAL1',
                'applicant_name' => 'No Balance User'
            ]
        ]);
    } catch (Exception $e) {
        if (str_contains($e->getMessage(), 'Insufficient Wallet Balance')) {
            $caughtShortfall = true;
            echo "  [PASS] Insufficient balance caught with exact message: '{$e->getMessage()}'\n";
        }
    }
    if (!$caughtShortfall) {
        throw new Exception("Test 4 Failed: Insufficient balance did not trigger error!");
    }
    // Restore wallet balance
    $db->prepare("UPDATE wallets SET balance = 500.00 WHERE user_id = ?")->execute([$testUserId]);
    echo "\n";

    // -------------------------------------------------------------
    // TEST 5: Atomic Rollback on Failure
    // -------------------------------------------------------------
    echo "--- TEST 5: Atomic Rollback on Mid-Batch Failure ---\n";
    $countBefore = (int)$db->query("SELECT COUNT(*) FROM applications WHERE user_id = {$testUserId}")->fetchColumn();
    $balBefore5 = WalletService::getBalance($testUserId);

    $caughtRollback = false;
    try {
        ApplicationService::submitBatch($testUserId, $dataTypeId, [
            [
                'state' => 'Bihar',
                'district_id' => $districtId,
                'block_id' => (int)$blockRows[0]['id'],
                'application_code' => 'BICCO',
                'application_number' => 'BICCO/2026/ROLLBACK1',
                'applicant_name' => 'Valid User'
            ],
            [
                'state' => 'Bihar',
                'district_id' => 9999999, // Invalid district!
                'block_id' => 9999999,
                'application_code' => 'BICCO',
                'application_number' => 'BICCO/2026/ROLLBACK2',
                'applicant_name' => 'Invalid Location User'
            ]
        ]);
    } catch (Exception $e) {
        $caughtRollback = true;
        echo "  [PASS] Submission rejected as expected: '{$e->getMessage()}'\n";
    }

    $countAfter = (int)$db->query("SELECT COUNT(*) FROM applications WHERE user_id = {$testUserId}")->fetchColumn();
    $balAfter5 = WalletService::getBalance($testUserId);

    if ($countAfter !== $countBefore) {
        throw new Exception("Test 5 Failed: Applications table not rolled back!");
    }
    if (abs($balAfter5 - $balBefore5) > 0.01) {
        throw new Exception("Test 5 Failed: Wallet balance changed despite rollback!");
    }
    echo "  [PASS] Zero rows committed and wallet untouched after atomic rollback.\n\n";

    // -------------------------------------------------------------
    // TEST 6: Individual Processing & Exact Rejection Refund
    // -------------------------------------------------------------
    echo "--- TEST 6: Independent Processing & Single Rejection Refund ---\n";
    $testBatchAppToReject = $res2['applications'][0];
    $testBatchAppToApprove = $res2['applications'][1];

    $appIdToReject = (int)$testBatchAppToReject['id'];
    $appIdToApprove = (int)$testBatchAppToApprove['id'];
    $feeToRefund = (float)$testBatchAppToReject['price'];

    $balBeforeRefund = WalletService::getBalance($testUserId);

    // 1. Reject individual application
    ApplicationService::updateStatus(
        $appIdToReject,
        'REJECTED',
        1, // actorUserId
        'ADMIN', // actorRole
        'Admin', // actorName
        'Applicant details mismatched in portal', // remarks
        'Applicant details mismatched in portal'  // rejectionReason
    );

    $balAfterRefund = WalletService::getBalance($testUserId);
    $refundDiff = $balAfterRefund - $balBeforeRefund;

    if (abs($refundDiff - $feeToRefund) > 0.01) {
        throw new Exception("Test 6 Failed: Rejection refund did not credit exact original fee! Expected +₹{$feeToRefund}, got +₹{$refundDiff}");
    }
    echo "  [PASS] Application #{$appIdToReject} rejected. Wallet credited exact fee +₹{$feeToRefund}.\n";

    // Verify rejection record
    $rejRow = $db->query("SELECT status, is_refunded, admin_remarks FROM applications WHERE id = {$appIdToReject}")->fetch(PDO::FETCH_ASSOC);
    if ($rejRow['status'] !== 'REJECTED' || (int)$rejRow['is_refunded'] !== 1) {
        throw new Exception("Test 6 Failed: Application status or is_refunded flag not updated properly.");
    }
    echo "  [PASS] Verified is_refunded = 1 and status = REJECTED.\n";

    // 2. Approve sibling application
    ApplicationService::updateStatus(
        $appIdToApprove,
        'APPROVED',
        1,
        'ADMIN',
        'Admin',
        'Verified and approved successfully'
    );

    $appRowApproved = $db->query("SELECT status, is_refunded FROM applications WHERE id = {$appIdToApprove}")->fetch(PDO::FETCH_ASSOC);
    if ($appRowApproved['status'] !== 'APPROVED' || (int)$appRowApproved['is_refunded'] !== 0) {
        throw new Exception("Test 6 Failed: Sibling application affected by rejection refund.");
    }
    echo "  [PASS] Sibling Application #{$appIdToApprove} approved independently, not refunded.\n\n";

    // -------------------------------------------------------------
    // TEST 7: Code-Number Mismatch Validation (BCCCO vs BICCO)
    // -------------------------------------------------------------
    echo "--- TEST 7: Code vs Prefix Mismatch Validation (BCCCO vs BICCO) ---\n";
    $mismatchCaught = false;
    try {
        ApplicationService::submitBatch($testUserId, $dataTypeId, [
            [
                'state' => 'Bihar',
                'district_id' => $districtId,
                'block_id' => (int)$blockRows[0]['id'],
                'application_code' => 'BCCCO',
                'application_number' => 'BICCO/2026/564564', // Mismatch!
                'applicant_name' => 'Mismatch Test'
            ]
        ]);
    } catch (Exception $e) {
        if (stripos($e->getMessage(), 'prefix mismatch') !== false || stripos($e->getMessage(), 'Invalid Application Number prefix') !== false) {
            $mismatchCaught = true;
            echo "  [PASS] Successfully rejected BCCCO with BICCO number: '{$e->getMessage()}'\n";
        } else {
            echo "  [FAIL] Unexpected error message: {$e->getMessage()}\n";
        }
    }
    if (!$mismatchCaught) {
        throw new Exception("Test 7 Failed: BCCCO with BICCO number was NOT rejected!");
    }

    // Verify reverse mismatch as well (BICCO with BCCCO number)
    $reverseMismatchCaught = false;
    try {
        ApplicationService::submitBatch($testUserId, $dataTypeId, [
            [
                'state' => 'Bihar',
                'district_id' => $districtId,
                'block_id' => (int)$blockRows[0]['id'],
                'application_code' => 'BICCO',
                'application_number' => 'BCCCO/2026/564564', // Reverse Mismatch!
                'applicant_name' => 'Reverse Mismatch Test'
            ]
        ]);
    } catch (Exception $e) {
        if (stripos($e->getMessage(), 'prefix mismatch') !== false || stripos($e->getMessage(), 'Invalid Application Number prefix') !== false) {
            $reverseMismatchCaught = true;
            echo "  [PASS] Successfully rejected BICCO with BCCCO number: '{$e->getMessage()}'\n";
        } else {
            echo "  [FAIL] Unexpected error message: {$e->getMessage()}\n";
        }
    }
    if (!$reverseMismatchCaught) {
        throw new Exception("Test 7 Failed: BICCO with BCCCO number was NOT rejected!");
    }
    echo "  [PASS] Code-Number prefix validation is strictly enforced.\n\n";

    // -------------------------------------------------------------
    // TEST 8: Full Sequential Batch Flow Simulation (5 Entries, Edit/Remove, Unsaved Draft Resolution)
    // -------------------------------------------------------------
    echo "--- TEST 8: Sequential Batch Lifecycle (5 entries, edit, remove, draft submit) ---\n";
    
    // Simulate user queue in UI
    $simulatedQueue = [];
    $codes = ['BICCO', 'BCCCO', 'BRCCO', 'BOBC', 'NCLCO'];
    
    // 1. User sequentially enters 5 applications
    for ($i = 0; $i < 5; $i++) {
        $code = $codes[$i];
        $blkId = (int)$blockRows[$i % count($blockRows)]['id'];
        $appNum = "{$code}/2026/SEQ" . str_pad((string)($i + 1), 4, '0', STR_PAD_LEFT);
        $pricing = PricingService::calculatePrice($dataTypeId, $districtId, $blkId, ['application_code' => $code]);
        $simulatedQueue[] = [
            'state' => 'Bihar',
            'district_id' => $districtId,
            'block_id' => $blkId,
            'application_code' => $code,
            'application_number' => $appNum,
            'applicant_name' => "Sequential Applicant {$i}",
            'client_fee' => (float)$pricing['final_fee']
        ];
    }
    echo "  [PASS] Added 5 sequential entries (Application 1 to 5). Initial queue count: " . count($simulatedQueue) . "\n";

    // 2. User edits Entry #2 (index 1) in place
    $editBlk = (int)$blockRows[0]['id'];
    $pricingEdit = PricingService::calculatePrice($dataTypeId, $districtId, $editBlk, ['application_code' => 'BCCCO']);
    $simulatedQueue[1]['applicant_name'] = "Sequential Applicant 1 (Updated Name)";
    $simulatedQueue[1]['block_id'] = $editBlk;
    $simulatedQueue[1]['client_fee'] = (float)$pricingEdit['final_fee'];
    if (count($simulatedQueue) !== 5) {
        throw new Exception("Test 8 Failed: Edit in place created duplicate row!");
    }
    echo "  [PASS] Edited Entry #2 in place without creating duplicate. Queue count remains: " . count($simulatedQueue) . "\n";

    // 3. User removes Entry #4 (index 3)
    array_splice($simulatedQueue, 3, 1);
    if (count($simulatedQueue) !== 4) {
        throw new Exception("Test 8 Failed: Removal did not reduce queue size to 4!");
    }
    echo "  [PASS] Removed 1 item. Queue count correctly adjusted to 4.\n";

    // 4. Simulate Partial Fresh Form Resolution: "Add Entry & Submit All"
    // User had a 5th item typed in active form
    $unaddedDraft = [
        'state' => 'Bihar',
        'district_id' => $districtId,
        'block_id' => (int)$blockRows[1]['id'],
        'application_code' => 'RTPS',
        'application_number' => 'RTPS/2026/DRAFT01',
        'applicant_name' => 'Draft Applicant Five',
        'client_fee' => (float)PricingService::calculatePrice($dataTypeId, $districtId, (int)$blockRows[1]['id'], ['application_code' => 'RTPS'])['final_fee']
    ];
    // User clicked "Add Entry & Submit All", so it gets pushed
    $simulatedQueue[] = $unaddedDraft;
    if (count($simulatedQueue) !== 5) {
        throw new Exception("Test 8 Failed: Add & Submit draft did not bring count back to 5!");
    }
    echo "  [PASS] Unsaved draft successfully added via prompt modal. Total batch size: 5.\n";

    // Verify calculated totals
    $calcSum = array_sum(array_column($simulatedQueue, 'client_fee'));
    $balBefore8 = WalletService::getBalance($testUserId);

    // Ensure test user has enough wallet balance
    if ($balBefore8 < $calcSum) {
        $db->prepare("UPDATE wallets SET balance = balance + 1000.00 WHERE user_id = ?")->execute([$testUserId]);
        $balBefore8 = WalletService::getBalance($testUserId);
    }

    // Submit the 5-item batch
    $res8 = ApplicationService::submitBatch($testUserId, $dataTypeId, $simulatedQueue);
    if (!$res8['success'] || $res8['total_items'] !== 5) {
        throw new Exception("Test 8 Failed: Batch submission failed for 5 items.");
    }
    foreach ($res8['applications'] as $a) {
        $cleanupAppIds[] = (int)$a['id'];
    }

    $balAfter8 = WalletService::getBalance($testUserId);
    if (abs(($balBefore8 - $balAfter8) - $calcSum) > 0.01) {
        throw new Exception("Test 8 Failed: Wallet debit mismatch! Expected ₹{$calcSum}, debited ₹" . ($balBefore8 - $balAfter8));
    }
    echo "  [PASS] Batch #{$res8['batch_ref']} submitted atomically with 5 items.\n";
    echo "  [PASS] Verified total fee debit ₹{$calcSum} matches sum of rows exactly.\n\n";

    // -------------------------------------------------------------
    // TEST 9: Admin Management Lifecycle (Labour Card Style Flow)
    // -------------------------------------------------------------
    echo "TEST 9: Admin Management Lifecycle (Start Processing -> Upload & Approve -> Reject with Refund)\n";
    
    // Top up wallet for Test 9
    $db->prepare("UPDATE wallets SET balance = balance + 500.00 WHERE user_id = ?")->execute([$testUserId]);

    // Find or create admin user for actor ID
    $adminRow = $db->query("SELECT id FROM users WHERE role = 'ADMIN' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $adminActorId = $adminRow ? (int)$adminRow['id'] : $testUserId;

    // 1. Submit 2 fresh test applications in a batch
    $pA = PricingService::calculatePrice($dataTypeId, $districtId, (int)$blockRows[0]['id'], ['application_code' => 'BICCO']);
    $pB = PricingService::calculatePrice($dataTypeId, $districtId, (int)$blockRows[1]['id'], ['application_code' => 'BCCCO']);

    $batchAdminTest = [
        [
            'state' => 'Bihar',
            'district_id' => $districtId,
            'block_id' => (int)$blockRows[0]['id'],
            'application_code' => 'BICCO',
            'application_number' => 'BICCO/2026/ADM001',
            'applicant_name' => 'Admin Test Person One',
            'client_fee' => (float)$pA['final_fee']
        ],
        [
            'state' => 'Bihar',
            'district_id' => $districtId,
            'block_id' => (int)$blockRows[1]['id'],
            'application_code' => 'BCCCO',
            'application_number' => 'BCCCO/2026/ADM002',
            'applicant_name' => 'Admin Test Person Two',
            'client_fee' => (float)$pB['final_fee']
        ]
    ];

    $res9 = ApplicationService::submitBatch($testUserId, $dataTypeId, $batchAdminTest);
    if (!$res9['success'] || count($res9['applications']) !== 2) {
        throw new Exception("Test 9 Failed: Could not submit 2 test applications: " . ($res9['error'] ?? ''));
    }
    $appA = $res9['applications'][0];
    $appB = $res9['applications'][1];
    $cleanupAppIds[] = (int)$appA['id'];
    $cleanupAppIds[] = (int)$appB['id'];

    // Verify initial status is PENDING
    $stmtA = $db->prepare("SELECT status, is_refunded, result_file FROM applications WHERE id = ?");
    $stmtA->execute([$appA['id']]);
    $currA = $stmtA->fetch(PDO::FETCH_ASSOC);
    if ($currA['status'] !== 'PENDING') {
        throw new Exception("Test 9 Failed: App A initial status is not PENDING!");
    }
    echo "  [PASS] Application A & B submitted in batch #{$res9['batch_ref']} with status PENDING.\n";

    // 2. Action: Start Processing on Application A -> IN_PROCESS
    $resStart = ApplicationService::updateStatus(
        (int)$appA['id'],
        'IN_PROCESS',
        $adminActorId,
        'ADMIN',
        'Super Admin',
        'Operator started verification process'
    );
    if (!$resStart['success'] || $resStart['status'] !== 'IN_PROCESS') {
        throw new Exception("Test 9 Failed: Start Processing transition to IN_PROCESS failed!");
    }
    $stmtA->execute([$appA['id']]);
    $currA = $stmtA->fetch(PDO::FETCH_ASSOC);
    if ($currA['status'] !== 'IN_PROCESS') {
        throw new Exception("Test 9 Failed: App A status is not IN_PROCESS in DB!");
    }
    echo "  [PASS] Start Processing transitioned App A to IN_PROCESS successfully.\n";

    // 3. Action: Upload & Approve on Application A -> APPROVED with result_file
    $mockResultFile = '/uploads/results/test_cert_' . $appA['id'] . '.pdf';
    $resApprove = ApplicationService::updateStatus(
        (int)$appA['id'],
        'APPROVED',
        $adminActorId,
        'ADMIN',
        'Super Admin',
        'Document certified and approved',
        null,
        'Auto certified',
        $mockResultFile
    );
    if (!$resApprove['success'] || $resApprove['status'] !== 'APPROVED') {
        throw new Exception("Test 9 Failed: Upload & Approve transition to APPROVED failed: " . ($resApprove['error'] ?? ''));
    }
    $stmtA->execute([$appA['id']]);
    $currA = $stmtA->fetch(PDO::FETCH_ASSOC);
    if ($currA['status'] !== 'APPROVED' || $currA['result_file'] !== $mockResultFile) {
        throw new Exception("Test 9 Failed: App A not marked APPROVED or result_file not saved!");
    }
    echo "  [PASS] Upload & Approve transitioned App A to APPROVED with linked result_file.\n";

    // 4. Action: Reject on Application B with mandatory reason -> REJECTED & Refund
    $balBeforeReject = WalletService::getBalance($testUserId);
    $feeB = (float)$appB['fee_debited'];
    $rejectReason = 'Document mismatched with government database';

    $resReject = ApplicationService::updateStatus(
        (int)$appB['id'],
        'REJECTED',
        $adminActorId,
        'ADMIN',
        'Super Admin',
        $rejectReason,
        $rejectReason
    );
    if (!$resReject['success'] || $resReject['status'] !== 'REJECTED') {
        throw new Exception("Test 9 Failed: Rejection failed: " . ($resReject['error'] ?? ''));
    }

    $stmtB = $db->prepare("SELECT status, is_refunded, rejection_reason FROM applications WHERE id = ?");
    $stmtB->execute([$appB['id']]);
    $currB = $stmtB->fetch(PDO::FETCH_ASSOC);
    if ($currB['status'] !== 'REJECTED' || (int)$currB['is_refunded'] !== 1 || $currB['rejection_reason'] !== $rejectReason) {
        throw new Exception("Test 9 Failed: App B not marked REJECTED, refunded, or rejection reason missing!");
    }

    $balAfterReject = WalletService::getBalance($testUserId);
    if (abs(($balAfterReject - $balBeforeReject) - $feeB) > 0.01) {
        throw new Exception("Test 9 Failed: Wallet refund amount incorrect! Expected +₹{$feeB}, got " . ($balAfterReject - $balBeforeReject));
    }
    echo "  [PASS] Rejection correctly saved mandatory reason '{$rejectReason}' and refunded ₹{$feeB} to retailer wallet.\n";

    // 5. Test Duplicate Refund Protection on Rejected item
    $balBeforeDup = WalletService::getBalance($testUserId);
    $dupRefundRes = ApplicationService::updateStatus(
        (int)$appB['id'],
        'REJECTED',
        $adminActorId,
        'ADMIN',
        'Super Admin',
        'Second reject attempt'
    );
    $balAfterDup = WalletService::getBalance($testUserId);
    if (abs($balAfterDup - $balBeforeDup) > 0.001) {
        throw new Exception("Test 9 Failed: Duplicate refund occurred! Balance changed by " . ($balAfterDup - $balBeforeDup));
    }
    echo "  [PASS] Duplicate refund protection verified. Wallet balance untouched.\n\n";

    echo "========================================================\n";
    echo " ALL 9 INTEGRATION & VERIFICATION TESTS PASSED!\n";
    echo "========================================================\n\n";

} catch (Exception $e) {
    echo "\n[ERROR OCCURRED] " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
} finally {
    // -------------------------------------------------------------
    // TEARDOWN & CLEANUP
    // -------------------------------------------------------------
    echo "[CLEANUP] Cleaning up test applications and records...\n";
    if (!empty($cleanupAppIds)) {
        $inApps = implode(',', array_map('intval', $cleanupAppIds));
        $db->exec("DELETE FROM application_chats WHERE application_id IN ({$inApps})");
        $db->exec("DELETE FROM application_status_history WHERE application_id IN ({$inApps})");
        $db->exec("DELETE FROM application_field_values WHERE application_id IN ({$inApps})");
        $db->exec("DELETE FROM applications WHERE id IN ({$inApps})");
        echo "[CLEANUP] Deleted " . count($cleanupAppIds) . " test applications.\n";
    }

    if (!empty($cleanupUserIds)) {
        $inUsers = implode(',', array_map('intval', $cleanupUserIds));
        $userAppIds = $db->query("SELECT id FROM applications WHERE user_id IN ({$inUsers})")->fetchAll(PDO::FETCH_COLUMN);
        if (!empty($userAppIds)) {
            $inUserApps = implode(',', array_map('intval', $userAppIds));
            $db->exec("DELETE FROM application_chats WHERE application_id IN ({$inUserApps})");
            $db->exec("DELETE FROM application_status_history WHERE application_id IN ({$inUserApps})");
            $db->exec("DELETE FROM application_field_values WHERE application_id IN ({$inUserApps})");
            $db->exec("DELETE FROM applications WHERE id IN ({$inUserApps})");
        }
        $db->exec("DELETE FROM audit_logs WHERE actor_user_id IN ({$inUsers})");
        $db->exec("DELETE FROM notifications WHERE user_id IN ({$inUsers})");
        $db->exec("DELETE FROM wallet_transactions WHERE user_id IN ({$inUsers})");
        $db->exec("DELETE FROM wallets WHERE user_id IN ({$inUsers})");
        $db->exec("DELETE FROM users WHERE id IN ({$inUsers})");
        echo "[CLEANUP] Deleted " . count($cleanupUserIds) . " test users & wallets.\n";
    }
    echo "[CLEANUP] Test environment restored to pristine state.\n";
}
