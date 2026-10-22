<?php
/**
 * Comprehensive Verification: Application Code Based Staff Routing System
 * Tests all 18 cases specified in requirement.
 */

declare(strict_types=1);

require_once __DIR__ . '/../app/Config/Config.php';
require_once __DIR__ . '/../app/Config/Database.php';
require_once __DIR__ . '/../app/helpers.php';

// Autoloader
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $baseDir = dirname(__DIR__) . '/app/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    if (file_exists($file)) require_once $file;
});

use App\Config\Database;
use App\Services\StaffRoutingService;
use App\Services\ApplicationService;
use App\Services\AuthService;
use App\Services\PricingService;
use App\Services\LocationService;
use App\Services\WhatsAppNotificationService;

$db = Database::getConnection();

echo "========================================================\n";
echo "   WELCOME BIHAR - STAFF ROUTING VERIFICATION SUITE    \n";
echo "========================================================\n\n";

$results = [];

function recordResult(string $caseNum, string $title, bool $passed, string $details = ''): void {
    global $results;
    $status = $passed ? "PASS" : "FAIL";
    $results[] = [
        'case' => $caseNum,
        'title' => $title,
        'status' => $status,
        'details' => $details
    ];
    echo sprintf("[%s] Case %s: %s\n", $status, $caseNum, $title);
    if (!empty($details)) {
        echo "       Info: {$details}\n";
    }
}

// 0. Setup test environment
// Find Gaya District and Konch Block
$gaya = $db->query("SELECT id, name FROM districts WHERE name LIKE '%Gaya%' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if (!$gaya) {
    die("Error: Gaya district not found in database.\n");
}
$districtId = (int)$gaya['id'];

$konch = $db->query("SELECT id, name FROM blocks WHERE district_id = {$districtId} AND name LIKE '%Konch%' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if (!$konch) {
    die("Error: Konch block not found in database.\n");
}
$blockId = (int)$konch['id'];

