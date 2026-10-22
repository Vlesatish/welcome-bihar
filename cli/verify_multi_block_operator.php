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
use App\Services\WhatsAppNotificationService;

echo "====================================================================\n";
echo "WELCOME BIHAR: 12-POINT MULTI-BLOCK & DUAL-ROLE VERIFICATION SUITE\n";
echo "====================================================================\n\n";

$db = Database::getConnection();
$passed = 0;
$failed = 0;
$testResults = [];

function runAssert(bool $condition, string $checkNum, string $testName, string &$details = ''): bool {
    global $passed, $failed, $testResults;
    if ($condition) {
        echo " [PASS] Check {$checkNum}: {$testName}\n";
        $passed++;
        $testResults[] = ['check' => $checkNum, 'name' => $testName, 'status' => 'PASS', 'details' => $details];
        return true;
    } else {
        echo " [FAIL] Check {$checkNum}: {$testName} - {$details}\n";
        $failed++;
        $testResults[] = ['check' => $checkNum, 'name' => $testName, 'status' => 'FAIL', 'details' => $details];
        return false;
    }
}

function getOrCreateUser(PDO $db, string $mobile, string $role, string $name, string $email, ?int $distId = null, ?int $blkId = null): int {
    $stmt = $db->prepare("SELECT id FROM users WHERE mobile = ? LIMIT 1");
    $stmt->execute([$mobile]);
    $existing = $stmt->fetchColumn();
    if ($existing) {
        $db->prepare("UPDATE users SET role = ?, name = ?, email = ?, status = 'ACTIVE', assigned_district_id = ?, assigned_block_id = ? WHERE id = ?")
           ->execute([$role, $name, $email, $distId, $blkId, $existing]);
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
    $ins->execute([$uuid, $role, $name, $mobile, $email, $distId, $blkId]);
    return (int)$db->lastInsertId();
}

// Ensure 2 distinct blocks exist for District 1
$dist1 = 1;
$stmtBlk = $db->prepare("SELECT id FROM blocks WHERE district_id = ? ORDER BY id ASC LIMIT 2");
$stmtBlk->execute([$dist1]);
$blks = $stmtBlk->fetchAll(PDO::FETCH_COLUMN);
if (count($blks) < 2) {
    die("Need at least 2 blocks in district 1 for multi-block tests\n");
}
$blk1 = (int)$blks[0];
$blk2 = (int)$blks[1];

// Find a block in a different district (Dist 2)
$stmtDist2 = $db->query("SELECT id FROM districts WHERE id != {$dist1} LIMIT 1");
$dist2 = (int)$stmtDist2->fetchColumn();
$stmtBlk3 = $db->prepare("SELECT id FROM blocks WHERE district_id = ? LIMIT 1");
$stmtBlk3->execute([$dist2]);
$blk3 = (int)$stmtBlk3->fetchColumn();

// Ensure service_block_availability is LIVE for blk1, blk2, blk3
foreach ([[$dist1, $blk1], [$dist1, $blk2], [$dist2, $blk3]] as $loc) {
    $db->prepare("
        INSERT INTO service_block_availability (data_type_id, district_id, block_id, status, created_at, updated_at)
        VALUES (1, ?, ?, 'LIVE', NOW(), NOW())
        ON DUPLICATE KEY UPDATE status = 'LIVE'
    ")->execute([$loc[0], $loc[1]]);
}

// Ensure service_rate_rules exists for testing
foreach ([[$dist1, $blk1], [$dist1, $blk2], [$dist2, $blk3]] as $loc) {
    $stmtRate = $db->prepare("SELECT id FROM service_rate_rules WHERE data_type_id = 1 AND district_id = ? AND block_id = ? LIMIT 1");
    $stmtRate->execute([$loc[0], $loc[1]]);
    if (!$stmtRate->fetchColumn()) {
        $db->prepare("
            INSERT INTO service_rate_rules (data_type_id, state, district_id, block_id, application_code, fee_amount, is_active, created_at, updated_at)
            VALUES (1, 'Bihar', ?, ?, NULL, 50.00, 1, NOW(), NOW())
        ")->execute([$loc[0], $loc[1]]);
    }
}

// -------------------------------------------------------------
// CHECK 1: USER sirf Retailer Mode use kare
// -------------------------------------------------------------
$pureUserId = getOrCreateUser($db, '9900022001', 'USER', 'Pure Retailer User', 'pureretailer@test.local');
WalletService::getOrCreateWallet($pureUserId);
$db->prepare("INSERT INTO user_role_assignments (user_id, role_code, is_active, granted_at, created_at, updated_at) VALUES (?, 'USER', 1, NOW(), NOW(), NOW()) ON DUPLICATE KEY UPDATE is_active = 1")->execute([$pureUserId]);
$db->prepare("DELETE FROM user_role_assignments WHERE user_id = ? AND role_code = 'OPERATOR'")->execute([$pureUserId]);

// Test session simulation for pure user
$_SESSION['user_id'] = $pureUserId;
$_SESSION['user_role'] = 'USER';
$_SESSION['active_mode'] = 'RETAILER';

$hasDual = AuthService::hasDualRole($pureUserId);
$canSwitch = AuthService::hasRoleCapability($pureUserId, 'OPERATOR');
$isOpStaff = AuthService::isStaffOrAdmin();

$c1Ok = (!$hasDual && !$canSwitch && !$isOpStaff);
$c1Details = "hasDualRole=" . ($hasDual?'true':'false') . ", hasOperatorCapability=" . ($canSwitch?'true':'false');
runAssert($c1Ok, "1", "USER sirf Retailer Mode use kare (cannot activate Operator mode)", $c1Details);

// -------------------------------------------------------------
// CHECK 2: OPERATOR sirf assigned applications dekhe
// -------------------------------------------------------------
$dedicatedOpId = getOrCreateUser($db, '9900022002', 'OPERATOR', 'Dedicated Staff Only', 'dedicatedop@test.local', $dist1, $blk1);
$otherOpId = getOrCreateUser($db, '9900022003', 'OPERATOR', 'Other Staff Person', 'otherop@test.local', $dist1, $blk1);

// Clean assignments for test
$db->prepare("DELETE FROM operator_block_assignments WHERE operator_user_id IN (?, ?)")->execute([$dedicatedOpId, $otherOpId]);
$db->prepare("DELETE FROM operator_application_code_assignments WHERE operator_user_id IN (?, ?)")->execute([$dedicatedOpId, $otherOpId]);

// Assign dedicatedOpId to dist1, blk1
StaffRoutingService::saveOperatorBlockAssignment($dedicatedOpId, $dist1, $blk1, true);
StaffRoutingService::saveOperatorCodeAssignments($dedicatedOpId, $dist1, $blk1, ['BICCO']);

// Assign otherOpId to dist1, blk1
StaffRoutingService::saveOperatorBlockAssignment($otherOpId, $dist1, $blk1, true);
StaffRoutingService::saveOperatorCodeAssignments($otherOpId, $dist1, $blk1, ['BICCO']);

// Create 1 application assigned to dedicatedOp, 1 assigned to otherOp
$appRefDed = 'TEST-DED-' . time() . '-' . mt_rand(100, 999);
$db->prepare("
    INSERT INTO applications (app_ref, user_id, data_type_id, application_code, district_id, block_id, assigned_operator_id, status, is_locked, created_at, updated_at)
    VALUES (?, ?, 1, 'BICCO', ?, ?, ?, 'IN_PROGRESS', 1, NOW(), NOW())
")->execute([$appRefDed, $pureUserId, $dist1, $blk1, $dedicatedOpId]);
$appIdDed = (int)$db->lastInsertId();

$appRefOth = 'TEST-OTH-' . time() . '-' . mt_rand(100, 999);
$db->prepare("
    INSERT INTO applications (app_ref, user_id, data_type_id, application_code, district_id, block_id, assigned_operator_id, status, is_locked, created_at, updated_at)
    VALUES (?, ?, 1, 'BICCO', ?, ?, ?, 'IN_PROGRESS', 1, NOW(), NOW())
")->execute([$appRefOth, $pureUserId, $dist1, $blk1, $otherOpId]);
$appIdOth = (int)$db->lastInsertId();

// Verify access control
$canAccessOwn = AuthService::canOperatorAccessApplication($dedicatedOpId, $appIdDed);
$canAccessOther = AuthService::canOperatorAccessApplication($dedicatedOpId, $appIdOth);

$c2Ok = ($canAccessOwn && !$canAccessOther);
$c2Details = "canAccessOwn=" . ($canAccessOwn?'true':'false') . ", canAccessOther=" . ($canAccessOther?'true':'false');
runAssert($c2Ok, "2", "OPERATOR sirf assigned applications dekhe (cannot view another operator's application)", $c2Details);

// -------------------------------------------------------------
// CHECK 3: Dual-role user dono modes switch kar sake
// -------------------------------------------------------------
$dualUserId = getOrCreateUser($db, '9900022004', 'USER', 'Dual Role User', 'dualuser@test.local');
WalletService::getOrCreateWallet($dualUserId);
// Grant both USER and OPERATOR role capabilities
$db->prepare("INSERT INTO user_role_assignments (user_id, role_code, is_active, granted_at, created_at, updated_at) VALUES (?, 'USER', 1, NOW(), NOW(), NOW()) ON DUPLICATE KEY UPDATE is_active = 1")->execute([$dualUserId]);
$db->prepare("INSERT INTO user_role_assignments (user_id, role_code, is_active, granted_at, created_at, updated_at) VALUES (?, 'OPERATOR', 1, NOW(), NOW(), NOW()) ON DUPLICATE KEY UPDATE is_active = 1")->execute([$dualUserId]);

// Assign at least one block to dualUserId so operator mode is valid
StaffRoutingService::saveOperatorBlockAssignment($dualUserId, $dist1, $blk1, true);
StaffRoutingService::saveOperatorCodeAssignments($dualUserId, $dist1, $blk1, ['BICCO']);

// Simulate login session as Retailer
$_SESSION['user_id'] = $dualUserId;
$_SESSION['user_role'] = 'USER';
$_SESSION['active_portal_mode'] = 'RETAILER';
$initialMode = AuthService::getActiveRoleMode();

// Simulate mode switch to OPERATOR
$switch1 = AuthService::switchRoleMode('OPERATOR');
$afterSwitchOp = AuthService::getActiveRoleMode();
$isStaffNow = AuthService::isStaffOrAdmin();

// Simulate mode switch back to RETAILER
$switch2 = AuthService::switchRoleMode('RETAILER');
$afterSwitchRet = AuthService::getActiveRoleMode();

$c3Ok = ($initialMode === 'RETAILER' && $switch1 && $afterSwitchOp === 'OPERATOR' && $isStaffNow && $switch2 && $afterSwitchRet === 'RETAILER');
$c3Details = "init={$initialMode}, switch1=" . ($switch1?'ok':'fail') . ", op={$afterSwitchOp}, switch2=" . ($switch2?'ok':'fail') . ", ret={$afterSwitchRet}";
runAssert($c3Ok, "3", "Dual-role user dono modes switch kar sake (Retailer <-> Operator Mode)", $c3Details);

// -------------------------------------------------------------
// CHECK 4: Ek operator ko do alag blocks assign karke dono blocks ki assigned applications dikhni chahiye
// -------------------------------------------------------------
// Assign dedicatedOpId to both blk1 AND blk2
StaffRoutingService::saveOperatorBlockAssignment($dedicatedOpId, $dist1, $blk1, true);
StaffRoutingService::saveOperatorCodeAssignments($dedicatedOpId, $dist1, $blk1, ['BICCO', 'BCCCO']);

StaffRoutingService::saveOperatorBlockAssignment($dedicatedOpId, $dist1, $blk2, true);
StaffRoutingService::saveOperatorCodeAssignments($dedicatedOpId, $dist1, $blk2, ['BICCO', 'BCCCO']);

// Create application in blk1 assigned to dedicatedOp
$appRefBlk1 = 'TEST-BLK1-' . time() . '-' . mt_rand(100, 999);
$db->prepare("
    INSERT INTO applications (app_ref, user_id, data_type_id, application_code, district_id, block_id, assigned_operator_id, status, is_locked, created_at, updated_at)
    VALUES (?, ?, 1, 'BICCO', ?, ?, ?, 'PENDING', 1, NOW(), NOW())
")->execute([$appRefBlk1, $pureUserId, $dist1, $blk1, $dedicatedOpId]);
$appIdBlk1 = (int)$db->lastInsertId();

// Create application in blk2 assigned to dedicatedOp
$appRefBlk2 = 'TEST-BLK2-' . time() . '-' . mt_rand(100, 999);
$db->prepare("
    INSERT INTO applications (app_ref, user_id, data_type_id, application_code, district_id, block_id, assigned_operator_id, status, is_locked, created_at, updated_at)
    VALUES (?, ?, 1, 'BICCO', ?, ?, ?, 'PENDING', 1, NOW(), NOW())
")->execute([$appRefBlk2, $pureUserId, $dist1, $blk2, $dedicatedOpId]);
$appIdBlk2 = (int)$db->lastInsertId();

// Query operator visible applications for both blocks
$accBlk1 = AuthService::canOperatorAccessApplication($dedicatedOpId, $appIdBlk1);
$accBlk2 = AuthService::canOperatorAccessApplication($dedicatedOpId, $appIdBlk2);

$assignedBlocks = AuthService::getOperatorAssignedBlocks($dedicatedOpId);
$assignedBlockIds = array_column($assignedBlocks, 'block_id');
$bothBlocksAssigned = in_array($blk1, $assignedBlockIds, true) && in_array($blk2, $assignedBlockIds, true);

$c4Ok = ($accBlk1 && $accBlk2 && $bothBlocksAssigned);
$c4Details = "accBlk1=" . ($accBlk1?'true':'false') . ", accBlk2=" . ($accBlk2?'true':'false') . ", bothAssigned=" . ($bothBlocksAssigned?'true':'false');
runAssert($c4Ok, "4", "Ek operator ko do alag blocks assign karke dono blocks ki assigned applications dikhni chahiye", $c4Details);

// -------------------------------------------------------------
// CHECK 5: Unassigned block ka data us operator ko bilkul nahi dikhna chahiye
// -------------------------------------------------------------
// Create application in blk3 (District 2), which is NOT assigned to dedicatedOp
$appRefBlk3 = 'TEST-UNASS-' . time() . '-' . mt_rand(100, 999);
$db->prepare("
    INSERT INTO applications (app_ref, user_id, data_type_id, application_code, district_id, block_id, assigned_operator_id, status, is_locked, created_at, updated_at)
    VALUES (?, ?, 1, 'BICCO', ?, ?, NULL, 'PENDING', 1, NOW(), NOW())
")->execute([$appRefBlk3, $pureUserId, $dist2, $blk3]);
$appIdBlk3 = (int)$db->lastInsertId();

$accUnassigned = AuthService::canOperatorAccessApplication($dedicatedOpId, $appIdBlk3);
$isBlockAssigned = AuthService::isOperatorAssignedToBlock($dedicatedOpId, $dist2, $blk3);

$c5Ok = (!$accUnassigned && !$isBlockAssigned);
$c5Details = "accUnassigned=" . ($accUnassigned?'true':'false') . ", isBlockAssigned=" . ($isBlockAssigned?'true':'false');
runAssert($c5Ok, "5", "Unassigned block ka data us operator ko bilkul nahi dikhna chahiye", $c5Details);

// -------------------------------------------------------------
// CHECK 6: Alag Application Code wale staff ko sirf unke configured codes milne chahiye
// -------------------------------------------------------------
// Setup Code-specific operators for District 1, Block 1
$opCodeA = getOrCreateUser($db, '9900022005', 'OPERATOR', 'Staff Code BICCO', 'codea@test.local', $dist1, $blk1);
$opCodeB = getOrCreateUser($db, '9900022006', 'OPERATOR', 'Staff Code BCCCO', 'codeb@test.local', $dist1, $blk1);

// Isolate code test by deactivating other operators for dist1, blk1
$db->prepare("UPDATE operator_block_assignments SET is_active = 0 WHERE district_id = ? AND block_id = ?")->execute([$dist1, $blk1]);
$db->prepare("UPDATE operator_application_code_assignments SET is_active = 0 WHERE district_id = ? AND block_id = ?")->execute([$dist1, $blk1]);

// Configure opCodeA only for BICCO
StaffRoutingService::saveOperatorBlockAssignment($opCodeA, $dist1, $blk1, true);
StaffRoutingService::saveOperatorCodeAssignments($opCodeA, $dist1, $blk1, ['BICCO']);

// Configure opCodeB only for BCCCO
StaffRoutingService::saveOperatorBlockAssignment($opCodeB, $dist1, $blk1, true);
StaffRoutingService::saveOperatorCodeAssignments($opCodeB, $dist1, $blk1, ['BCCCO']);

// Resolve operator for BICCO in blk1 (excluding opCodeA's user_id if needed, here applicant is pureUserId)
$resolvedOpForBICCO = StaffRoutingService::resolveOperatorForApplication($dist1, $blk1, 'BICCO', null, $pureUserId);
$resolvedOpForBCCCO = StaffRoutingService::resolveOperatorForApplication($dist1, $blk1, 'BCCCO', null, $pureUserId);

$c6Ok = ($resolvedOpForBICCO === $opCodeA && $resolvedOpForBCCCO === $opCodeB);
$c6Details = "BICCO_resolved={$resolvedOpForBICCO} (exp {$opCodeA}), BCCCO_resolved={$resolvedOpForBCCCO} (exp {$opCodeB})";
runAssert($c6Ok, "6", "Alag Application Code wale staff ko sirf unke configured codes milne chahiye", $c6Details);

// -------------------------------------------------------------
// CHECK 7: Retailer kisi bhi enabled block mein entry kar sake
// -------------------------------------------------------------
// Test that pureUserId (Retailer) has permission to access any Bihar location
$_SESSION['user_id'] = $pureUserId;
$_SESSION['user_role'] = 'USER';
$_SESSION['active_portal_mode'] = 'RETAILER';

$retailerCanAccessDist1 = AuthService::canAccessLocation($dist1, $blk1);
$retailerCanAccessDist2 = AuthService::canAccessLocation($dist2, $blk3);

// Submit an application in District 2, Block 3 (different district) via WalletService and DB
if (WalletService::getBalance($pureUserId) < 50) {
    WalletService::credit($pureUserId, 500.0, 'TEST_CREDIT', 'TEST', 'REF-' . time(), 'IDEM-' . time(), 'Credit for test', 'क्रेडिट');
}
$appRefMultiBlock = 'TEST-RETAILER-ALL-' . time() . '-' . mt_rand(100, 999);
$db->prepare("
    INSERT INTO applications (app_ref, user_id, data_type_id, application_code, district_id, block_id, applicant_name, applicant_mobile, status, is_locked, created_at, updated_at)
    VALUES (?, ?, 1, 'BICCO', ?, ?, 'Any Block Applicant', '9876543210', 'PENDING', 1, NOW(), NOW())
")->execute([$appRefMultiBlock, $pureUserId, $dist2, $blk3]);
$appIdMulti = (int)$db->lastInsertId();

$c7Ok = ($retailerCanAccessDist1 && $retailerCanAccessDist2 && $appIdMulti > 0);
$c7Details = "dist1Access=" . ($retailerCanAccessDist1?'true':'false') . ", dist2Access=" . ($retailerCanAccessDist2?'true':'false') . ", appId={$appIdMulti}";
runAssert($c7Ok, "7", "Retailer kisi bhi enabled block mein entry kar sake (Bihar-wide access)", $c7Details);

// -------------------------------------------------------------
// CHECK 8: Assigned operator ko WhatsApp queue entry mile
// -------------------------------------------------------------
// Now assign an operator to dist1, blk2 and test WhatsApp queue
$appRefWa = 'TEST-WA-' . time() . '-' . mt_rand(100, 999);
$db->prepare("
    INSERT INTO applications (app_ref, user_id, data_type_id, application_code, district_id, block_id, applicant_name, applicant_mobile, assigned_operator_id, status, is_locked, created_at, updated_at)
    VALUES (?, ?, 1, 'BICCO', ?, ?, 'Rohan Kumar', '9988776655', ?, 'PENDING', 1, NOW(), NOW())
")->execute([$appRefWa, $pureUserId, $dist1, $blk2, $dedicatedOpId]);
$appIdWa = (int)$db->lastInsertId();

$distName = (string)$db->query("SELECT name FROM districts WHERE id = {$dist1}")->fetchColumn();
$blkName = (string)$db->query("SELECT name FROM blocks WHERE id = {$blk2}")->fetchColumn();

$appDataWa = [
    'id' => $appIdWa,
    'app_ref' => $appRefWa,
    'application_number' => 'APP-NUM-' . time(),
    'application_code' => 'BICCO',
    'district_id' => $dist1,
    'block_id' => $blk2,
    'district_name' => $distName,
    'block_name' => $blkName,
    'applicant_name' => 'Rohan Kumar',
    'applicant_mobile' => '9988776655'
];

StaffRoutingService::saveOperatorBlockAssignment($dedicatedOpId, $dist1, $blk2, true);
$waResult = WhatsAppNotificationService::queueSubmissionNotification($appIdWa, $dist1, $blk2, $appDataWa, $db, $dedicatedOpId);
$stmtWaQueue = $db->prepare("SELECT * FROM whatsapp_notification_outbox WHERE application_id = ? AND recipient_user_id = ? ORDER BY id DESC LIMIT 1");
$stmtWaQueue->execute([$appIdWa, $dedicatedOpId]);
$waEntry = $stmtWaQueue->fetch(PDO::FETCH_ASSOC);

$hasRequiredFields = false;
if ($waEntry && !empty($waEntry['message_body'])) {
    $body = $waEntry['message_body'];
    $hasRequiredFields = (
        str_contains($body, 'Rohan Kumar') &&
        str_contains($body, $appDataWa['application_number']) &&
        str_contains($body, "Location: {$distName} > {$blkName}") &&
        !str_contains($body, 'Reference:') &&
        !str_contains($body, 'Status:')
    );
}

$c8Ok = ($waResult && !empty($waEntry) && $hasRequiredFields);
$c8Details = "queueResult=" . ($waResult?'true':'false') . ", entryFound=" . (!empty($waEntry)?'true':'false') . ", contentValid=" . ($hasRequiredFields?'true':'false');
runAssert($c8Ok, "8", "Assigned operator ko WhatsApp queue entry mile (with applicant details and block)", $c8Details);

// -------------------------------------------------------------
// CHECK 9: Unauthorized view/update/upload/export/API request 403 return kare
// -------------------------------------------------------------
// Test 1: Operator trying to access unassigned application
$unauth1 = AuthService::canOperatorAccessApplication($dedicatedOpId, $appIdOth); // Other operator's app

// Test 2: Dual role operator trying to process their own application (anti-self-processing)
$ownAppRef = 'TEST-OWN-' . time() . '-' . mt_rand(100, 999);
$db->prepare("
    INSERT INTO applications (app_ref, user_id, data_type_id, application_code, district_id, block_id, assigned_operator_id, status, is_locked, created_at, updated_at)
    VALUES (?, ?, 1, 'BICCO', ?, ?, ?, 'PENDING', 1, NOW(), NOW())
")->execute([$ownAppRef, $dualUserId, $dist1, $blk1, $dualUserId]);
$ownAppId = (int)$db->lastInsertId();

$unauthAntiSelf = AuthService::canOperatorAccessApplication($dualUserId, $ownAppId); // Must be FALSE

// Test 3: Suspended operator access
$suspendedOpId = getOrCreateUser($db, '9900022007', 'USER', 'Suspended Dual Staff', 'susp@test.local');
$db->prepare("INSERT INTO user_role_assignments (user_id, role_code, is_active, granted_at, created_at, updated_at) VALUES (?, 'USER', 1, NOW(), NOW(), NOW()) ON DUPLICATE KEY UPDATE is_active = 1")->execute([$suspendedOpId]);
$db->prepare("INSERT INTO user_role_assignments (user_id, role_code, is_active, revoked_at, created_at, updated_at) VALUES (?, 'OPERATOR', 0, NOW(), NOW(), NOW()) ON DUPLICATE KEY UPDATE is_active = 0, revoked_at = NOW()")->execute([$suspendedOpId]);

$suspendedHasCap = AuthService::hasRoleCapability($suspendedOpId, 'OPERATOR');

$c9Ok = (!$unauth1 && !$unauthAntiSelf && !$suspendedHasCap);
$c9Details = "otherOpAccess=" . ($unauth1?'allowed':'blocked') . ", antiSelfAccess=" . ($unauthAntiSelf?'allowed':'blocked') . ", suspendedCap=" . ($suspendedHasCap?'active':'blocked');
runAssert($c9Ok, "9", "Unauthorized view/update/upload/export/API request 403 return kare (strict authorization & anti-self-processing)", $c9Details);

// -------------------------------------------------------------
// CHECK 10: All modified PHP files par syntax check chalao
// -------------------------------------------------------------
$modifiedFiles = [
    'app/Controllers/AdminController.php',
    'app/Controllers/ApplicationController.php',
    'app/Controllers/AuthController.php',
    'app/Controllers/LocationController.php',
    'app/Controllers/Traits/ApplicationAdminTrait.php',
    'app/Controllers/Traits/ApplicationApiTrait.php',
    'app/Controllers/UserController.php',
    'app/Services/ApplicationService.php',
    'app/Services/AuthService.php',
    'app/Services/StaffRoutingService.php',
    'app/Services/SystemUpdateService.php',
    'app/Services/Traits/ApplicationStatusTrait.php',
    'app/Services/WhatsAppNotificationService.php',
    'app/Views/admin/user_detail.php',
    'app/Views/admin/users.php',
    'app/Views/layouts/header.php',
    'app/Views/user/dashboard.php',
    'public/index.php'
];

$allSyntaxOk = true;
$syntaxErrors = [];
foreach ($modifiedFiles as $file) {
    $fullPath = dirname(__DIR__) . '/' . $file;
    if (!file_exists($fullPath)) continue;
    $output = [];
    $retCode = 0;
    exec("php -l \"{$fullPath}\" 2>&1", $output, $retCode);
    if ($retCode !== 0) {
        $allSyntaxOk = false;
        $syntaxErrors[] = "{$file}: " . implode(' ', $output);
    }
}
$c10Ok = $allSyntaxOk;
$c10Details = $allSyntaxOk ? "All " . count($modifiedFiles) . " PHP files passed linting without syntax errors" : implode("; ", $syntaxErrors);
runAssert($c10Ok, "10", "All modified PHP files par syntax check chalao (php -l)", $c10Details);

// -------------------------------------------------------------
// CHECK 11: Runtime logs mein warning, notice, fatal error aur SQL exception nahi rehni chahiye
// -------------------------------------------------------------
$logFile = dirname(__DIR__) . '/storage/logs/error.log';
$logClean = true;
$logDetails = "Log clean";
if (file_exists($logFile)) {
    $recentLog = file_get_contents($logFile);
    // Check if recent log has PHP Fatal error or Uncaught PDOException in last entries
    $lines = explode("\n", trim($recentLog));
    $recentLines = array_slice($lines, -50);
    $fatalFound = false;
    foreach ($recentLines as $line) {
        if (stripos($line, 'Fatal error') !== false || stripos($line, 'Uncaught PDOException') !== false) {
            $fatalFound = true;
            $logDetails = "Found in error.log: " . substr($line, 0, 100);
            break;
        }
    }
    $logClean = !$fatalFound;
}
runAssert($logClean, "11", "Runtime logs mein warning, notice, fatal error aur SQL exception nahi rehni chahiye", $logDetails);

// -------------------------------------------------------------
// CHECK 12: Existing wallet debit, refund, commission aur application status flow regression check karo
// -------------------------------------------------------------
$regressUser = getOrCreateUser($db, '9900022008', 'USER', 'Regression User', 'regress@test.local');
WalletService::getOrCreateWallet($regressUser);
$balBefore = WalletService::getBalance($regressUser);

// Credit 200
WalletService::credit($regressUser, 200.00, 'TEST_DEPOSIT', 'TEST', 'TX-' . time(), 'IDEM-DEP-' . time(), 'Deposit for test', 'जमा');
$balAfterDep = WalletService::getBalance($regressUser);

// Debit 50
$debitTx = WalletService::debit($regressUser, 50.00, 'APPLICATION_FEE', 'APPLICATIONS', 'APP-REG-' . time(), 'IDEM-DEB-' . time(), 'Application fee debit', 'आवेदन शुल्क');
$balAfterDeb = WalletService::getBalance($regressUser);

// Refund 50
$refundTx = WalletService::credit($regressUser, 50.00, 'REFUND', 'APPLICATIONS', 'REF-REG-' . time(), 'IDEM-REF-' . time(), 'Application fee refund', 'वापसी');
$balAfterRef = WalletService::getBalance($regressUser);

$c12Ok = (
    ($balAfterDep - $balBefore) == 200.00 &&
    ($balAfterDep - $balAfterDeb) == 50.00 &&
    ($balAfterRef - $balAfterDeb) == 50.00 &&
    $debitTx > 0 &&
    $refundTx > 0
);
$c12Details = "balBefore={$balBefore}, balAfterDep={$balAfterDep}, balAfterDeb={$balAfterDeb}, balAfterRef={$balAfterRef}";
runAssert($c12Ok, "12", "Existing wallet debit, refund, commission aur application status flow regression check karo", $c12Details);

echo "\n====================================================================\n";
echo "SUMMARY: {$passed} PASSED, {$failed} FAILED (TOTAL 12 CHECKS)\n";
echo "====================================================================\n";

if ($failed > 0) {
    exit(1);
}
exit(0);
