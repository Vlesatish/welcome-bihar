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
use App\Config\Database;
use App\Services\AuthService;
use App\Services\StaffRoutingService;

Config::init();
$db = Database::getConnection();

echo "====================================================================\n";
echo "   COMPREHENSIVE MULTI-BLOCK OPERATOR VERIFICATION TEST            \n";
echo "====================================================================\n\n";

// Target operator: User 311 (mobile: 9931222047)
$opUser = $db->query("SELECT id, name, mobile, role FROM users WHERE id = 311")->fetch(PDO::FETCH_ASSOC);
echo "1. Testing Operator User: {$opUser['name']} (ID: {$opUser['id']}, Mobile: {$opUser['mobile']})\n";

// Fetch 2 blocks from District 1 (Araria)
$d1Blocks = $db->query("SELECT id, name FROM blocks WHERE district_id = 1 AND is_active = 1 ORDER BY id ASC LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);
$blockA = $d1Blocks[0]; // e.g. Araria
$blockB = $d1Blocks[1]; // e.g. Forbesganj / Jokihat / Kursakatta
$blockC = $d1Blocks[2]; // Unassigned block

echo "\nDistrict 1 Blocks:\n";
echo " - Block A (Assigned): ID {$blockA['id']} ({$blockA['name']})\n";
echo " - Block B (Assigned): ID {$blockB['id']} ({$blockB['name']})\n";
echo " - Block C (Unassigned control): ID {$blockC['id']} ({$blockC['name']})\n";

// Assign Block A and Block B to operator 311
StaffRoutingService::saveOperatorBlockAssignment(311, 1, (int)$blockA['id'], 1, 1, $db);
StaffRoutingService::saveOperatorBlockAssignment(311, 1, (int)$blockB['id'], 1, 1, $db);

// Deactivate any assignment for Block C just in case
$db->exec("UPDATE operator_block_assignments SET is_active = 0 WHERE operator_user_id = 311 AND district_id = 1 AND block_id = {$blockC['id']}");

// Assign application codes BICCO and BCCCO to both Block A and Block B
StaffRoutingService::saveOperatorCodeAssignments(311, 1, (int)$blockA['id'], ['BICCO', 'BCCCO'], 1);
StaffRoutingService::saveOperatorCodeAssignments(311, 1, (int)$blockB['id'], ['BICCO', 'BCCCO'], 1);

// Test 2: Check dropdown blocks returned by AuthService::getOperatorAssignedBlocks
$assignedBlocks = AuthService::getOperatorAssignedBlocks(311, 1);
echo "\n2. Assigned Blocks for Operator 311 in District 1 (" . count($assignedBlocks) . "):\n";
$assignedBlockIds = [];
foreach ($assignedBlocks as $ab) {
    $assignedBlockIds[] = (int)$ab['id'];
    echo "   - Block ID {$ab['id']} ({$ab['name']})\n";
}

assert(in_array((int)$blockA['id'], $assignedBlockIds, true), "Block A must be in assigned blocks");
assert(in_array((int)$blockB['id'], $assignedBlockIds, true), "Block B must be in assigned blocks");
assert(!in_array((int)$blockC['id'], $assignedBlockIds, true), "Block C must NOT be in assigned blocks");
echo "   [PASS] Both assigned blocks present; unassigned Block C correctly excluded.\n";

// Create test applications in Block A, Block B, and Block C assigned to operator 311
$retailerId = (int)$db->query("SELECT id FROM users WHERE role = 'USER' AND id != 311 LIMIT 1")->fetchColumn();
if (!$retailerId) {
    $retailerId = (int)$db->query("SELECT id FROM users WHERE id != 311 LIMIT 1")->fetchColumn();
}

function createTestApp($db, $districtId, $blockId, $opId, $retailerId, $code, $status = 'PENDING') {
    $ref = 'TEST-' . strtoupper(substr(md5(uniqid('', true)), 0, 8));
    $stmt = $db->prepare("
        INSERT INTO applications (
            user_id, data_type_id, app_ref, batch_ref, applicant_name, applicant_mobile,
            applicant_identifier, application_code, fee_debited, commission_credited,
            status, district_id, block_id, assigned_operator_id, created_at, updated_at
        ) VALUES (
            ?, 4, ?, ?, ?, '9876543210',
            ?, ?, 50.00, 10.00,
            ?, ?, ?, ?, NOW(), NOW()
        )
    ");
    $stmt->execute([
        $retailerId, $ref, 'BATCH-TEST', 'Test Applicant ' . $ref,
        $ref, $code,
        $status, $districtId, $blockId, $opId
    ]);
    return (int)$db->lastInsertId();
}

$appA_Id = createTestApp($db, 1, (int)$blockA['id'], 311, $retailerId, 'BICCO', 'PENDING');
$appB_Id = createTestApp($db, 1, (int)$blockB['id'], 311, $retailerId, 'BCCCO', 'IN_PROCESS');
$appC_Id = createTestApp($db, 1, (int)$blockC['id'], 311, $retailerId, 'BICCO', 'PENDING'); // assigned to 311 but block C is unassigned

echo "\n3. Created test applications:\n";
echo "   - App A (Block A): ID {$appA_Id}\n";
echo "   - App B (Block B): ID {$appB_Id}\n";
echo "   - App C (Unassigned Block C): ID {$appC_Id}\n";

// Test 3: "All Assigned Blocks" Query for District 1
$sqlAll = "
    SELECT a.id, a.block_id, a.status FROM applications a
    WHERE a.assigned_operator_id = 311 AND a.user_id != 311
      AND a.district_id = 1
      AND EXISTS (
          SELECT 1 FROM operator_block_assignments oba
          WHERE oba.operator_user_id = 311 AND oba.district_id = a.district_id AND oba.block_id = a.block_id AND oba.is_active = 1
      )
";
$appsAll = $db->query($sqlAll)->fetchAll(PDO::FETCH_ASSOC);
$appAllIds = array_column($appsAll, 'id');
echo "\n4. 'All Assigned Blocks' Query in District 1:\n";
echo "   - Found " . count($appsAll) . " applications\n";
assert(in_array($appA_Id, $appAllIds), "App A must appear in All Assigned Blocks");
assert(in_array($appB_Id, $appAllIds), "App B must appear in All Assigned Blocks");
assert(!in_array($appC_Id, $appAllIds), "App C (unassigned block) must NOT appear in All Assigned Blocks");
echo "   [PASS] Both Block A and Block B applications visible together; Block C excluded.\n";

// Test 4: Filter by Block A
$sqlA = $sqlAll . " AND a.block_id = " . (int)$blockA['id'];
$appsA = $db->query($sqlA)->fetchAll(PDO::FETCH_ASSOC);
$appAIds = array_column($appsA, 'id');
echo "\n5. Filter by Block A (ID: {$blockA['id']}):\n";
echo "   - Found " . count($appsA) . " applications\n";
assert(in_array($appA_Id, $appAIds), "App A must appear");
assert(!in_array($appB_Id, $appAIds), "App B must NOT appear");
assert(!in_array($appC_Id, $appAIds), "App C must NOT appear");
echo "   [PASS] Only Block A applications returned.\n";

// Test 5: Filter by Block B
$sqlB = $sqlAll . " AND a.block_id = " . (int)$blockB['id'];
$appsB = $db->query($sqlB)->fetchAll(PDO::FETCH_ASSOC);
$appBIds = array_column($appsB, 'id');
echo "\n6. Filter by Block B (ID: {$blockB['id']}):\n";
echo "   - Found " . count($appsB) . " applications\n";
assert(!in_array($appA_Id, $appBIds), "App A must NOT appear");
assert(in_array($appB_Id, $appBIds), "App B must appear");
assert(!in_array($appC_Id, $appBIds), "App C must NOT appear");
echo "   [PASS] Only Block B applications returned.\n";

// Test 6: Security Check - canOperatorAccessApplication
echo "\n7. Security Checks (canOperatorAccessApplication):\n";
$canAccessA = AuthService::canOperatorAccessApplication(311, $appA_Id);
$canAccessB = AuthService::canOperatorAccessApplication(311, $appB_Id);
$canAccessC = AuthService::canOperatorAccessApplication(311, $appC_Id);

echo "   - Access App A (Assigned Block A): " . ($canAccessA ? "ALLOWED [PASS]" : "DENIED [FAIL]") . "\n";
echo "   - Access App B (Assigned Block B): " . ($canAccessB ? "ALLOWED [PASS]" : "DENIED [FAIL]") . "\n";
echo "   - Access App C (Unassigned Block C): " . (!$canAccessC ? "DENIED 403 [PASS]" : "ALLOWED [FAIL]") . "\n";

// Debug App B
$appB = $db->query("SELECT * FROM applications WHERE id = {$appB_Id}")->fetch(PDO::FETCH_ASSOC);
echo "   [DEBUG App B] ID: {$appB['id']}, op: {$appB['assigned_operator_id']}, dist: {$appB['district_id']}, blk: {$appB['block_id']}, code: {$appB['application_code']}\n";
echo "   [DEBUG isOpAssignedToBlock]: " . (AuthService::isOperatorAssignedToBlock(311, (int)$appB['district_id'], (int)$appB['block_id']) ? 'YES' : 'NO') . "\n";
$codeChk = $db->query("SELECT * FROM operator_application_code_assignments WHERE operator_user_id = 311 AND district_id = {$appB['district_id']} AND block_id = {$appB['block_id']}")->fetchAll(PDO::FETCH_ASSOC);
echo "   [DEBUG Codes for Block B]: " . json_encode($codeChk) . "\n";

assert($canAccessA === true, "Must have access to App A");
assert($canAccessB === true, "Must have access to App B");
assert($canAccessC === false, "Must NOT have access to App C in unassigned block");

// Test 7: Anti-self-processing check
// If operator 311 creates an application themselves, they should NOT have access in operator mode
$selfAppId = createTestApp($db, 1, (int)$blockA['id'], 311, 311, 'BICCO', 'PENDING');
$canAccessSelf = AuthService::canOperatorAccessApplication(311, $selfAppId);
echo "   - Access Self-Created Application: " . (!$canAccessSelf ? "DENIED 403 [PASS]" : "ALLOWED [FAIL]") . "\n";
assert($canAccessSelf === false, "Anti-self-processing must deny operator access to own application");

// Clean up test applications
$db->exec("DELETE FROM applications WHERE id IN ({$appA_Id}, {$appB_Id}, {$appC_Id}, {$selfAppId})");
echo "\n8. Cleaned up temporary test records.\n";

echo "\n====================================================================\n";
echo "   ALL MULTI-BLOCK OPERATOR TESTS COMPLETED SUCCESSFULLY! [100%]\n";
echo "====================================================================\n";
