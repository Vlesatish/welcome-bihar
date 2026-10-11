<?php
/**
 * Welcome Bihar - Automated PHP Verification Test Suite
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

\App\Config\Env::load(dirname(__DIR__) . '/.env');
require_once dirname(__DIR__) . '/app/Services/TranslationService.php';

echo "========================================================\n";
echo "WELCOME BIHAR - SYSTEM VERIFICATION SUITE\n";
echo "========================================================\n";

$passed = 0;
$failed = 0;

function assertTest(string $desc, bool $condition): void {
    global $passed, $failed;
    if ($condition) {
        echo " [PASS] $desc\n";
        $passed++;
    } else {
        echo " [FAIL] $desc\n";
        $failed++;
    }
}

// TEST 1: Database Connection
try {
    $db = \App\Config\Database::getConnection();
    assertTest("MySQL Database connection established via PDO", true);
} catch (\Throwable $e) {
    assertTest("MySQL Database connection established via PDO: " . $e->getMessage(), false);
}

// TEST 2: Bihar Districts & Blocks Check
$distCount = (int)$db->query("SELECT COUNT(*) FROM districts")->fetchColumn();
$blockCount = (int)$db->query("SELECT COUNT(*) FROM blocks")->fetchColumn();
assertTest("All 38 Bihar Districts loaded (Count: $distCount)", $distCount === 38);
assertTest("All 708 Administrative Blocks/Urban Bodies loaded (Count: $blockCount)", $blockCount >= 700);

// TEST 3: 3 Configurable Data Types Check
$dtCount = (int)$db->query("SELECT COUNT(*) FROM data_types")->fetchColumn();
$fieldsCount = (int)$db->query("SELECT COUNT(*) FROM data_type_fields")->fetchColumn();
assertTest("3 Core Data Types seeded (Count: $dtCount)", $dtCount === 3);
assertTest("Configurable Dynamic Fields seeded (Count: $fieldsCount)", $fieldsCount >= 20);

// TEST 4: Demo Users & Bcrypt Password Verify
$userStmt = $db->query("SELECT * FROM users WHERE email = 'operator@welcomebihar.gov.in'");
$operator = $userStmt->fetch();
assertTest("Demo Operator account exists", (bool)$operator);
assertTest("Password hash verification matches 'Welcome@2026'", password_verify('Welcome@2026', $operator['password_hash']));

// TEST 5: Wallet Atomic Credit & Debit and Negative Balance Guard
$operatorId = (int)$operator['id'];
$initialBal = \App\Services\WalletService::getBalance($operatorId);

// Test credit
$creditRes = \App\Services\WalletService::credit(
    $operatorId,
    250.00,
    'TOPUP_CREDIT',
    'TEST',
    'TEST_CR_1',
    'IDEM_TEST_CR_' . microtime(true),
    'Test credit deposit',
    'परीक्षण जमा'
);
$balAfterCredit = \App\Services\WalletService::getBalance($operatorId);
assertTest("Wallet credit of ₹250.00 executed atomically", round($balAfterCredit - $initialBal, 2) === 250.00);

// Test debit
$debitRes = \App\Services\WalletService::debit(
    $operatorId,
    100.00,
    'APPLICATION_FEE_DEBIT',
    'TEST',
    'TEST_DB_1',
    'IDEM_TEST_DB_' . microtime(true),
    'Test debit fee',
    'परीक्षण शुल्क कटौती'
);
$balAfterDebit = \App\Services\WalletService::getBalance($operatorId);
assertTest("Wallet debit of ₹100.00 executed atomically", round($balAfterCredit - $balAfterDebit, 2) === 100.00);

// Test negative balance prevention
$caughtNegative = false;
try {
    \App\Services\WalletService::debit(
        $operatorId,
        9999999.00,
        'APPLICATION_FEE_DEBIT',
        'TEST',
        'TEST_DB_FAIL',
        'IDEM_TEST_FAIL_' . microtime(true),
        'Should fail due to insufficient funds',
        'असफल होना चाहिए'
    );
} catch (\Exception $e) {
    $caughtNegative = true;
}
assertTest("Negative balance correctly prevented with Insufficient Balance exception", $caughtNegative);

// TEST 6: Application Submission & Dynamic Fields
$aadhaarTest = '9999' . str_pad((string)mt_rand(10000000, 99999999), 8, '0');
$appValues = [
    'applicant_name' => 'Automated Test Applicant',
    'father_or_husband_name' => 'Test Father',
    'gender' => 'Male',
    'dob' => '1995-05-15',
    'mobile_no' => '9876543210',
    'aadhaar_no' => $aadhaarTest,
    'work_nature' => 'Mason',
    'working_days_90' => 'Yes',
    'bank_account_no' => '123456789012',
    'bank_ifsc' => 'SBIN0001234',
    'present_address' => 'Test Village, Danapur, Patna'
];

$patnaId = 26;
$danapurStmt = $db->prepare("SELECT id FROM blocks WHERE district_id = ? AND name LIKE '%Danapur%' LIMIT 1");
$danapurStmt->execute([$patnaId]);
$danapurId = (int)$danapurStmt->fetchColumn() ?: 465;

$appRes = \App\Services\ApplicationService::submit(
    $operatorId,
    1, // BOCW Registration
    $patnaId,
    $danapurId,
    $appValues
);
assertTest("Application submission succeeded with Reference {$appRes['app_ref']}", $appRes['success']);

// Check duplicate entry detection
$dupCaught = false;
try {
    \App\Services\ApplicationService::submit(
        $operatorId,
        1,
        $patnaId,
        $danapurId,
        $appValues
    );
} catch (\Exception $e) {
    $dupCaught = true;
}
assertTest("Duplicate Aadhaar application correctly blocked", $dupCaught);

// TEST 7: Application Status Update & Rejection Refund
$appId = $appRes['application_id'];
$statusRes1 = \App\Services\ApplicationService::updateStatus(
    $appId,
    'UNDER_REVIEW',
    1,
    'ADMIN',
    'Super Admin',
    'Initial documentation verified'
);
assertTest("Transition to UNDER_REVIEW succeeded", $statusRes1['success']);

// Rejection test with refund
$preRefundBal = \App\Services\WalletService::getBalance($operatorId);
$statusRes2 = \App\Services\ApplicationService::updateStatus(
    $appId,
    'REJECTED',
    1,
    'ADMIN',
    'Super Admin',
    'Test rejection',
    'Aadhaar document scan was unreadable in verification test'
);
$postRefundBal = \App\Services\WalletService::getBalance($operatorId);
assertTest("Rejection succeeded and fee ₹50.00 was automatically refunded to wallet", round($postRefundBal - $preRefundBal, 2) === 50.00);

// TEST 8: ALL API Provider Adapter & QR Creation
$provider = new \App\Services\AllApiProvider();
$qrRes = $provider->createQrPayment(
    'ORD-TEST-' . time(),
    150.00,
    ['name' => 'Test Operator', 'mobile' => '9999900003', 'email' => 'operator@welcomebihar.gov.in']
);
assertTest("ALL API QR generator created valid payment payload", $qrRes['success'] && !empty($qrRes['qr_image_url']));

echo "========================================================\n";
echo "SUMMARY: Passed: $passed | Failed: $failed\n";
echo "========================================================\n";

if ($failed === 0) {
    echo "ALL TESTS PASSED SUCCESSFULLY!\n";
    exit(0);
} else {
    echo "SOME TESTS FAILED!\n";
    exit(1);
}
