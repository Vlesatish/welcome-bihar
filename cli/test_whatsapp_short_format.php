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
echo " WHATSAPP SHORT MESSAGE & APPLICATION NUMBER PRESERVATION SUITE\n";
echo "====================================================================\n\n";

// --- TEST 1: User Correct Examples (Preserving Full Application Number) ---
echo "--- TEST 1: User Correct Examples ---\n";
// Example 1
$ex1 = WhatsAppNotificationService::buildMessage([
    'district_name' => 'Aurangabad',
    'block_name' => 'Haspura',
    'application_number' => 'BCCCO/2026/56465456',
    'applicant_name' => 'Priyanka Kumari'
]);
$expected1 = "Location: Aurangabad > Haspura\nApplication Number: BCCCO/2026/56465456\nApplicant Name: Priyanka Kumari";
assertRule($ex1 === $expected1, "User Example 1 (BCCCO/2026/56465456) matches perfectly", "Expected:\n{$expected1}\n\nGot:\n{$ex1}");

// Example 2
$ex2 = WhatsAppNotificationService::buildMessage([
    'district_name' => 'Aurangabad',
    'block_name' => 'Haspura',
    'application_number' => 'BCCCO/2026/646464694',
    'applicant_name' => 'Priyanka Kumari'
]);
$expected2 = "Location: Aurangabad > Haspura\nApplication Number: BCCCO/2026/646464694\nApplicant Name: Priyanka Kumari";
assertRule($ex2 === $expected2, "User Example 2 (BCCCO/2026/646464694) matches perfectly", "Expected:\n{$expected2}\n\nGot:\n{$ex2}");

// Example 3
$ex3 = WhatsAppNotificationService::buildMessage([
    'district_name' => 'Aurangabad',
    'block_name' => 'Haspura',
    'application_number' => 'GH12365',
    'applicant_name' => 'Priyanka Kumari'
]);
$expected3 = "Location: Aurangabad > Haspura\nApplication Number: GH12365\nApplicant Name: Priyanka Kumari";
assertRule($ex3 === $expected3, "User Example 3 (GH12365) matches perfectly", "Expected:\n{$expected3}\n\nGot:\n{$ex3}");

// --- TEST 2: Rule 2, 3, 4 - NO Masking or Truncation of Application Number ---
echo "\n--- TEST 2: Rule 2, 3, 4 - NO Masking or Truncation on Application Number ---\n";
assertRule(!str_contains($ex1, 'XXXX-XXXX-5456'), "Rule 2: XXXX-XXXX-5456 masking is NOT applied to Application Number");
assertRule(str_contains($ex1, 'Application Number: BCCCO/2026/56465456'), "Rule 3: Prefix, slash, year, letters and digits complete preserved");
assertRule(!str_contains($ex1, '5456') || str_contains($ex1, '56465456'), "Rule 4: Not truncated to last 4 digits");

// --- TEST 3: Rule 5 & 7 - Sensitive Aadhaar IS Masked, Application Number is NOT ---
echo "\n--- TEST 3: Rule 5 & 7 - Aadhaar Masked, Application Number NOT Masked ---\n";
$mixedMsg = WhatsAppNotificationService::buildMessage([
    'district_name' => 'Aurangabad',
    'block_name' => 'Haspura',
    'application_number' => 'BCCCO/2026/56465456',
    'applicant_name' => 'Priyanka Kumari',
    'aadhaar_number' => '987654321234',
    'mobile_number' => '9876543210'
]);
assertRule(str_contains($mixedMsg, 'Application Number: BCCCO/2026/56465456'), "Application Number remains completely unmasked in mixed message");
assertRule(str_contains($mixedMsg, 'Aadhaar Number: XXXX-XXXX-1234'), "Rule 7: Aadhaar Number masked to XXXX-XXXX-1234");
assertRule(str_contains($mixedMsg, 'Mobile Number: 9876543210'), "Rule 6: Mobile Number complete and unmasked");

// --- TEST 4: Rule 9 - HTML entity decoding ---
echo "\n--- TEST 4: Rule 9 - HTML entity decoding ---\n";
$htmlMsg = WhatsAppNotificationService::buildMessage([
    'district_name' => 'Patna &amp; Rural',
    'block_name' => 'Danapur',
    'application_number' => 'BCCCO&#47;2026&#47;56465456',
    'applicant_name' => 'M/s O&#039;Connor &amp; Sons'
]);
assertRule(str_contains($htmlMsg, 'Location: Patna & Rural > Danapur'), "Rule 9: &amp; decoded in location");
assertRule(str_contains($htmlMsg, 'Application Number: BCCCO/2026/56465456'), "Rule 9: &#47; decoded in application number");
assertRule(str_contains($htmlMsg, "Applicant Name: M/s O'Connor & Sons"), "Rule 9: &#039; and &amp; decoded in applicant name");

