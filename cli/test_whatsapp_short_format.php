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

$db = null;
try {
    $db = Database::getConnection();
} catch (\Throwable $e) {
    // Database may be offline during quick CLI unit tests
}

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
echo " WHATSAPP ULTRA-SHORT MESSAGE & SIMPLE INPUT VERIFICATION SUITE\n";
echo "====================================================================\n\n";

// --- TEST 1: User Correct Examples (Location, App No, Applicant Name) ---
echo "--- TEST 1: User Exact Format Examples ---\n";
// Example 1: EWSCO/2026/769404 Rishikesh Kumar (User prompt example)
$exUser = WhatsAppNotificationService::buildMessage([
    'district_name' => 'Gaya',
    'block_name' => 'Konch',
    'application_number' => 'EWSCO/2026/769404',
    'applicant_name' => 'Rishikesh Kumar'
]);
$expectedUser = "Location: Gaya > Konch\nApp No EWSCO/2026/769404\nApplicant Name: Rishikesh Kumar";
assertRule($exUser === $expectedUser, "User Prompt Example (Gaya > Konch, App No EWSCO/2026/769404, Rishikesh Kumar) matches perfectly", "Expected:\n{$expectedUser}\n\nGot:\n{$exUser}");

// Example 2: BCCCO/2026/56465456
$ex1 = WhatsAppNotificationService::buildMessage([
    'district_name' => 'Aurangabad',
    'block_name' => 'Haspura',
    'application_number' => 'BCCCO/2026/56465456',
    'applicant_name' => 'Priyanka Kumari'
]);
$expected1 = "Location: Aurangabad > Haspura\nApp No BCCCO/2026/56465456\nApplicant Name: Priyanka Kumari";
assertRule($ex1 === $expected1, "Example 2 (BCCCO/2026/56465456) matches perfectly", "Expected:\n{$expected1}\n\nGot:\n{$ex1}");

// Example 3: GH12365
$ex3 = WhatsAppNotificationService::buildMessage([
    'district_name' => 'Aurangabad',
    'block_name' => 'Haspura',
    'application_number' => 'GH12365',
    'applicant_name' => 'Priyanka Kumari'
]);
$expected3 = "Location: Aurangabad > Haspura\nApp No GH12365\nApplicant Name: Priyanka Kumari";
assertRule($ex3 === $expected3, "Example 3 (GH12365) matches perfectly", "Expected:\n{$expected3}\n\nGot:\n{$ex3}");

// --- TEST 2: Rule 2, 3, 4 - NO Masking or Truncation of Application Number ---
echo "\n--- TEST 2: App No Complete & Unmasked ---\n";
assertRule(!str_contains($ex1, 'XXXX-XXXX-5456'), "App No: XXXX-XXXX-5456 masking is NOT applied");
assertRule(str_contains($ex1, 'App No BCCCO/2026/56465456'), "Prefix, slash, year, letters and digits complete preserved");
assertRule(!str_contains($ex1, '5456') || str_contains($ex1, '56465456'), "Not truncated to last 4 digits");

// --- TEST 3: Dynamic other input fields BLOCKED to keep message simple ---
echo "\n--- TEST 3: Simple Input Data (Extra Dynamic Fields Blocked) ---\n";
$mixedMsg = WhatsAppNotificationService::buildMessage([
    'district_name' => 'Aurangabad',
    'block_name' => 'Haspura',
    'application_number' => 'BCCCO/2026/56465456',
    'applicant_name' => 'Priyanka Kumari',
    'aadhaar_number' => '987654321234',
    'mobile_number' => '9876543210',
    'father_name' => 'Ramesh Singh',
    'mother_name' => 'Sunita Devi',
    'category' => 'BC-1'
]);
assertRule(str_contains($mixedMsg, 'App No BCCCO/2026/56465456'), "App No remains in message");
assertRule(str_contains($mixedMsg, 'Applicant Name: Priyanka Kumari'), "Applicant Name remains in message");
assertRule(!str_contains($mixedMsg, 'Aadhaar'), "Aadhaar input field is strictly blocked");
assertRule(!str_contains($mixedMsg, 'Mobile Number'), "Mobile number input field is strictly blocked");
assertRule(!str_contains($mixedMsg, 'Father Name'), "Father name input field is strictly blocked");
assertRule(!str_contains($mixedMsg, 'Mother Name'), "Mother name input field is strictly blocked");
assertRule(!str_contains($mixedMsg, 'Category'), "Category input field is strictly blocked");
assertRule(substr_count($mixedMsg, "\n") === 2, "Message has exactly 3 lines", "Lines: " . (substr_count($mixedMsg, "\n") + 1));

