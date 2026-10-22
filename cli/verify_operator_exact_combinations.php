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
echo "   EXACT COMBINATION VERIFICATION TEST FOR OPERATOR 311             \n";
echo "====================================================================\n\n";

$opId = 311;
// Fetch operator 311
$op = $db->query("SELECT id, name, mobile, role, status FROM users WHERE id = {$opId}")->fetch(PDO::FETCH_ASSOC);
if (!$op) {
    die("Operator 311 not found\n");
}
echo "Operator: {$op['name']} (ID: {$op['id']}, Mobile: {$op['mobile']}, Status: {$op['status']})\n\n";

// Ensure Operator 311 has the 3 exact configured combinations:
// 1. Araria (1) -> Araria (1) -> BCCCO, BICCO
// 2. Araria (1) -> Bhargama (2) -> BCCCO, BICCO
// 3. Gaya (11) -> Konch (199) -> BCCCO, BICCO
$distAraria = 1;
$blkAraria = 1;
$blkBhargama = 2;
$distGaya = 11;
$blkKonch = 199;

// Ensure assignments exist in operator_block_assignments
StaffRoutingService::saveOperatorBlockAssignment($opId, $distAraria, $blkAraria, 1, 1, $db);
StaffRoutingService::saveOperatorBlockAssignment($opId, $distAraria, $blkBhargama, 1, 1, $db);
StaffRoutingService::saveOperatorBlockAssignment($opId, $distGaya, $blkKonch, 1, 1, $db);

// Ensure codes are BCCCO and BICCO for each
StaffRoutingService::saveOperatorCodeAssignments($opId, $distAraria, $blkAraria, ['BCCCO', 'BICCO'], 1);
StaffRoutingService::saveOperatorCodeAssignments($opId, $distAraria, $blkBhargama, ['BCCCO', 'BICCO'], 1);
StaffRoutingService::saveOperatorCodeAssignments($opId, $distGaya, $blkKonch, ['BCCCO', 'BICCO'], 1);

// Find a different block in Gaya (unassigned to 311)
$otherGayaBlk = (int)$db->query("SELECT id FROM blocks WHERE district_id = {$distGaya} AND id != {$blkKonch} LIMIT 1")->fetchColumn();

// Find an unassigned district (e.g. Patna = 2)
$unassignedDist = (int)$db->query("SELECT id FROM districts WHERE id NOT IN ({$distAraria}, {$distGaya}) LIMIT 1")->fetchColumn();
$unassignedDistBlk = (int)$db->query("SELECT id FROM blocks WHERE district_id = {$unassignedDist} LIMIT 1")->fetchColumn();

// Retailer user for submitting apps
$retailerId = (int)$db->query("SELECT id FROM users WHERE id != {$opId} AND role = 'USER' LIMIT 1")->fetchColumn();
if (!$retailerId) {
    $retailerId = (int)$db->query("SELECT id FROM users WHERE id != {$opId} LIMIT 1")->fetchColumn();
}

// Another operator for cross-operator testing
$otherOpId = (int)$db->query("SELECT id FROM users WHERE id != {$opId} AND (role = 'OPERATOR' OR role = 'USER') AND id != {$retailerId} LIMIT 1")->fetchColumn();