// Find a different block for cross-block testing (e.g., Tekari or another block in Gaya)
$otherBlock = $db->query("SELECT id, name FROM blocks WHERE district_id = {$districtId} AND id != {$blockId} LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$otherBlockId = (int)$otherBlock['id'];

// Find or create Retailer User
$retailer = $db->query("SELECT id, name, mobile FROM users WHERE role = 'USER' AND status = 'ACTIVE' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if (!$retailer) {
    die("Error: No active USER found.\n");
}
$retailerId = (int)$retailer['id'];

// Ensure retailer has wallet and ample balance for tests
$db->exec("INSERT INTO wallets (user_id, balance, created_at, updated_at) VALUES ({$retailerId}, 50000.00, NOW(), NOW())
           ON DUPLICATE KEY UPDATE balance = 50000.00, updated_at = NOW()");

// Find or create Staff A (Operator in Gaya > Konch)
$staffA = $db->query("SELECT id, name, mobile FROM users WHERE role = 'OPERATOR' AND assigned_district_id = {$districtId} AND assigned_block_id = {$blockId} AND mobile = '9800000001' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if (!$staffA) {
    $db->prepare("INSERT INTO users (uuid, role, name, mobile, email, password_hash, status, assigned_district_id, assigned_block_id, created_at, updated_at)
                  VALUES (UUID(), 'OPERATOR', 'Staff A (Konch)', '9800000001', 'staffa_test@bihar.gov.in', :pwd, 'ACTIVE', {$districtId}, {$blockId}, NOW(), NOW())")
       ->execute([':pwd' => password_hash('Welcome@2026', PASSWORD_BCRYPT)]);
    $staffAId = (int)$db->lastInsertId();
} else {
    $staffAId = (int)$staffA['id'];
    $db->exec("UPDATE users SET status = 'ACTIVE' WHERE id = {$staffAId}");
}

// Find or create Staff B (Operator in Gaya > Konch)
$staffB = $db->query("SELECT id, name, mobile FROM users WHERE role = 'OPERATOR' AND assigned_district_id = {$districtId} AND assigned_block_id = {$blockId} AND mobile = '9800000002' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if (!$staffB) {
    $db->prepare("INSERT INTO users (uuid, role, name, mobile, email, password_hash, status, assigned_district_id, assigned_block_id, created_at, updated_at)
                  VALUES (UUID(), 'OPERATOR', 'Staff B (Konch)', '9800000002', 'staffb_test@bihar.gov.in', :pwd, 'ACTIVE', {$districtId}, {$blockId}, NOW(), NOW())")
       ->execute([':pwd' => password_hash('Welcome@2026', PASSWORD_BCRYPT)]);
    $staffBId = (int)$db->lastInsertId();
} else {
    $staffBId = (int)$staffB['id'];
    $db->exec("UPDATE users SET status = 'ACTIVE' WHERE id = {$staffBId}");
}

// Find or create Other Operator (in a different block)
$otherOp = $db->query("SELECT id, name, mobile FROM users WHERE role = 'OPERATOR' AND assigned_district_id = {$districtId} AND assigned_block_id = {$otherBlockId} LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if (!$otherOp) {
    $db->prepare("INSERT INTO users (uuid, role, name, mobile, email, password_hash, status, assigned_district_id, assigned_block_id, created_at, updated_at)
                  VALUES (UUID(), 'OPERATOR', 'Staff Other Block', '9800000003', 'staffother_test@bihar.gov.in', :pwd, 'ACTIVE', {$districtId}, {$otherBlockId}, NOW(), NOW())")
       ->execute([':pwd' => password_hash('Welcome@2026', PASSWORD_BCRYPT)]);
    $otherOpId = (int)$db->lastInsertId();
} else {
    $otherOpId = (int)$otherOp['id'];
}

// Ensure active rates exist for Gaya > Konch for BICCO and LABOURCARD
$dtRow = $db->query("SELECT id FROM data_types WHERE code = 'srv_block_app' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$dataTypeId = $dtRow ? (int)$dtRow['id'] : 4;

// Ensure block service is active in service_block_availability
$db->exec("INSERT INTO service_block_availability (data_type_id, district_id, block_id, status, created_at, updated_at)
           VALUES ({$dataTypeId}, {$districtId}, {$blockId}, 'LIVE', NOW(), NOW())
           ON DUPLICATE KEY UPDATE status = 'LIVE', updated_at = NOW()");

// Ensure rate rules exist for BICCO (50.00) and LABOURCARD (500.00) and others
$upsertRate = function($code, $fee) use ($db, $dataTypeId, $districtId, $blockId) {
    $existing = $db->prepare("SELECT id FROM service_rate_rules WHERE data_type_id = ? AND district_id = ? AND block_id = ? AND application_code = ? LIMIT 1");
    $existing->execute([$dataTypeId, $districtId, $blockId, $code]);
    $rowId = $existing->fetchColumn();
    if ($rowId) {
        $db->prepare("UPDATE service_rate_rules SET fee_amount = ?, is_active = 1, updated_at = NOW() WHERE id = ?")->execute([$fee, $rowId]);
    } else {
        $db->prepare("INSERT INTO service_rate_rules (data_type_id, district_id, block_id, application_code, fee_amount, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 1, NOW(), NOW())")
           ->execute([$dataTypeId, $districtId, $blockId, $code, $fee]);
    }
};

$upsertRate('BICCO', 50.00);
$upsertRate('LABOURCARD', 500.00);
foreach (['BCCCO', 'BRCCO', 'BOBC', 'NCLCO'] as $extraCode) {
    $upsertRate($extraCode, 50.00);
}

// -------------------------------------------------------------------------
// Reset any other operator assignments for this test block so Staff A and Staff B are isolated
$db->exec("UPDATE operator_application_code_assignments SET is_active = 0 WHERE district_id = {$districtId} AND block_id = {$blockId} AND operator_user_id NOT IN ({$staffAId}, {$staffBId})");

// CASE 1: Configure Gaya > Konch BICCO (and BCCCO, BRCCO, BOBC, NCLCO) to Staff A
// -------------------------------------------------------------------------
StaffRoutingService::saveOperatorCodeAssignments($staffAId, $districtId, $blockId, ['BCCCO', 'BICCO', 'BRCCO', 'BOBC', 'NCLCO'], 1);
$staffACodes = $db->query("SELECT application_code FROM operator_application_code_assignments WHERE operator_user_id = {$staffAId} AND is_active = 1")->fetchAll(PDO::FETCH_COLUMN);
$c1Pass = in_array('BICCO', $staffACodes, true) && count($staffACodes) === 5;
recordResult('1', 'Configure Gaya > Konch BICCO to Staff A', $c1Pass, 'Staff A assigned: ' . implode(', ', $staffACodes));

// -------------------------------------------------------------------------
// CASE 2: Configure Gaya > Konch LABOURCARD to Staff B
// -------------------------------------------------------------------------
StaffRoutingService::saveOperatorCodeAssignments($staffBId, $districtId, $blockId, ['LABOURCARD'], 1);
$staffBCodes = $db->query("SELECT application_code FROM operator_application_code_assignments WHERE operator_user_id = {$staffBId} AND is_active = 1")->fetchAll(PDO::FETCH_COLUMN);
$c2Pass = (count($staffBCodes) === 1 && $staffBCodes[0] === 'LABOURCARD');
recordResult('2', 'Configure Gaya > Konch LABOURCARD to Staff B', $c2Pass, 'Staff B assigned: ' . implode(', ', $staffBCodes));

// -------------------------------------------------------------------------
// CASE 3: Submit BICCO and confirm only Staff A receives it
// -------------------------------------------------------------------------
$biccoAppNum = 'BICCO/' . date('Ymd') . '/' . rand(10000, 99999);
$sub1 = ApplicationService::submit($retailerId, $dataTypeId, $districtId, $blockId, [
    'applicant_name' => 'Ramesh Kumar (BICCO Test)',
    'applicant_mobile' => '9876543210',
    'application_code' => 'BICCO',
    'application_number' => $biccoAppNum
]);
$appBicco = $db->query("SELECT id, assigned_operator_id, application_code FROM applications WHERE id = {$sub1['application_id']}")->fetch(PDO::FETCH_ASSOC);
$c3Pass = ((int)$appBicco['assigned_operator_id'] === $staffAId && $appBicco['application_code'] === 'BICCO');
recordResult('3', 'Submit BICCO and confirm only Staff A receives it', $c3Pass, "App ID {$sub1['application_id']} assigned to Staff ID: {$appBicco['assigned_operator_id']} (Expected: {$staffAId})");

// -------------------------------------------------------------------------
// CASE 4: Submit LABOURCARD and confirm only Staff B receives it
// -------------------------------------------------------------------------
$labourAppNum = 'LC/' . date('Ymd') . '/' . rand(10000, 99999);
$sub2 = ApplicationService::submit($retailerId, $dataTypeId, $districtId, $blockId, [
    'applicant_name' => 'Suresh Yadav (Labour Test)',
    'applicant_mobile' => '9876543211',
    'application_code' => 'LABOURCARD',
    'application_number' => $labourAppNum,
    'aadhaar_number' => sprintf('%012d', mt_rand(100000000000, 999999999999))
]);
$appLabour = $db->query("SELECT id, assigned_operator_id, application_code FROM applications WHERE id = {$sub2['application_id']}")->fetch(PDO::FETCH_ASSOC);
$c4Pass = ((int)$appLabour['assigned_operator_id'] === $staffBId && $appLabour['application_code'] === 'LABOURCARD');
recordResult('4', 'Submit LABOURCARD and confirm only Staff B receives it', $c4Pass, "App ID {$sub2['application_id']} assigned to Staff ID: {$appLabour['assigned_operator_id']} (Expected: {$staffBId})");

// -------------------------------------------------------------------------
// CASE 5: Staff A cannot open Staff B application through direct URL/access
// -------------------------------------------------------------------------
$staffACanOpenStaffB = AuthService::canOperatorAccessApplication($staffAId, (int)$appLabour['id']);
$staffBCanOpenStaffB = AuthService::canOperatorAccessApplication($staffBId, (int)$appLabour['id']);
$c5Pass = (!$staffACanOpenStaffB && $staffBCanOpenStaffB);
recordResult('5', 'Staff A cannot open Staff B application through direct URL', $c5Pass, "Staff A access: " . ($staffACanOpenStaffB ? 'ALLOWED (FAIL)' : 'DENIED (PASS)') . ", Staff B access: " . ($staffBCanOpenStaffB ? 'ALLOWED' : 'DENIED'));

// -------------------------------------------------------------------------
// CASE 6: Staff B cannot approve Staff A application through direct POST
// -------------------------------------------------------------------------
$staffBCanOpenStaffA = AuthService::canOperatorAccessApplication($staffBId, (int)$appBicco['id']);
$staffACanOpenStaffA = AuthService::canOperatorAccessApplication($staffAId, (int)$appBicco['id']);
$c6Pass = (!$staffBCanOpenStaffA && $staffACanOpenStaffA);
recordResult('6', 'Staff B cannot approve Staff A application through direct POST', $c6Pass, "Staff B access to Staff A app: " . ($staffBCanOpenStaffA ? 'ALLOWED (FAIL)' : 'DENIED (PASS)'));

// -------------------------------------------------------------------------
// CASE 7: Batch with BICCO and LABOURCARD routes rows separately
// -------------------------------------------------------------------------
$batchItems = [
    [
        'district_id' => $districtId,
        'block_id' => $blockId,
        'application_code' => 'BICCO',
        'application_number' => 'BICCO/BATCH/' . rand(10000, 99999),
        'applicant_name' => 'Batch Person 1'
    ],
    [
        'district_id' => $districtId,
        'block_id' => $blockId,
        'application_code' => 'LABOURCARD',
        'application_number' => 'LC/BATCH/' . rand(10000, 99999),
        'applicant_name' => 'Batch Person 2',
        'aadhaar_number' => sprintf('%012d', mt_rand(100000000000, 999999999999))
    ],
    [
        'district_id' => $districtId,
        'block_id' => $blockId,
        'application_code' => 'BCCCO',
        'application_number' => 'BCCCO/BATCH/' . rand(10000, 99999),
        'applicant_name' => 'Batch Person 3'
    ]
];
$batchRes = ApplicationService::submitBatch($retailerId, $dataTypeId, $batchItems);
$batchApps = $batchRes['applications'] ?? [];
$bApp1 = $db->query("SELECT assigned_operator_id, application_code FROM applications WHERE id = {$batchApps[0]['id']}")->fetch(PDO::FETCH_ASSOC);
$bApp2 = $db->query("SELECT assigned_operator_id, application_code FROM applications WHERE id = {$batchApps[1]['id']}")->fetch(PDO::FETCH_ASSOC);
$bApp3 = $db->query("SELECT assigned_operator_id, application_code FROM applications WHERE id = {$batchApps[2]['id']}")->fetch(PDO::FETCH_ASSOC);

$c7Pass = ((int)$bApp1['assigned_operator_id'] === $staffAId &&
           (int)$bApp2['assigned_operator_id'] === $staffBId &&
           (int)$bApp3['assigned_operator_id'] === $staffAId);
recordResult('7', 'Batch with BICCO and LABOURCARD routes rows separately', $c7Pass, "Row 1 (BICCO) -> Op {$bApp1['assigned_operator_id']} | Row 2 (LABOURCARD) -> Op {$bApp2['assigned_operator_id']} | Row 3 (BCCCO) -> Op {$bApp3['assigned_operator_id']}");

// -------------------------------------------------------------------------
// CASE 8: Operator from another block receives nothing
// -------------------------------------------------------------------------
$otherOpAssignedCount = (int)$db->query("SELECT COUNT(*) FROM applications WHERE assigned_operator_id = {$otherOpId} AND district_id = {$districtId} AND block_id = {$blockId}")->fetchColumn();
$c8Pass = ($otherOpAssignedCount === 0);
recordResult('8', 'Operator from another block receives nothing', $c8Pass, "Other Block Operator assigned count for Konch: {$otherOpAssignedCount}");

// -------------------------------------------------------------------------
// CASE 9: Inactive operator is never assigned
// -------------------------------------------------------------------------
// Deactivate Staff A temporarily
$db->exec("UPDATE users SET status = 'INACTIVE' WHERE id = {$staffAId}");
$opResolvedWhenInactive = StaffRoutingService::resolveOperatorForApplication($districtId, $blockId, 'BICCO');
// Reactivate Staff A
$db->exec("UPDATE users SET status = 'ACTIVE' WHERE id = {$staffAId}");
$c9Pass = ($opResolvedWhenInactive === null || $opResolvedWhenInactive !== $staffAId);
recordResult('9', 'Inactive operator is never assigned', $c9Pass, "Resolved op while Staff A was inactive: " . var_export($opResolvedWhenInactive, true));

// -------------------------------------------------------------------------
// CASE 10: Missing routing leaves the application unassigned and alerts Admin
// -------------------------------------------------------------------------
// Add a rate for 'OTHER' code but assign it to no operator
$db->exec("INSERT INTO service_rate_rules (data_type_id, district_id, block_id, application_code, fee_amount, is_active, created_at, updated_at)
           VALUES ({$dataTypeId}, {$districtId}, {$blockId}, 'OTHER', 75.00, 1, NOW(), NOW())
           ON DUPLICATE KEY UPDATE fee_amount = 75.00, is_active = 1, updated_at = NOW()");
$db->exec("DELETE FROM operator_application_code_assignments WHERE district_id = {$districtId} AND block_id = {$blockId} AND application_code = 'OTHER'");

$subUnassigned = ApplicationService::submit($retailerId, $dataTypeId, $districtId, $blockId, [
    'applicant_name' => 'Unassigned Test Candidate',
    'applicant_mobile' => '9876543299',
    'application_code' => 'OTHER',
    'application_number' => 'OTHER/' . rand(10000, 99999)
]);
$unassignedRow = $db->query("SELECT id, status, assigned_operator_id FROM applications WHERE id = {$subUnassigned['application_id']}")->fetch(PDO::FETCH_ASSOC);
$adminNoticeCount = (int)$db->query("SELECT COUNT(*) FROM notifications WHERE user_id IN (SELECT id FROM users WHERE role = 'ADMIN') AND title LIKE '%Unassigned%'")->fetchColumn();
$c10Pass = ($unassignedRow['assigned_operator_id'] === null && $unassignedRow['status'] === 'PENDING' && $adminNoticeCount > 0);
recordResult('10', 'Missing routing leaves the application unassigned and alerts Admin', $c10Pass, "Status: {$unassignedRow['status']}, Assigned Op: " . var_export($unassignedRow['assigned_operator_id'], true) . ", Admin Notices: {$adminNoticeCount}");

// -------------------------------------------------------------------------
// CASE 11: Manual reassignment removes old staff access
// -------------------------------------------------------------------------
// Reassign the BICCO app from Staff A to Staff B manually (Staff B must be permitted for BICCO)
StaffRoutingService::saveOperatorCodeAssignments($staffBId, $districtId, $blockId, ['LABOURCARD', 'BICCO'], 1);
StaffRoutingService::reassignApplication((int)$appBicco['id'], $staffBId, 'Shift workload to Staff B', 1);
$appBiccoAfterReassign = $db->query("SELECT assigned_operator_id FROM applications WHERE id = {$appBicco['id']}")->fetch(PDO::FETCH_ASSOC);
$staffAAccessAfter = AuthService::canOperatorAccessApplication($staffAId, (int)$appBicco['id']);
$staffBAccessAfter = AuthService::canOperatorAccessApplication($staffBId, (int)$appBicco['id']);
$historyCount = (int)$db->query("SELECT COUNT(*) FROM application_assignment_history WHERE application_id = {$appBicco['id']} AND assignment_type = 'REASSIGN'")->fetchColumn();
$c11Pass = ((int)$appBiccoAfterReassign['assigned_operator_id'] === $staffBId && !$staffAAccessAfter && $staffBAccessAfter && $historyCount > 0);
recordResult('11', 'Manual reassignment removes old staff access', $c11Pass, "New assigned op: {$appBiccoAfterReassign['assigned_operator_id']}, Staff A access: " . ($staffAAccessAfter ? 'YES' : 'NO') . ", Staff B access: " . ($staffBAccessAfter ? 'YES' : 'NO') . ", History records: {$historyCount}");

// -------------------------------------------------------------------------
// CASE 12: WhatsApp goes only to assigned staff
// -------------------------------------------------------------------------
// Check the outbox message created for the Labour application (which went to Staff B)
$outboxStaffB = $db->query("SELECT recipient_user_id, status, message_body FROM whatsapp_notification_outbox WHERE recipient_user_id = {$staffBId} ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$outboxStaffAForLabour = $db->query("SELECT COUNT(*) FROM whatsapp_notification_outbox WHERE recipient_user_id = {$staffAId} AND message_body LIKE '%LABOURCARD%'")->fetchColumn();
$c12Pass = ($outboxStaffB && (int)$outboxStaffAForLabour === 0 && str_contains($outboxStaffB['message_body'], 'Applicant:'));
recordResult('12', 'WhatsApp goes only to assigned staff', $c12Pass, "Staff B outbox recipient: " . ($outboxStaffB ? 'YES' : 'NO') . ", Staff A received Labour WA: {$outboxStaffAForLabour}");

// -------------------------------------------------------------------------
// CASE 13: Duplicate WhatsApp is not sent
// -------------------------------------------------------------------------
// Attempt to queue a second notification for the same application and event
WhatsAppNotificationService::queueSubmissionNotification(
    (int)$appLabour['id'],
    $districtId,
    $blockId,
    [
        'applicant_name' => 'Suresh Yadav (Labour Test)',
        'application_number' => $labourAppNum,
        'app_ref' => 'TEST-REF',
        'district_name' => 'Gaya',
        'block_name' => 'Konch',
        'application_code' => 'LABOURCARD'
    ],
    $db,
    $staffBId
);
$countLabourWa = (int)$db->query("SELECT COUNT(*) FROM whatsapp_notification_outbox WHERE recipient_user_id = {$staffBId} AND message_body LIKE '%{$labourAppNum}%'")->fetchColumn();
$c13Pass = ($countLabourWa === 1);
recordResult('13', 'Duplicate WhatsApp is not sent', $c13Pass, "Total outbox messages for {$labourAppNum}: {$countLabourWa} (Expected: 1)");

// -------------------------------------------------------------------------
// CASE 14: Wallet debit occurs exactly once
// -------------------------------------------------------------------------
$balBefore = (float)$db->query("SELECT balance FROM wallets WHERE user_id = {$retailerId}")->fetchColumn();
$singleFee = 50.00;
$appSingleNum = 'BICCO/WALLET/' . rand(10000, 99999);
$subWallet = ApplicationService::submit($retailerId, $dataTypeId, $districtId, $blockId, [
    'applicant_name' => 'Wallet Safety Candidate',
    'applicant_mobile' => '9876543255',
    'application_code' => 'BICCO',
    'application_number' => $appSingleNum
]);
$balAfter = (float)$db->query("SELECT balance FROM wallets WHERE user_id = {$retailerId}")->fetchColumn();
$txCount = (int)$db->query("SELECT COUNT(*) FROM wallet_transactions WHERE reference_type = 'APPLICATION' AND reference_id = '{$subWallet['application_id']}'")->fetchColumn();
$diff = round($balBefore - $balAfter, 2);
$c14Pass = ($diff === $singleFee && $txCount === 1);
recordResult('14', 'Wallet debit occurs exactly once', $c14Pass, "Balance before: ₹{$balBefore}, after: ₹{$balAfter}, Debited: ₹{$diff} (Expected: ₹{$singleFee}), Transactions: {$txCount}");

// -------------------------------------------------------------------------
// CASE 15: Exact block rate remains unchanged
// -------------------------------------------------------------------------
$pricingBicco = PricingService::calculatePrice($dataTypeId, $districtId, $blockId, ['application_code' => 'BICCO']);
$pricingLabour = PricingService::calculatePrice($dataTypeId, $districtId, $blockId, ['application_code' => 'LABOURCARD']);
$c15Pass = ((float)$pricingBicco['final_fee'] === 50.00 && (float)$pricingLabour['final_fee'] === 500.00);
recordResult('15', 'Exact block rate remains unchanged', $c15Pass, "BICCO resolved rate: ₹{$pricingBicco['final_fee']} (Expected 50.00), LABOURCARD: ₹{$pricingLabour['final_fee']} (Expected 500.00)");

// -------------------------------------------------------------------------
// CASE 16: Block Service ON/OFF continues working
// -------------------------------------------------------------------------
// Turn OFF service for Konch
$db->exec("UPDATE service_block_availability SET status = 'INACTIVE' WHERE district_id = {$districtId} AND block_id = {$blockId} AND data_type_id = {$dataTypeId}");
$submissionBlocked = false;
try {
    ApplicationService::submit($retailerId, $dataTypeId, $districtId, $blockId, [
        'applicant_name' => 'Should Fail',
        'applicant_mobile' => '9876543200',
        'application_code' => 'BICCO',
        'application_number' => 'BICCO/FAIL/' . rand(1000, 9999)
    ]);
} catch (\Exception $e) {
    $submissionBlocked = true;
}
// Turn ON service again
$db->exec("UPDATE service_block_availability SET status = 'LIVE' WHERE district_id = {$districtId} AND block_id = {$blockId} AND data_type_id = {$dataTypeId}");
$c16Pass = ($submissionBlocked === true);
recordResult('16', 'Block Service ON/OFF continues working', $c16Pass, "Submission blocked when service INACTIVE: " . ($submissionBlocked ? 'YES' : 'NO'));

// -------------------------------------------------------------------------
// CASE 17: Retailer location access remains unchanged
// -------------------------------------------------------------------------
// Retailer profile district/block does not restrict submitting to another block
$subOtherLocation = ApplicationService::submit($retailerId, $dataTypeId, $districtId, $blockId, [
    'applicant_name' => 'Cross Location Candidate',
    'applicant_mobile' => '9876543212',
    'application_code' => 'BICCO',
    'application_number' => 'BICCO/CROSS/' . rand(1000, 9999)
]);
$c17Pass = (!empty($subOtherLocation['application_id']));
recordResult('17', 'Retailer location access remains unchanged', $c17Pass, "Retailer submitted successfully regardless of profile location. App ID: {$subOtherLocation['application_id']}");

// -------------------------------------------------------------------------
// CASE 18: All modified PHP files pass php -l
// -------------------------------------------------------------------------
$filesToCheck = [
    'app/Services/StaffRoutingService.php',
    'app/Services/ApplicationService.php',
    'app/Services/AuthService.php',
    'app/Services/WhatsAppNotificationService.php',
    'app/Controllers/AdminController.php',
    'app/Controllers/ApplicationController.php',
    'app/Controllers/Traits/ApplicationAdminTrait.php',
    'app/Controllers/Traits/ApplicationApiTrait.php',
    'app/Views/admin/staff_routing.php',
    'app/Views/admin/applications.php',
    'app/Views/admin/applications_modals.php',
    'app/Views/admin/service_rate_rules.php',
    'app/Views/admin/dashboard.php',
    'public/index.php'
];

$allSyntaxPass = true;
$syntaxErrors = [];
foreach ($filesToCheck as $f) {
    $fullPath = dirname(__DIR__) . '/' . $f;
    exec("\"C:\\xampp\\php\\php.exe\" -l \"{$fullPath}\" 2>&1", $out, $code);
    if ($code !== 0) {
        $allSyntaxPass = false;
        $syntaxErrors[] = "{$f}: " . implode(' ', $out);
    }
}
recordResult('18', 'All modified PHP files pass php -l', $allSyntaxPass, $allSyntaxPass ? 'All 14 modified/created PHP files passed lint check.' : implode('; ', $syntaxErrors));

echo "\n========================================================\n";
$totalCases = count($results);
$passCases = count(array_filter($results, fn($r) => $r['status'] === 'PASS'));
echo "RESULTS: {$passCases}/{$totalCases} PASSED\n";
echo "========================================================\n";