// --- TEST 5: Rule 6 & 8 - Exclusion of internal/technical data & boilerplate ---
echo "\n--- TEST 5: Rule 6 & 8 - Exclusion of internal/technical data & boilerplate ---\n";
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
assertRule(!str_contains($dirtyMsg, 'WB-REF-999'), "Rule 8: app_ref strictly excluded");
assertRule(!str_contains($dirtyMsg, 'PENDING'), "Rule 8: status strictly excluded");
assertRule(!str_contains($dirtyMsg, '150.00') && !str_contains($dirtyMsg, '100.00'), "Rule 8: rate and fee strictly excluded");
assertRule(!str_contains($dirtyMsg, '500.00'), "Rule 8: wallet balance strictly excluded");
assertRule(!str_contains($dirtyMsg, 'Birth Certificate'), "Rule 8: service name strictly excluded");
assertRule(!str_contains($dirtyMsg, 'Satish Kumar'), "Rule 8: operator name strictly excluded");
assertRule(!str_contains($dirtyMsg, '2026-10-22'), "Rule 8: dates strictly excluded");
assertRule(!str_contains($dirtyMsg, 'New Application'), "Rule 8: headings strictly excluded");
assertRule(!preg_match('/[\x{1F600}-\x{1F64F}\x{1F300}-\x{1F5FF}\x{1F680}-\x{1F6FF}\x{2600}-\x{26FF}]/u', $dirtyMsg), "No decorative emojis");

// --- TEST 6: Rule 7 - Skip empty, null, whitespace, N/A, unchecked ---
echo "\n--- TEST 6: Rule 7 - Skip empty, null, whitespace, N/A, unchecked ---\n";
$emptyMsg = WhatsAppNotificationService::buildMessage([
    'district_name' => 'Gaya',
    'block_name' => 'Konch',
    'applicant_name' => 'Pankaj Kumar',
    'application_number' => 'BICCO/2026/12345',
    'empty_field_1' => '',
    'empty_field_2' => '   ',
    'na_field_1' => 'N/A',
    'na_field_2' => 'NA',
    'dash_field' => '-',
    'null_field' => 'NULL',
    'unchecked_field' => 'UNCHECKED'
]);
assertRule(!str_contains($emptyMsg, 'Empty Field'), "Empty fields skipped");
assertRule(!str_contains($emptyMsg, 'Na Field'), "N/A and NA fields skipped");
assertRule(!str_contains($emptyMsg, 'Dash Field'), "Dash fields skipped");
assertRule(!str_contains($emptyMsg, 'Null Field'), "Null fields skipped");
assertRule(!str_contains($emptyMsg, 'Unchecked Field'), "Unchecked fields skipped");

// --- TEST 7: Rule 11 - Duplicate field ek baar hi bhejo ---
echo "\n--- TEST 7: Rule 11 - Deduplication of fields ---\n";
$dupMsg = WhatsAppNotificationService::buildMessage([
    'district_name' => 'Gaya',
    'block_name' => 'Konch',
    'application_number' => 'BICCO/2026/12345',
    'applicant_name' => 'Pankaj Kumar',
    'fields' => [
        ['field_name' => 'application_number', 'field_value' => 'BICCO/2026/12345', 'label' => 'Application Number'],
        ['field_name' => 'applicant_name', 'field_value' => 'Pankaj Kumar', 'label' => 'Applicant Name'],
        ['field_name' => 'mobile_number', 'field_value' => '9876543210', 'label' => 'Mobile Number']
    ],
    'mobile_number' => '9876543210'
]);
$appNumCount = substr_count($dupMsg, 'Application Number');
$applicantCount = substr_count($dupMsg, 'Applicant Name');
$mobileCount = substr_count($dupMsg, 'Mobile Number');
assertRule($appNumCount === 1, "Rule 11: Duplicate Application Number appears exactly once", "Count: {$appNumCount}");
assertRule($applicantCount === 1, "Rule 11: Duplicate Applicant Name appears exactly once", "Count: {$applicantCount}");
assertRule($mobileCount === 1, "Rule 11: Duplicate Mobile Number appears exactly once", "Count: {$mobileCount}");

// --- TEST 8: Rule 1 & 10 - Dynamic values from application_field_values priority ---
echo "\n--- TEST 8: Rule 1 & 10 - Fetch from DB application_field_values ---\n";
$dbMsg = WhatsAppNotificationService::buildMessage(['district_name' => 'Gaya', 'block_name' => 'Konch'], $db, 883);
$expectedDbLines = [
    'Location: Gaya > Konch',
    'Application Number: LC/BATCH/34456',
    'Applicant Name: Batch Person 2',
    'Aadhaar Number: XXXX-XXXX-7671'
];
$dbLines = explode("\n", trim($dbMsg));
assertRule($dbLines === $expectedDbLines, "Rule 1 & 10: Application Number priority and exact order", "Got:\n{$dbMsg}");

// --- TEST 9: Exact assigned operator and retry idempotency ---
echo "\n--- TEST 9: Exact assigned operator and retry idempotency ---\n";
$op311 = $db->query("SELECT id, mobile FROM users WHERE id = 311")->fetch(PDO::FETCH_ASSOC);
if ($op311) {
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
        assertRule($q1['queued_count'] === 1 || $q1['operators_found'] === 1, "Notification queued for exact assigned operator");

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
        assertRule($outboxCount === 1, "Retrying does NOT insert duplicate outbox message (Count: {$outboxCount})");
    }
}

echo "\n====================================================================\n";
echo "SUMMARY: {$testsPassed} PASSED, {$testsFailed} FAILED\n";
echo "====================================================================\n";