function createTempApp($db, $distId, $blkId, $code, $assignedOpId, $userId) {
    $ref = 'EXACT-' . strtoupper(substr(md5(uniqid('', true)), 0, 8));
    $stmt = $db->prepare("
        INSERT INTO applications (
            user_id, data_type_id, app_ref, batch_ref, applicant_name, applicant_mobile,
            applicant_identifier, application_code, fee_debited, commission_credited,
            status, district_id, block_id, assigned_operator_id, created_at, updated_at
        ) VALUES (
            ?, 4, ?, 'BATCH-EXACT', 'Exact Applicant', '9876543210',
            ?, ?, 50.00, 10.00,
            'PENDING', ?, ?, ?, NOW(), NOW()
        )
    ");
    $stmt->execute([
        $userId, $ref, $ref, $code, $distId, $blkId, $assignedOpId
    ]);
    return (int)$db->lastInsertId();
}

$createdApps = [];

echo "--- TESTING ALLOWED EXAMPLES ---\n";

$allowedCases = [
    ['Araria + Araria + BCCCO', $distAraria, $blkAraria, 'BCCCO', $opId, $retailerId],
    ['Araria + Araria + BICCO', $distAraria, $blkAraria, 'BICCO', $opId, $retailerId],
    ['Araria + Bhargama + BCCCO', $distAraria, $blkBhargama, 'BCCCO', $opId, $retailerId],
    ['Araria + Bhargama + BICCO', $distAraria, $blkBhargama, 'BICCO', $opId, $retailerId],
    ['Gaya + Konch + BCCCO', $distGaya, $blkKonch, 'BCCCO', $opId, $retailerId],
    ['Gaya + Konch + BICCO', $distGaya, $blkKonch, 'BICCO', $opId, $retailerId],
];

$allPassed = true;

foreach ($allowedCases as $ac) {
    [$name, $d, $b, $code, $targetOp, $submitter] = $ac;
    $appId = createTempApp($db, $d, $b, $code, $targetOp, $submitter);
    $createdApps[] = $appId;
    $canAccess = AuthService::canOperatorAccessApplication($opId, $appId);
    if ($canAccess) {
        echo " [PASS] Allowed: {$name} -> ACCESS GRANTED\n";
    } else {
        echo " [FAIL] Allowed: {$name} -> ACCESS DENIED\n";
        $allPassed = false;
    }
}

echo "\n--- TESTING DENIED EXAMPLES ---\n";

$deniedCases = [
    ['Araria + Araria + LABOURCARD (Unassigned Code)', $distAraria, $blkAraria, 'LABOURCARD', $opId, $retailerId],
    ['Araria + Bhargama + BOBC (Unassigned Code)', $distAraria, $blkBhargama, 'BOBC', $opId, $retailerId],
    ['Gaya + Konch + NCLCO (Unassigned Code)', $distGaya, $blkKonch, 'NCLCO', $opId, $retailerId],
    ['Gaya + Unassigned Block + BICCO (Unassigned Block)', $distGaya, $otherGayaBlk, 'BICCO', $opId, $retailerId],
    ['Unassigned District + Block + BCCCO (Unassigned District)', $unassignedDist, $unassignedDistBlk, 'BCCCO', $opId, $retailerId],
    ['Kisi dusre operator ko assigned application', $distAraria, $blkAraria, 'BCCCO', $otherOpId, $retailerId],
    ['Self-submitted application (anti-self-processing)', $distAraria, $blkAraria, 'BCCCO', $opId, $opId],
];

foreach ($deniedCases as $dc) {
    [$name, $d, $b, $code, $targetOp, $submitter] = $dc;
    $appId = createTempApp($db, $d, $b, $code, $targetOp, $submitter);
    $createdApps[] = $appId;
    $canAccess = AuthService::canOperatorAccessApplication($opId, $appId);
    if (!$canAccess) {
        echo " [PASS] Denied: {$name} -> ACCESS STRICTLY BLOCKED (403)\n";
    } else {
        echo " [FAIL] Denied: {$name} -> ACCESS ALLOWED INCORRECTLY!\n";
        $allPassed = false;
    }
}

// Clean up test records
if (!empty($createdApps)) {
    $db->exec("DELETE FROM applications WHERE id IN (" . implode(',', $createdApps) . ")");
}

echo "\n====================================================================\n";
if ($allPassed) {
    echo " RESULT: 100% OF ALLOWED AND DENIED COMBINATION EXAMPLES PASSED!\n";
} else {
    echo " RESULT: SOME CHECKS FAILED!\n";
    exit(1);
}
echo "====================================================================\n";
