<?php
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $baseDir = dirname(__DIR__) . '/app/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    if (file_exists($file)) require $file;
});

use App\Config\Database;
use App\Services\WhatsAppNotificationService;

$db = Database::getConnection();

$testsPassed = 0;
$testsFailed = 0;

function assertRule(bool $condition, string $ruleName, string $details = ''): void {
    global $testsPassed, $testsFailed;
    if ($condition) {
        $testsPassed++;
        echo " [PASS] {$ruleName}\n";
    } else {
        $testsFailed++;
        echo " [FAIL] {$ruleName} - Details: {$details}\n";
    }
}

echo "====================================================================\n";
echo " WHATSAPP ULTRA-SHORT MESSAGE COMPLIANCE VERIFICATION\n";
echo "====================================================================\n\n";

// --- TEST 1: Exact User Example ---
echo "--- TEST 1: Exact User Example ---\n";
$exampleMsg = WhatsAppNotificationService::buildMessage([
    'district_name' => 'Gaya',
    'block_name' => 'Konch',
    'applicant_name' => 'Pankaj Kumar',
    'application_number' => 'BICCO/2026/12345',
    'mobile_number' => '9876543210'
]);

$expectedExample = "Location: Gaya > Konch\nApplicant Name: Pankaj Kumar\nApplication Number: BICCO/2026/12345\nMobile Number: 9876543210";
assertRule($exampleMsg === $expectedExample, "User example matches format perfectly", "Expected:\n{$expectedExample}\n\nGot:\n{$exampleMsg}");

// --- TEST 2: Rule 6 & 11 - No heading, description, rate, status, wallet, reference, date, service name, operator name, emojis ---
echo "\n--- TEST 2: Rule 6 & 11 - Exclusion of internal/technical data & boilerplate ---\n";
$dirtyMsg = WhatsAppNotificationService::buildMessage([
    'district_name' => 'Gaya',
    'block_name' => 'Konch',
    'applicant_name' => 'Pankaj Kumar',
    'application_number' => 'BICCO/2026/12345',
    'app_ref' => 'WB-REF-999',
    'application_code' => 'BICCO',
    'status' => 'PENDING',
    'rate' => '150.00',
    'fee' => '100.00',
    'wallet' => '500.00',
    'service_name' => 'Birth Certificate',
    'operator_name' => 'Satish Kumar',
    'created_at' => '2026-10-22 15:00:00',
    'heading' => 'New Application',
    'description' => 'A new application is received',
    'batch_ref' => 'BATCH-001'
]);
assertRule(!str_contains($dirtyMsg, 'WB-REF-999'), "Rule 6: app_ref strictly excluded");
assertRule(!str_contains($dirtyMsg, 'PENDING'), "Rule 6: status strictly excluded");
assertRule(!str_contains($dirtyMsg, '150.00') && !str_contains($dirtyMsg, '100.00'), "Rule 6: rate and fee strictly excluded");
assertRule(!str_contains($dirtyMsg, '500.00'), "Rule 6: wallet balance strictly excluded");
assertRule(!str_contains($dirtyMsg, 'Birth Certificate'), "Rule 6: service name strictly excluded");
assertRule(!str_contains($dirtyMsg, 'Satish Kumar'), "Rule 6: operator name strictly excluded");
assertRule(!str_contains($dirtyMsg, '2026-10-22'), "Rule 6: dates strictly excluded");
assertRule(!str_contains($dirtyMsg, 'New Application'), "Rule 11: headings strictly excluded");
assertRule(!preg_match('/[\x{1F600}-\x{1F64F}\x{1F300}-\x{1F5FF}\x{1F680}-\x{1F6FF}\x{2600}-\x{26FF}]/u', $dirtyMsg), "Rule 11: No decorative emojis");

// --- TEST 3: Rule 2 & 3 - Skip empty, null, whitespace, N/A, unchecked ---
echo "\n--- TEST 3: Rule 2 & 3 - Skip empty, null, whitespace, N/A, unchecked ---\n";
$emptyMsg = WhatsAppNotificationService::buildMessage([
    'district_name' => 'Gaya',
    'block_name' => 'Konch',
    'applicant_name' => 'Pankaj Kumar',
    'empty_field_1' => '',
    'empty_field_2' => '   ',
    'na_field_1' => 'N/A',
    'na_field_2' => 'NA',
    'dash_field' => '-',
    'null_field' => 'NULL',
    'unchecked_field' => 'UNCHECKED'
]);
assertRule(!str_contains($emptyMsg, 'Empty Field'), "Rule 2: Empty fields skipped");
assertRule(!str_contains($emptyMsg, 'Na Field'), "Rule 3: N/A and NA fields skipped");
assertRule(!str_contains($emptyMsg, 'Dash Field'), "Rule 3: Dash fields skipped");
assertRule(!str_contains($emptyMsg, 'Null Field'), "Rule 3: Null fields skipped");
assertRule(!str_contains($emptyMsg, 'Unchecked Field'), "Rule 3: Unchecked fields skipped");

