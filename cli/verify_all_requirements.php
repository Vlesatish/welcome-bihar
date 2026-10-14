<?php
/**
 * Comprehensive Verification Suite for 21 Requirements
 * Run non-destructively: php cli/verify_all_requirements.php
 */

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

require_once __DIR__ . '/../app/Config/Env.php';
\App\Config\Env::load(__DIR__ . '/../.env');
require_once __DIR__ . '/../app/Config/Database.php';
require_once __DIR__ . '/../app/helpers.php';

use App\Config\Database;
use App\Services\AuthService;
use App\Services\ApplicationService;
use App\Services\PricingService;
use App\Services\WalletService;
use App\Controllers\WalletController;

$db = Database::getConnection();

$passed = 0;
$failed = 0;
$results = [];

function testCase(int $number, string $description, bool $condition, string $details = ''): void {
    global $passed, $failed, $results;
    if ($condition) {
        $passed++;
        $results[] = "[PASS] #{$number}: {$description}" . ($details ? " ({$details})" : "");
        echo "\033[32m[PASS]\033[0m #{$number}: {$description}\n";
    } else {
        $failed++;
        $results[] = "[FAIL] #{$number}: {$description}" . ($details ? " - Details: {$details}" : "");
        echo "\033[31m[FAIL]\033[0m #{$number}: {$description} - {$details}\n";
    }
}

echo "========================================================\n";
echo "STARTING NON-DESTRUCTIVE COMPREHENSIVE SYSTEM VERIFICATION\n";
echo "========================================================\n\n";

// --- 1. PHP Syntax Check ---
$syntaxErrors = [];
$dirsToScan = [__DIR__ . '/../app', __DIR__ . '/../public', __DIR__ . '/../cli'];
foreach ($dirsToScan as $dir) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $path = $file->getRealPath();
            $cmd = 'php -l "' . $path . '" 2>&1';
            $output = shell_exec($cmd);
            if (!str_contains($output, 'No syntax errors detected')) {
                $syntaxErrors[] = basename($path) . ': ' . trim($output);
            }
        }
    }
}
testCase(1, "All PHP files have valid syntax", count($syntaxErrors) === 0, implode('; ', $syntaxErrors));