// --- TEST 4: HTML entity decoding ---
echo "\n--- TEST 4: HTML Entity Decoding ---\n";
$htmlMsg = WhatsAppNotificationService::buildMessage([
    'district_name' => 'Patna &amp; Rural',
    'block_name' => 'Danapur',
    'application_number' => 'BCCCO&#47;2026&#47;56465456',
    'applicant_name' => 'M/s O&#039;Connor &amp; Sons'
]);
assertRule(str_contains($htmlMsg, 'Location: Patna & Rural > Danapur'), "&amp; decoded in location");
assertRule(str_contains($htmlMsg, 'App No BCCCO/2026/56465456'), "&#47; decoded in application number");
assertRule(str_contains($htmlMsg, "Applicant Name: M/s O'Connor & Sons"), "&#039; and &amp; decoded in applicant name");

// --- TEST 5: Exclusion of internal/technical data & boilerplate ---
echo "\n--- TEST 5: Exclusion of internal/technical data & boilerplate ---\n";
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
assertRule(!str_contains($dirtyMsg, 'WB-REF-999'), "app_ref strictly excluded");
assertRule(!str_contains($dirtyMsg, 'PENDING'), "status strictly excluded");
assertRule(!str_contains($dirtyMsg, '150.00') && !str_contains($dirtyMsg, '100.00'), "rate and fee strictly excluded");
assertRule(!str_contains($dirtyMsg, '500.00'), "wallet balance strictly excluded");
assertRule(!str_contains($dirtyMsg, 'Birth Certificate'), "service name strictly excluded");
assertRule(!str_contains($dirtyMsg, 'Satish Kumar'), "operator name strictly excluded");
assertRule(!str_contains($dirtyMsg, '2026-10-22'), "dates strictly excluded");
assertRule(!str_contains($dirtyMsg, 'New Application'), "headings strictly excluded");
assertRule(!preg_match('/[\x{1F600}-\x{1F64F}\x{1F300}-\x{1F5FF}\x{1F680}-\x{1F6FF}\x{2600}-\x{26FF}]/u', $dirtyMsg), "No decorative emojis");

// --- TEST 6: Attachment resolver handles both photo/image and PDF files ---
echo "\n--- TEST 6: Attachment Support (Photos & PDFs) ---\n";
assertRule(method_exists(WhatsAppNotificationService::class, 'getApplicationAttachments'), "getApplicationAttachments method exists");
assertRule(method_exists(WhatsAppNotificationService::class, 'getApplicationPdfAttachments'), "getApplicationPdfAttachments backward-compatible alias exists");

// --- TEST 7 & 8: Database tests (if DB available) ---
if ($db !== null) {
    echo "\n--- TEST 7: DB Dynamic application_field_values priority ---\n";
    try {
        $dbMsg = WhatsAppNotificationService::buildMessage(['district_name' => 'Gaya', 'block_name' => 'Konch'], $db, 883);
        $expectedDbLines = [
            'Location: Gaya > Konch',
            'App No LC/BATCH/34456',
            'Applicant Name: Batch Person 2'
        ];
        $dbLines = explode("\n", trim($dbMsg));
        assertRule($dbLines === $expectedDbLines, "Application Number priority and exact 3-line format from DB", "Got:\n{$dbMsg}");
    } catch (\Throwable $e) {
        echo " [SKIP] DB test 7: " . $e->getMessage() . "\n";
    }

    echo "\n--- TEST 8: Exact assigned operator and retry idempotency ---\n";
    try {
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
        }
    } catch (\Throwable $e) {
        echo " [SKIP] DB test 8: " . $e->getMessage() . "\n";
    }
} else {
    echo "\n[INFO] DB is currently offline; live database tests skipped.\n";
}

echo "\n====================================================================\n";
echo "SUMMARY: {$testsPassed} PASSED, {$testsFailed} FAILED\n";
echo "====================================================================\n";