// --- TEST 4: Rule 7 - Aadhaar masking: XXXX-XXXX-1234 ---
echo "\n--- TEST 4: Rule 7 - Aadhaar masking ---\n";
$aadhaarMsg1 = WhatsAppNotificationService::buildMessage([
    'district_name' => 'Gaya',
    'block_name' => 'Konch',
    'aadhaar_number' => '987654321234'
]);
assertRule(str_contains($aadhaarMsg1, 'Aadhaar Number: XXXX-XXXX-1234'), "Rule 7: Plain 12-digit Aadhaar masked to XXXX-XXXX-1234", "Got: {$aadhaarMsg1}");

$aadhaarMsg2 = WhatsAppNotificationService::buildMessage([
    'district_name' => 'Gaya',
    'block_name' => 'Konch',
    'aadhaar_no' => '9876 5432 5678'
]);
assertRule(str_contains($aadhaarMsg2, 'Aadhaar Number: XXXX-XXXX-5678'), "Rule 7: Spaced Aadhaar masked to XXXX-XXXX-5678", "Got: {$aadhaarMsg2}");

// --- TEST 5: Rule 5 - Duplicate field ek baar hi bhejo ---
echo "\n--- TEST 5: Rule 5 - Deduplication of fields ---\n";
$dupMsg = WhatsAppNotificationService::buildMessage([
    'district_name' => 'Gaya',
    'block_name' => 'Konch',
    'applicant_name' => 'Pankaj Kumar',
    'fields' => [
        ['field_name' => 'applicant_name', 'field_value' => 'Pankaj Kumar', 'label' => 'Applicant Name'],
        ['field_name' => 'applicant_name', 'field_value' => 'Pankaj Kumar', 'label' => 'Applicant Name'],
        ['field_name' => 'mobile_number', 'field_value' => '9876543210', 'label' => 'Mobile Number']
    ],
    'mobile_number' => '9876543210'
]);
$applicantCount = substr_count($dupMsg, 'Applicant Name');
$mobileCount = substr_count($dupMsg, 'Mobile Number');
assertRule($applicantCount === 1, "Rule 5: Duplicate Applicant Name appears exactly once", "Count: {$applicantCount}");
assertRule($mobileCount === 1, "Rule 5: Duplicate Mobile Number appears exactly once", "Count: {$mobileCount}");

// --- TEST 6: Rule 1 & 4 - Dynamic values from application_field_values in form order ---
echo "\n--- TEST 6: Rule 1 & 4 - Fetch from DB application_field_values in exact order ---\n";
$dbMsg = WhatsAppNotificationService::buildMessage(['district_name' => 'Gaya', 'block_name' => 'Konch'], $db, 883);
$expectedDbLines = [
    'Location: Gaya > Konch',
    'Application Number: LC/BATCH/34456',
    'Aadhaar Number: XXXX-XXXX-7671',
    'Applicant Name: Batch Person 2'
];
$dbLines = explode("\n", trim($dbMsg));
assertRule($dbLines === $expectedDbLines, "Rule 1 & 4: Loaded from AFV in exact order with masked Aadhaar", "Got:\n{$dbMsg}");

// --- TEST 7: Rule 8 & 10 - Exact assigned operator & No duplicate on retry ---
echo "\n--- TEST 7: Rule 8 & 10 - Exact assigned operator and idempotency ---\n";
// Operator 311 is assigned to Gaya > Konch (BCCCO, BICCO)
$op311 = $db->query("SELECT id, mobile FROM users WHERE id = 311")->fetch(PDO::FETCH_ASSOC);
if ($op311) {
    // Find an application for Gaya > Konch
    $app886 = $db->query("SELECT id, district_id, block_id, application_code FROM applications WHERE id = 886")->fetch(PDO::FETCH_ASSOC);
    if ($app886) {
        $q1 = WhatsAppNotificationService::queueSubmissionNotification(
            (int)$app886['id'],
            (int)$app886['district_id'],
            (int)$app886['block_id'],
            ['district_name' => 'Gaya', 'block_name' => 'Konch', 'application_code' => $app886['application_code']],
            $db,
            311
        );
        assertRule($q1['queued_count'] === 1 || $q1['operators_found'] === 1, "Rule 8: Notification queued for exact assigned operator");

        // Retry same call
        $q2 = WhatsAppNotificationService::queueSubmissionNotification(
            (int)$app886['id'],
            (int)$app886['district_id'],
            (int)$app886['block_id'],
            ['district_name' => 'Gaya', 'block_name' => 'Konch', 'application_code' => $app886['application_code']],
            $db,
            311
        );
        $outboxCount = (int)$db->query("SELECT COUNT(*) FROM whatsapp_notification_outbox WHERE application_id = 886 AND recipient_user_id = 311")->fetchColumn();
        assertRule($outboxCount === 1, "Rule 10: Retrying does NOT insert duplicate outbox message (Count: {$outboxCount})");
    }
}

echo "\n====================================================================\n";
echo "SUMMARY: {$testsPassed} PASSED, {$testsFailed} FAILED\n";
echo "====================================================================\n";