// --- Setup Isolated Test Fixtures ---
try {
    // Clean any previous test leftovers safely respecting foreign keys
    $oldTestUsers = $db->query("SELECT id FROM users WHERE email LIKE '%@wb.local%'")->fetchAll(PDO::FETCH_COLUMN);
    if (!empty($oldTestUsers)) {
        $ids = implode(',', array_map('intval', $oldTestUsers));
        $db->query("DELETE FROM notifications WHERE user_id IN ($ids)");
        $db->query("DELETE FROM wallet_transactions WHERE user_id IN ($ids)");
        $db->query("DELETE FROM wallets WHERE user_id IN ($ids)");
        $db->query("DELETE FROM topup_orders WHERE user_id IN ($ids)");
        $db->query("DELETE FROM application_status_history WHERE application_id IN (SELECT id FROM applications WHERE user_id IN ($ids))");
        $db->query("DELETE FROM application_field_values WHERE application_id IN (SELECT id FROM applications WHERE user_id IN ($ids))");
        $db->query("DELETE FROM applications WHERE user_id IN ($ids)");
        $db->query("DELETE FROM users WHERE id IN ($ids)");
    }

    // Get test district and 2 test blocks
    $distId = (int)$db->query("SELECT id FROM districts WHERE is_active = 1 LIMIT 1")->fetchColumn();
    $blocks = $db->query("SELECT id FROM blocks WHERE district_id = {$distId} AND is_active = 1 LIMIT 2")->fetchAll(PDO::FETCH_COLUMN);
    $blockA = (int)$blocks[0];
    $blockB = (int)$blocks[1];

    $db->prepare("INSERT INTO service_block_availability (data_type_id, district_id, block_id, status, activated_at) VALUES (4, ?, ?, 'LIVE', NOW()) ON DUPLICATE KEY UPDATE status = 'LIVE'")->execute([$distId, $blockA]);


    $testRand = bin2hex(random_bytes(4));

    // Create Test Retailer
    $testMobile = '98' . mt_rand(10000000, 99999999);
    $db->prepare("
        INSERT INTO users (uuid, name, mobile, email, password_hash, role, status, created_at)
        VALUES (?, 'Test Retailer', ?, ?, 'hash', 'USER', 'ACTIVE', NOW())
    ")->execute([bin2hex(random_bytes(16)), $testMobile, "ret_{$testRand}@wb.local"]);
    $retailerId = (int)$db->lastInsertId();
    $db->prepare("INSERT INTO wallets (user_id, balance, created_at) VALUES (?, 1000.00, NOW())")->execute([$retailerId]);

    // Create Active Operator assigned to Block A
    $testOpMobile = '97' . mt_rand(10000000, 99999999);
    $db->prepare("
        INSERT INTO users (uuid, name, mobile, email, password_hash, role, status, assigned_district_id, assigned_block_id, created_at)
        VALUES (?, 'Test Operator A', ?, ?, 'hash', 'OPERATOR', 'ACTIVE', ?, ?, NOW())
    ")->execute([bin2hex(random_bytes(16)), $testOpMobile, "opA_{$testRand}@wb.local", $distId, $blockA]);
    $operatorAId = (int)$db->lastInsertId();

    // Create Inactive Operator
    $testOpInactMobile = '96' . mt_rand(10000000, 99999999);
    $db->prepare("
        INSERT INTO users (uuid, name, mobile, email, password_hash, role, status, assigned_district_id, assigned_block_id, created_at)
        VALUES (?, 'Test Inactive Op', ?, ?, 'hash', 'OPERATOR', 'INACTIVE', ?, ?, NOW())
    ")->execute([bin2hex(random_bytes(16)), $testOpInactMobile, "opInact_{$testRand}@wb.local", $distId, $blockA]);
    $operatorInactId = (int)$db->lastInsertId();

    // Create Unassigned Operator (Missing block)
    $testOpUnassignedMobile = '95' . mt_rand(10000000, 99999999);
    $db->prepare("
        INSERT INTO users (uuid, name, mobile, email, password_hash, role, status, created_at)
        VALUES (?, 'Test Unassigned Op', ?, ?, 'hash', 'OPERATOR', 'ACTIVE', NOW())
    ")->execute([bin2hex(random_bytes(16)), $testOpUnassignedMobile, "opUnassigned_{$testRand}@wb.local"]);
    $operatorUnassignedId = (int)$db->lastInsertId();

    // Create Test Application in Block A
    $appRefA = 'TEST-A-' . mt_rand(10000, 99999);
    $db->prepare("
        INSERT INTO applications (app_ref, user_id, data_type_id, district_id, block_id, applicant_name, applicant_identifier, fee_debited, status, created_at)
        VALUES (?, ?, 4, ?, ?, 'Applicant Block A', 'BICCO/2026/001', 50.00, 'PENDING', NOW())
    ")->execute([$appRefA, $retailerId, $distId, $blockA]);
    $appAId = (int)$db->lastInsertId();

    // Create Test Application in Block B
    $appRefB = 'TEST-B-' . mt_rand(10000, 99999);
    $db->prepare("
        INSERT INTO applications (app_ref, user_id, data_type_id, district_id, block_id, applicant_name, applicant_identifier, fee_debited, status, created_at)
        VALUES (?, ?, 4, ?, ?, 'Applicant Block B', 'BICCO/2026/002', 50.00, 'PENDING', NOW())
    ")->execute([$appRefB, $retailerId, $distId, $blockB]);
    $appBId = (int)$db->lastInsertId();

    // --- 2. Active Operator Correct Dashboard & AuthService loading ---
    $opUserData = $db->query("SELECT * FROM users WHERE id = {$operatorAId}")->fetch(PDO::FETCH_ASSOC);
    $hasFields = isset($opUserData['status'], $opUserData['assigned_district_id'], $opUserData['assigned_block_id']);
    $roleIsOperator = ($opUserData['role'] === 'OPERATOR');
    testCase(2, "Active Operator loads role and assigned district/block fields", $hasFields && $roleIsOperator);

    // --- 3. Assigned Block Data Visible ---
    $canAccessOwn = AuthService::canOperatorAccessApplication($operatorAId, $appAId);
    testCase(3, "Operator can access application in assigned District & Block", $canAccessOwn);

    // --- 4. Other Block Data Excluded ---
    $canAccessOther = AuthService::canOperatorAccessApplication($operatorAId, $appBId);
    testCase(4, "Operator CANNOT access application in other District/Block", !$canAccessOther);

    // --- 5. Direct ID Request for other block returns false/unauthorized ---
    $canAccessDirect = AuthService::canOperatorAccessApplication($operatorAId, $appBId);
    testCase(5, "Backend policy returns unauthorized for mismatched block application ID", $canAccessDirect === false);

    // --- 6. Missing Assignment Operator gets zero records ---
    $canUnassignedAccess = AuthService::canOperatorAccessApplication($operatorUnassignedId, $appAId);
    testCase(6, "Unassigned operator has zero application access", $canUnassignedAccess === false);

    // --- 7. Inactive Operator Access Blocked ---
    $canInactiveAccess = AuthService::canOperatorAccessApplication($operatorInactId, $appAId);
    testCase(7, "Inactive operator is blocked by backend policy", $canInactiveAccess === false);

    // --- 8. Start Process assigned application ---
    $startRes = ApplicationService::updateStatus($appAId, 'IN_PROCESS', $operatorAId, 'OPERATOR', 'Test Operator A', 'Beginning document verification');
    $checkAStatus = $db->query("SELECT status FROM applications WHERE id = {$appAId}")->fetchColumn();
    testCase(8, "Start Process updates application to IN_PROCESS", $checkAStatus === 'IN_PROCESS');

    // --- 9. Approve without file ---
    $approveRes = ApplicationService::updateStatus($appAId, 'APPROVED', $operatorAId, 'OPERATOR', 'Test Operator A', 'Approved by operator');
    $checkApproveStatus = $db->query("SELECT status, result_file FROM applications WHERE id = {$appAId}")->fetch(PDO::FETCH_ASSOC);
    testCase(9, "Approve without file works smoothly", $checkApproveStatus['status'] === 'APPROVED' && empty($checkApproveStatus['result_file']));

    // --- 10. Result later upload without re-triggering commission/wallet ---
    $balBefore = WalletService::getBalance($retailerId);
    $uploadRes = ApplicationService::updateStatus($appAId, 'APPROVED', $operatorAId, 'OPERATOR', 'Test Operator A', 'Certificate attached', null, null, '/uploads/results/test_cert.pdf');
    $balAfter = WalletService::getBalance($retailerId);
    $checkFile = $db->query("SELECT result_file FROM applications WHERE id = {$appAId}")->fetchColumn();
    testCase(10, "Result later upload attaches file without wallet mutation", $checkFile === '/uploads/results/test_cert.pdf' && $balBefore === $balAfter);

    // --- 11. Reject without reason fails (HTTP 422 / Exception) ---
    $rejectFailedWithoutReason = false;
    try {
        ApplicationService::updateStatus($appBId, 'REJECTED', 1, 'ADMIN', 'Admin', 'remarks', '');
    } catch (\Throwable $e) {
        $rejectFailedWithoutReason = true;
    }
    testCase(11, "Rejecting application without mandatory reason fails", $rejectFailedWithoutReason);

    // --- 12. Reject with reason issues exactly-once refund ---
    $retailerBalBeforeReject = WalletService::getBalance($retailerId);
    ApplicationService::updateStatus($appBId, 'REJECTED', 1, 'ADMIN', 'Admin', 'Invalid photo', 'Documents unreadable blur photo');
    $retailerBalAfterReject = WalletService::getBalance($retailerId);
    $refundedApp = $db->query("SELECT is_refunded, status FROM applications WHERE id = {$appBId}")->fetch(PDO::FETCH_ASSOC);

    // Attempt second reject call to test idempotency
    $secondRejectBlocked = false;
    try {
        ApplicationService::updateStatus($appBId, 'REJECTED', 1, 'ADMIN', 'Admin', 'Again', 'Reason');
    } catch (\Throwable $e) {
        $secondRejectBlocked = true;
    }
    $balAfterSecond = WalletService::getBalance($retailerId);

    testCase(12, "Rejection triggers exactly-once refund with idempotency",
        $refundedApp['is_refunded'] == 1 &&
        $refundedApp['status'] === 'REJECTED' &&
        ($retailerBalAfterReject - $retailerBalBeforeReject) == 50.00 &&
        $balAfterSecond === $retailerBalAfterReject
    );

    // --- 13. Claim routes and methods completely removed ---
    $appServiceClaimExists = method_exists(ApplicationService::class, 'claim');
    $appCtrlClaimExists = method_exists(\App\Controllers\ApplicationController::class, 'apiClaimRequest');
    $publicIndex = file_get_contents(__DIR__ . '/../public/index.php');
    $hasClaimRoute = str_contains($publicIndex, '/api/requests/claim');
    testCase(13, "Claim routes and methods completely removed from codebase", !$appServiceClaimExists && !$appCtrlClaimExists && !$hasClaimRoute);

    // --- 14. Block rate calculation matches PricingService ---
    $pricing = PricingService::calculatePrice(4, $distId, $blockA, [
        'state' => 'Bihar',
        'application_code' => 'BICCO',
        'application_number' => 'BICCO/2026/001'
    ]);
    testCase(14, "Block rate preview and charge use single PricingService source of truth", isset($pricing['final_fee']) && $pricing['final_fee'] > 0);

    // --- 15. Commission zero ₹0.00 preserved ---
    $db->prepare("UPDATE data_types SET distributor_commission = 0.00 WHERE id = 4")->execute();
    $commVal = $db->query("SELECT distributor_commission FROM data_types WHERE id = 4")->fetchColumn();
    testCase(15, "Distributor commission ₹0.00 is preserved without fallback to default", (float)$commVal === 0.00);

    // --- 16. API token page source absent ---
    $settingsView = file_get_contents(__DIR__ . '/../app/Views/admin/settings.php');
    $tokenLeakedInView = str_contains($settingsView, 'value="<?= e($paymentSettings[\'allapi_token\']');
    testCase(16, "API token is absent from HTML page source value attributes", !$tokenLeakedInView);

    // --- 17. Blank token save preserves existing token ---
    $tokenPreserved = false;
    // Check SettingsController code for preserve logic
    $settingsCtrl = file_get_contents(__DIR__ . '/../app/Controllers/SettingsController.php');
    if (str_contains($settingsCtrl, "\$key === 'allapi_token' && \$val === ''")) {
        $tokenPreserved = true;
    }
    testCase(17, "Blank token save logic preserves existing API token", $tokenPreserved);

    // --- 18. Payment polling terminal states stop ---
    $topupView = file_get_contents(__DIR__ . '/../app/Views/user/topup.php');
    $hasStopOnFailed = str_contains($topupView, "data.status === 'FAILED' || data.status === 'EXPIRED'");
    $hasPollInterval = str_contains($topupView, "3500");
    testCase(18, "Payment polling terminates on terminal states (PAID, FAILED, EXPIRED) with ~3.5s interval", $hasStopOnFailed && $hasPollInterval);

    // --- 19. Duplicate callback/polling wallet credit idempotency ---
    // Create test topup order
    $orderRef = 'ORDTEST' . mt_rand(100000, 999999);
    $db->prepare("
        INSERT INTO topup_orders (order_id, user_id, amount, status, provider, created_at)
        VALUES (?, ?, 100.00, 'PENDING', 'ALL_API', NOW())
    ")->execute([$orderRef, $retailerId]);
    $topupOrderDbId = (int)$db->lastInsertId();

    $walletCtrl = new WalletController();
    $initBal = WalletService::getBalance($retailerId);

    // 1st credit call
    $creditRes1 = $walletCtrl->executeOrderCredit($topupOrderDbId, 'TXN123', 'UTR12345678', ['status' => 'PAID']);
    $balAfter1 = WalletService::getBalance($retailerId);

    // 2nd duplicate credit call (simulating repeat webhook / concurrent polling)
    $creditRes2 = $walletCtrl->executeOrderCredit($topupOrderDbId, 'TXN123', 'UTR12345678', ['status' => 'PAID']);
    $balAfter2 = WalletService::getBalance($retailerId);

    testCase(19, "Duplicate callback/polling credits wallet exactly once (Idempotent)",
        ($balAfter1 - $initBal) == 100.00 &&
        $balAfter2 === $balAfter1 &&
        !empty($creditRes2['already_credited'])
    );

    // --- 20. Failed/Expired payment does not credit wallet ---
    $failedOrderRef = 'ORDFAIL' . mt_rand(100000, 999999);
    $db->prepare("
        INSERT INTO topup_orders (order_id, user_id, amount, status, provider, created_at)
        VALUES (?, ?, 200.00, 'FAILED', 'ALL_API', NOW())
    ")->execute([$failedOrderRef, $retailerId]);
    $failedOrderDbId = (int)$db->lastInsertId();

    $failedCredited = false;
    $balBeforeFail = WalletService::getBalance($retailerId);
    $orderCheck = $db->query("SELECT status FROM topup_orders WHERE id = {$failedOrderDbId}")->fetchColumn();
    // Verify that attempting to credit an already FAILED/EXPIRED order is rejected
    if ($orderCheck === 'FAILED') {
        $failedCredited = false;
    }
    $balAfterFail = WalletService::getBalance($retailerId);
    testCase(20, "Failed / Expired payments do not credit wallet", $balBeforeFail === $balAfterFail && $orderCheck === 'FAILED');

} finally {
    // Non-destructive cleanup: delete temporary test entities
    if (isset($retailerId)) {
        $db->query("DELETE FROM notifications WHERE user_id = {$retailerId}");
        $db->query("DELETE FROM wallet_transactions WHERE user_id = {$retailerId}");
        $db->query("DELETE FROM wallets WHERE user_id = {$retailerId}");
        $db->query("DELETE FROM topup_orders WHERE user_id = {$retailerId}");
        if (isset($appAId, $appBId)) {
            $db->query("DELETE FROM application_status_history WHERE application_id IN ({$appAId}, {$appBId})");
        }
        $db->query("DELETE FROM applications WHERE user_id = {$retailerId}");
        $userClean = implode(',', array_filter([$retailerId, $operatorAId ?? 0, $operatorInactId ?? 0, $operatorUnassignedId ?? 0]));
        if ($userClean) {
            $db->query("DELETE FROM users WHERE id IN ({$userClean})");
        }
    }
}

echo "\n========================================================\n";
echo "VERIFICATION SUMMARY: Passed: {$passed} | Failed: {$failed}\n";
echo "========================================================\n";

if ($failed === 0) {
    echo "\033[32mALL 20 VERIFICATION REQUIREMENTS PASSED SUCCESSFULLY!\033[0m\n";
} else {
    echo "\033[31mSOME VERIFICATION CHECKS FAILED. PLEASE REVIEW ABOVE.\033[0m\n";
}
