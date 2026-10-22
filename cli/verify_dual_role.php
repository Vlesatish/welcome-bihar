<?php
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

use App\Config\Config;
Config::init();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use App\Config\Database;
use App\Services\AuthService;
use App\Services\ApplicationService;
use App\Services\StaffRoutingService;
use App\Services\WalletService;
use App\Services\AuditService;

echo "====================================================\n";
echo "WELCOME BIHAR: DUAL-ROLE SYSTEM VERIFICATION SUITE\n";
echo "====================================================\n\n";

$db = Database::getConnection();
$errors = [];
$passed = 0;

function assertTest(bool $condition, string $testName, string &$errors, int &$passed): void {
    if ($condition) {
        echo " [PASS] {$testName}\n";
        $passed++;
    } else {
        echo " [FAIL] {$testName}\n";
        $errors .= " - Failed: {$testName}\n";
    }
}

$errBuffer = '';

// Setup Test Users
// Helper for idempotent test user retrieval/creation
function getOrCreateTestUser(PDO $db, string $mobile, string $role, string $name, string $email, int $distId = 0, int $blkId = 0): int {
    $stmt = $db->prepare("SELECT id FROM users WHERE mobile = ? LIMIT 1");
    $stmt->execute([$mobile]);
    $existing = $stmt->fetchColumn();
    if ($existing) {
        $db->prepare("UPDATE users SET role = ?, name = ?, email = ?, status = 'ACTIVE', assigned_district_id = ?, assigned_block_id = ? WHERE id = ?")
           ->execute([$role, $name, $email, $distId ?: null, $blkId ?: null, $existing]);
        return (int)$existing;
    }
    $uuid = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
    $ins = $db->prepare("
        INSERT INTO users (uuid, role, name, mobile, email, password_hash, status, assigned_district_id, assigned_block_id, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, '\$2y\$10\$abcdefghijklmnopqrstuu', 'ACTIVE', ?, ?, NOW(), NOW())
    ");
    $ins->execute([$uuid, $role, $name, $mobile, $email, $distId ?: null, $blkId ?: null]);
    return (int)$db->lastInsertId();
}

// 1. Single-role Retailer (USER)
$testUserId = getOrCreateTestUser($db, '9900011101', 'USER', 'Test Retailer Only', 'retailer1@test.local');
WalletService::getOrCreateWallet($testUserId);
if (WalletService::getBalance($testUserId) < 1000) {
    WalletService::credit($testUserId, 5000, 'TEST_CREDIT', 'TEST', 'TEST-REF-' . time(), 'IDEM-' . time() . '-1', 'Test credit', 'टेस्ट क्रेडिट');
}
$db->prepare("INSERT INTO user_role_assignments (user_id, role_code, is_active, granted_at, created_at, updated_at) VALUES (?, 'USER', 1, NOW(), NOW(), NOW()) ON DUPLICATE KEY UPDATE is_active = 1")->execute([$testUserId]);
$db->prepare("DELETE FROM user_role_assignments WHERE user_id = ? AND role_code = 'OPERATOR'")->execute([$testUserId]);

// 2. Single-role Operator (OPERATOR)
$testOpId = getOrCreateTestUser($db, '9900011102', 'OPERATOR', 'Test Dedicated Staff', 'staff1@test.local', 1, 1);
WalletService::getOrCreateWallet($testOpId);
$db->prepare("INSERT INTO user_role_assignments (user_id, role_code, is_active, granted_at, created_at, updated_at) VALUES (?, 'OPERATOR', 1, NOW(), NOW(), NOW()) ON DUPLICATE KEY UPDATE is_active = 1")->execute([$testOpId]);
$db->prepare("DELETE FROM user_role_assignments WHERE user_id = ? AND role_code = 'USER'")->execute([$testOpId]);

// 3. Dual-Role User (USER with active OPERATOR capability)
$testDualId = getOrCreateTestUser($db, '9900011103', 'USER', 'Test Dual Account', 'dual@test.local', 1, 1);
WalletService::getOrCreateWallet($testDualId);
if (WalletService::getBalance($testDualId) < 1000) {
    WalletService::credit($testDualId, 5000, 'TEST_CREDIT', 'TEST', 'TEST-REF-DUAL-' . time(), 'IDEM-DUAL-' . time(), 'Dual test credit', 'डुअल टेस्ट क्रेडिट');
}
$db->prepare("INSERT INTO user_role_assignments (user_id, role_code, is_active, granted_at, created_at, updated_at) VALUES (?, 'USER', 1, NOW(), NOW(), NOW()) ON DUPLICATE KEY UPDATE is_active = 1")->execute([$testDualId]);
$db->prepare("INSERT INTO user_role_assignments (user_id, role_code, is_active, granted_at, created_at, updated_at) VALUES (?, 'OPERATOR', 1, NOW(), NOW(), NOW()) ON DUPLICATE KEY UPDATE is_active = 1")->execute([$testDualId]);

// 4. Another Operator for block 1, 1 (backup)
$testBackupOpId = getOrCreateTestUser($db, '9900011104', 'OPERATOR', 'Test Backup Staff', 'backup@test.local', 1, 1);
WalletService::getOrCreateWallet($testBackupOpId);
$db->prepare("INSERT INTO user_role_assignments (user_id, role_code, is_active, granted_at, created_at, updated_at) VALUES (?, 'OPERATOR', 1, NOW(), NOW(), NOW()) ON DUPLICATE KEY UPDATE is_active = 1")->execute([$testBackupOpId]);

// Ensure district 1 block 1 is LIVE and has rates
$db->prepare("
    INSERT INTO service_block_availability (data_type_id, district_id, block_id, status, created_at, updated_at)
    VALUES (4, 1, 1, 'LIVE', NOW(), NOW())
    ON DUPLICATE KEY UPDATE status = 'LIVE', updated_at = NOW()
")->execute();

$db->prepare("
    INSERT INTO service_rate_rules (data_type_id, district_id, block_id, application_code, fee_amount, distributor_commission, is_active, created_at, updated_at)
    VALUES (4, 1, 1, 'BICCO', 50.00, 10.00, 1, NOW(), NOW())
    ON DUPLICATE KEY UPDATE fee_amount = 50.00, is_active = 1, updated_at = NOW()
")->execute();

$db->prepare("
    INSERT INTO service_rate_rules (data_type_id, district_id, block_id, application_code, fee_amount, distributor_commission, is_active, created_at, updated_at)
    VALUES (4, 1, 1, 'BCCCO', 60.00, 15.00, 1, NOW(), NOW())
    ON DUPLICATE KEY UPDATE fee_amount = 60.00, is_active = 1, updated_at = NOW()
")->execute();

// Code assignments for operator, dual, and backup
StaffRoutingService::saveOperatorCodeAssignments($testOpId, 1, 1, ['BICCO', 'BCCCO'], 1);
StaffRoutingService::saveOperatorCodeAssignments($testDualId, 1, 1, ['BICCO', 'BCCCO'], 1);
StaffRoutingService::saveOperatorCodeAssignments($testBackupOpId, 1, 1, ['BICCO', 'BCCCO'], 1);

echo "--- 1. Testing Role Capabilities & Isolation ---\n";
// Normal USER
assertTest(AuthService::hasRole($testUserId, 'USER') === true, 'Normal USER has USER role', $errBuffer, $passed);
assertTest(AuthService::hasRole($testUserId, 'OPERATOR') === false, 'Normal USER does NOT have OPERATOR role', $errBuffer, $passed);
assertTest(AuthService::isDualRole($testUserId) === false, 'Normal USER isDualRole is false', $errBuffer, $passed);

// Normal OPERATOR
assertTest(AuthService::hasRole($testOpId, 'OPERATOR') === true, 'Dedicated OPERATOR has OPERATOR role', $errBuffer, $passed);
assertTest(AuthService::hasRole($testOpId, 'USER') === false, 'Dedicated OPERATOR does not have USER role', $errBuffer, $passed);
assertTest(AuthService::isDualRole($testOpId) === false, 'Dedicated OPERATOR isDualRole is false', $errBuffer, $passed);

// Dual-role Account
assertTest(AuthService::hasRole($testDualId, 'USER') === true, 'Dual-role user has USER role', $errBuffer, $passed);
assertTest(AuthService::hasRole($testDualId, 'OPERATOR') === true, 'Dual-role user has OPERATOR role', $errBuffer, $passed);
assertTest(AuthService::isDualRole($testDualId) === true, 'Dual-role user isDualRole is true', $errBuffer, $passed);

echo "\n--- 2. Testing Session Portal Modes & Switching ---\n";
$_SESSION = [
    'user_id' => $testDualId,
    'user_role' => 'USER',
    'active_portal_mode' => 'RETAILER'
];
assertTest(AuthService::activePortalMode() === 'RETAILER', 'Dual-role defaults or sets activePortalMode to RETAILER', $errBuffer, $passed);
assertTest(AuthService::canAccessRetailerMode() === true, 'Dual-role can access Retailer mode', $errBuffer, $passed);
assertTest(AuthService::canAccessOperatorMode() === true, 'Dual-role can access Operator mode', $errBuffer, $passed);

// Switch mode to OPERATOR
AuthService::setActivePortalMode('OPERATOR');
assertTest(AuthService::activePortalMode() === 'OPERATOR', 'Active mode successfully switched to OPERATOR', $errBuffer, $passed);
assertTest(AuthService::validateAndSyncActiveMode() === 'OPERATOR', 'Validation keeps OPERATOR mode when active capability exists', $errBuffer, $passed);

// Switch mode back to RETAILER
AuthService::setActivePortalMode('RETAILER');
assertTest(AuthService::activePortalMode() === 'RETAILER', 'Active mode successfully switched to RETAILER', $errBuffer, $passed);

echo "\n--- 3. Testing Self-Processing Protection on Submissions & Routing ---\n";
// The dual-role user submits an application as a Retailer
$initialWallet = WalletService::getBalance($testDualId);
$randNum = bin2hex(random_bytes(4));
$submissionData = [
    'applicant_name' => 'Self Submission Test',
    'mobile' => '9876543210',
    'application_number' => 'BICCO/2026/' . $randNum,
    'application_code' => 'BICCO',
    'state' => 'Bihar',
    'district_name' => 'Patna',
    'block_name' => 'Patna Sadar'
];

$subResult = ApplicationService::submit($testDualId, 4, 1, 1, $submissionData, []);
$createdAppId = (int)$subResult['application_id'];
$newWallet = WalletService::getBalance($testDualId);

assertTest($createdAppId > 0, 'Dual user successfully submitted application in Retailer mode', $errBuffer, $passed);
assertTest($newWallet < $initialWallet, 'Retailer wallet was debited on submission', $errBuffer, $passed);

// Check assigned operator: MUST NOT be $testDualId!
$stmt = $db->prepare("SELECT user_id, assigned_operator_id, status FROM applications WHERE id = ?");
$stmt->execute([$createdAppId]);
$createdApp = $stmt->fetch(PDO::FETCH_ASSOC);

assertTest((int)$createdApp['user_id'] === $testDualId, 'Application creator is correctly recorded', $errBuffer, $passed);
assertTest((int)$createdApp['assigned_operator_id'] !== $testDualId, 'SELF-PROCESSING PROTECTION: Application creator was excluded from auto-assignment', $errBuffer, $passed);
assertTest((int)$createdApp['assigned_operator_id'] > 0, 'Application routed to eligible non-creator operator', $errBuffer, $passed);

echo "\n--- 4. Testing Server-Side Block on Self-Processing Actions ---\n";
// Force assigned_operator_id to $testDualId in DB to test fail-safe defense in depth
$db->prepare("UPDATE applications SET assigned_operator_id = ? WHERE id = ?")->execute([$testDualId, $createdAppId]);

// Try to access or update as Operator when actor is the creator
$canAccess = AuthService::canOperatorAccessApplication($testDualId, $createdAppId);
assertTest($canAccess === false, 'AuthService::canOperatorAccessApplication rejects self-processing', $errBuffer, $passed);

$blockedUpdate = false;
$blockedMessage = '';
try {
    ApplicationService::updateStatus(
        $createdAppId,
        'IN_PROCESS',
        $testDualId,
        'OPERATOR',
        'Test Dual Account',
        'Trying to process own application',
        null,
        'Test remarks'
    );
} catch (\Exception $e) {
    $blockedUpdate = true;
    $blockedMessage = $e->getMessage();
}

assertTest($blockedUpdate === true, 'ApplicationService::updateStatus threw exception on self-processing attempt', $errBuffer, $passed);
assertTest(str_contains($blockedMessage, 'You cannot process an application submitted by your own retailer account.'), 'Accurate error message returned for self-processing attempt', $errBuffer, $passed);

// Try manual admin assignment of application to its creator
$adminReassignBlocked = false;
try {
    StaffRoutingService::reassignApplication($createdAppId, $testDualId, 'Admin reassign test', 1);
} catch (\Exception $e) {
    $adminReassignBlocked = true;
}
assertTest($adminReassignBlocked === true, 'StaffRoutingService::reassignApplication rejects manual self-assignment', $errBuffer, $passed);

echo "\n--- 5. Testing Atomic Batch Submission Routing Isolation ---\n";
$bRand1 = bin2hex(random_bytes(4));
$bRand2 = bin2hex(random_bytes(4));
$batchItems = [
    [
        'district_id' => 1,
        'block_id' => 1,
        'application_code' => 'BICCO',
        'applicant_name' => 'Batch Item 1',
        'mobile' => '9876543211',
        'application_number' => 'BICCO/2026/' . $bRand1
    ],
    [
        'district_id' => 1,
        'block_id' => 1,
        'application_code' => 'BCCCO',
        'applicant_name' => 'Batch Item 2',
        'mobile' => '9876543212',
        'application_number' => 'BCCCO/2026/' . $bRand2
    ]
];

$batchResult = ApplicationService::submitBatch($testDualId, 4, $batchItems, []);
assertTest($batchResult['success'] === true, 'Batch submitted successfully', $errBuffer, $passed);

$bStmt = $db->prepare("SELECT id, user_id, assigned_operator_id FROM applications WHERE batch_ref = ?");
$bStmt->execute([$batchResult['batch_ref']]);
$batchApps = $bStmt->fetchAll(PDO::FETCH_ASSOC);

$noBatchSelfAssign = true;
foreach ($batchApps as $ba) {
    if ((int)$ba['assigned_operator_id'] === $testDualId) {
        $noBatchSelfAssign = false;
    }
}
assertTest($noBatchSelfAssign === true, 'Batch routing excluded the batch creator for all rows', $errBuffer, $passed);

echo "\n--- 6. Testing Operator Wallet Isolation ---\n";
// Have the dedicated operator process the application
$preOpWallet = WalletService::getBalance($testOpId);
$validAppId = (int)$batchApps[0]['id'];
$db->prepare("UPDATE applications SET assigned_operator_id = ? WHERE id = ?")->execute([$testOpId, $validAppId]);

// Operator moves to IN_PROCESS
ApplicationService::updateStatus($validAppId, 'IN_PROCESS', $testOpId, 'OPERATOR', 'Test Operator');
$postProcessWallet = WalletService::getBalance($testOpId);
assertTest($preOpWallet == $postProcessWallet, 'Operator moving application to IN_PROCESS did not alter operator wallet', $errBuffer, $passed);

// Operator approves and uploads result
ApplicationService::updateStatus($validAppId, 'APPROVED', $testOpId, 'OPERATOR', 'Test Operator', 'Approved successfully', null, 'Remarks', '/uploads/results/sample.pdf');
$postApproveWallet = WalletService::getBalance($testOpId);
assertTest($preOpWallet == $postApproveWallet, 'Operator approving and uploading result did not debit operator wallet', $errBuffer, $passed);

echo "\n--- 7. Testing Sole-Operator Exclusion (Leaves Unassigned & Logs Event) ---\n";
// Setup Block 2 in District 1 where ONLY $testDualId handles BICCO
$db->prepare("
    INSERT INTO service_block_availability (data_type_id, district_id, block_id, status, created_at, updated_at)
    VALUES (4, 1, 2, 'LIVE', NOW(), NOW())
    ON DUPLICATE KEY UPDATE status = 'LIVE', updated_at = NOW()
")->execute();

$db->prepare("
    INSERT INTO service_rate_rules (data_type_id, district_id, block_id, application_code, fee_amount, distributor_commission, is_active, created_at, updated_at)
    VALUES (4, 1, 2, 'BICCO', 50.00, 10.00, 1, NOW(), NOW())
    ON DUPLICATE KEY UPDATE fee_amount = 50.00, is_active = 1, updated_at = NOW()
")->execute();

// Remove any other operators for Block 2
$db->prepare("DELETE FROM operator_application_code_assignments WHERE district_id = 1 AND block_id = 2")->execute();

// Assign Block 2 to $testDualId ONLY
$db->prepare("UPDATE users SET assigned_district_id = 1, assigned_block_id = 2 WHERE id = ?")->execute([$testDualId]);
StaffRoutingService::saveOperatorCodeAssignments($testDualId, 1, 2, ['BICCO'], 1);

$soleSubRand = bin2hex(random_bytes(4));
$soleSubData = [
    'applicant_name' => 'Sole Operator Submission Test',
    'mobile' => '9876543219',
    'application_number' => 'BICCO/2026/' . $soleSubRand,
    'application_code' => 'BICCO',
    'state' => 'Bihar',
    'district_name' => 'Patna',
    'block_name' => 'Phulwari'
];

$soleSubResult = ApplicationService::submit($testDualId, 4, 1, 2, $soleSubData, []);
$soleAppId = (int)$soleSubResult['application_id'];

$stmtSole = $db->prepare("SELECT user_id, assigned_operator_id, status FROM applications WHERE id = ?");
$stmtSole->execute([$soleAppId]);
$soleApp = $stmtSole->fetch(PDO::FETCH_ASSOC);

assertTest($soleAppId > 0, 'Sole operator application submitted successfully', $errBuffer, $passed);
assertTest($soleApp['assigned_operator_id'] === null, 'SELF-PROCESSING: Sole operator left application unassigned (NULL)', $errBuffer, $passed);
assertTest($soleApp['status'] === 'PENDING', 'SELF-PROCESSING: Unassigned application kept status PENDING', $errBuffer, $passed);

echo "\n--- 8. Testing Revocation & Immediate Session Enforcement ---\n";
// Revoke operator access for $testDualId
$adminId = 1;
$db->prepare("UPDATE user_role_assignments SET is_active = 0, revoked_by = ?, revoked_at = NOW() WHERE user_id = ? AND role_code = 'OPERATOR'")->execute([$adminId, $testDualId]);

assertTest(AuthService::hasRole($testDualId, 'OPERATOR') === false, 'After revocation, hasRole(OPERATOR) is false', $errBuffer, $passed);
assertTest(AuthService::hasRole($testDualId, 'USER') === true, 'After revocation, Retailer USER role remains intact', $errBuffer, $passed);
assertTest(AuthService::canAccessOperatorMode() === false, 'canAccessOperatorMode is immediately false', $errBuffer, $passed);

// Session active mode validation should instantly downgrade to RETAILER
$_SESSION['active_portal_mode'] = 'OPERATOR';
$syncedMode = AuthService::validateAndSyncActiveMode();
assertTest($syncedMode === 'RETAILER', 'validateAndSyncActiveMode immediately clears OPERATOR mode and resets to RETAILER', $errBuffer, $passed);
assertTest($_SESSION['active_portal_mode'] === 'RETAILER', 'Session active_portal_mode reset to RETAILER', $errBuffer, $passed);

echo "\n--- 9. Testing Audit Logging for Dual-Role Events ---\n";
$auditStmt = $db->prepare("SELECT action FROM audit_logs WHERE action IN ('PORTAL_MODE_CHANGED', 'SELF_ASSIGNMENT_PREVENTED') ORDER BY id DESC LIMIT 20");
$auditStmt->execute();
$events = $auditStmt->fetchAll(PDO::FETCH_COLUMN);

assertTest(in_array('SELF_ASSIGNMENT_PREVENTED', $events, true), 'SELF_ASSIGNMENT_PREVENTED audit log recorded in system', $errBuffer, $passed);

echo "\n====================================================\n";
echo "VERIFICATION SUMMARY: Passed {$passed} assertions.\n";
if (!empty($errBuffer)) {
    echo "ERRORS:\n{$errBuffer}\n";
    exit(1);
} else {
    echo "ALL SCENARIOS PASSED WITH ZERO ERRORS!\n";
    echo "====================================================\n";
}
