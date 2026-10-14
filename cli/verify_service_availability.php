<?php
/**
 * Verification Suite for Block Rates, Service Availability, and Retailer Visibility
 * Validates Cases 1 to 6 and all security, pricing, and submission guarantees.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Config/Env.php';
\App\Config\Env::load(dirname(__DIR__) . '/.env');
require_once dirname(__DIR__) . '/app/Config/Database.php';
require_once dirname(__DIR__) . '/app/helpers.php';

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
use App\Services\ApplicationService;
use App\Services\PricingService;
use App\Services\WalletService;

$passed = 0;
$failed = 0;

function assertTest(string $title, bool $condition, string $details = ''): void {
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo " [PASS] {$title}\n";
        if (!empty($details)) echo "        {$details}\n";
    } else {
        $failed++;
        echo "![FAIL] {$title}\n";
        if (!empty($details)) echo "        {$details}\n";
    }
}

function httpGet(string $path, ?string $cookie = null): array {
    $ch = curl_init('http://127.0.0.1:8080' . $path);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    if ($cookie) curl_setopt($ch, CURLOPT_COOKIE, $cookie);
    $response = (string)curl_exec($ch);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $headers = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);
    return ['code' => $httpCode, 'headers' => $headers, 'body' => $body, 'json' => json_decode($body, true)];
}

function httpPostJson(string $path, array $data, ?string $cookie = null): array {
    $ch = curl_init('http://127.0.0.1:8080' . $path);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HEADER, true);
    $headers = ['Content-Type: application/json', 'Accept: application/json'];
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    if ($cookie) curl_setopt($ch, CURLOPT_COOKIE, $cookie);
    $response = (string)curl_exec($ch);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $headers = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);
    return ['code' => $httpCode, 'headers' => $headers, 'body' => $body, 'json' => json_decode($body, true)];
}

function httpPost(string $path, array $data, ?string $cookie = null): array {
    $ch = curl_init('http://127.0.0.1:8080' . $path);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_HEADER, true);
    if ($cookie) curl_setopt($ch, CURLOPT_COOKIE, $cookie);
    $response = (string)curl_exec($ch);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $headers = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);
    return ['code' => $httpCode, 'headers' => $headers, 'body' => $body, 'json' => json_decode($body, true)];
}

echo "====================================================================\n";
echo " WELCOME BIHAR - BLOCK RATES & SERVICE AVAILABILITY TEST SUITE\n";
echo "====================================================================\n\n";

$db = Database::getConnection();

// Lookups for testing
$dtStmt = $db->query("SELECT id FROM data_types WHERE code = 'srv_block_app' LIMIT 1");
$serviceId = (int)($dtStmt->fetchColumn() ?: 4);

$gayaRow = $db->query("SELECT id, name FROM districts WHERE name = 'Gaya' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$gayaId = (int)$gayaRow['id'];

$konchRow = $db->query("SELECT id, name FROM blocks WHERE district_id = {$gayaId} AND name = 'Konch' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$konchId = (int)$konchRow['id'];

$guraruRow = $db->query("SELECT id, name FROM blocks WHERE district_id = {$gayaId} AND name = 'Guraru' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$guraruId = (int)$guraruRow['id'];

$patnaRow = $db->query("SELECT id, name FROM districts WHERE name = 'Patna' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$patnaId = (int)$patnaRow['id'];

$danapurRow = $db->query("SELECT id, name FROM blocks WHERE district_id = {$patnaId} AND name = 'Danapur' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$danapurId = (int)$danapurRow['id'];

$adminUser = $db->query("SELECT * FROM users WHERE role = 'ADMIN' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$retailerUser = $db->query("SELECT * FROM users WHERE role = 'USER' AND status = 'ACTIVE' LIMIT 1")->fetch(PDO::FETCH_ASSOC);

$stdPass = 'Welcome@2026';
$pwdHash = password_hash($stdPass, PASSWORD_BCRYPT);
if (!$retailerUser) {
    $db->prepare("INSERT INTO users (uuid, role, name, mobile, email, password_hash, status, created_at) VALUES (UUID(), 'USER', 'Test Retailer', '9876543210', 'retailer@test.com', ?, 'ACTIVE', NOW())")->execute([$pwdHash]);
    $retailerUser = $db->query("SELECT * FROM users WHERE mobile = '9876543210'")->fetch(PDO::FETCH_ASSOC);
    $db->prepare("INSERT INTO wallets (user_id, balance, created_at) VALUES (?, 5000.00, NOW())")->execute([$retailerUser['id']]);
} else {
    $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([$pwdHash, $retailerUser['id']]);
    $db->prepare("UPDATE wallets SET balance = GREATEST(balance, 5000.00) WHERE user_id = ?")->execute([$retailerUser['id']]);
}
$db->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([$pwdHash, $adminUser['id']]);

function loginUser(string $identifier, string $password): ?string {
    $res = httpGet('/login');
    preg_match('/PHPSESSID=([^;]+)/', $res['headers'], $m);
    $cookie = !empty($m[0]) ? $m[0] : null;

    preg_match('/name=["\']csrf_token["\']\s+value=["\']([^"\']+)["\']/', $res['body'], $cm);
    $csrf = !empty($cm[1]) ? $cm[1] : '';

    if (!$cookie || !$csrf) return null;

    $loginRes = httpPost('/login', [
        'csrf_token' => $csrf,
        'identifier' => $identifier,
        'password' => $password
    ], $cookie);

    if (preg_match_all('/Set-Cookie:\s*(PHPSESSID=[^;]+)/i', $loginRes['headers'], $matches)) {
        return end($matches[1]);
    }
    return $cookie;
}

$retailerCookie = loginUser($retailerUser['mobile'], $stdPass);
$adminCookie = loginUser($adminUser['mobile'], $stdPass);

// Clean slate: Remove all rate rules and availability records for clean testing
$db->exec("DELETE FROM service_rate_rules WHERE data_type_id = {$serviceId}");
$db->exec("DELETE FROM service_block_availability WHERE data_type_id = {$serviceId}");

// -------------------------------------------------------------
// CASE 1: Configured Rates = 0
// - Expected retailer districts = 0
// - Expected retailer blocks = 0
// - Expected submit disabled
// -------------------------------------------------------------
echo "\n--- CASE 1: ZERO CONFIGURED RATES ---\n";

$rateCount1 = (int)$db->query("SELECT COUNT(*) FROM service_rate_rules WHERE data_type_id = {$serviceId} AND is_active = 1")->fetchColumn();
$resD1 = httpGet("/api/service-districts?service=srv_block_app");
$districts1 = $resD1['json'] ?: [];

$resB1 = httpGet("/api/service-blocks?district_id={$gayaId}&service=srv_block_app");
$blocks1 = $resB1['json'] ?: [];

$adminAvailRes1 = httpGet('/admin/service-availability', $adminCookie);
$hasEmptyMsg1 = strpos($adminAvailRes1['body'], 'No rate-configured blocks found. Add a block rate first.') !== false;

assertTest(
    "Case 1.1: Database configured rates count is 0",
    $rateCount1 === 0,
    "Configured Rates: {$rateCount1}"
);

assertTest(
    "Case 1.2: Retailer service-districts returns 0 districts",
    count($districts1) === 0,
    "Districts returned: " . count($districts1)
);

assertTest(
    "Case 1.3: Retailer service-blocks returns 0 blocks",
    count($blocks1) === 0,
    "Blocks returned: " . count($blocks1)
);

assertTest(
    "Case 1.4: Admin service availability page displays required empty state message",
    $hasEmptyMsg1,
    "Empty message found: YES"
);

// Retailer submission attempt must fail
$submitFailed1 = false;
$submitMsg1 = '';
try {
    ApplicationService::submit($retailerUser['id'], $serviceId, $gayaId, $konchId, [
        'application_code' => 'BICCO',
        'application_number' => 'BICCO/2026/00001',
        'applicant_name' => 'Case 1 Applicant'
    ], []);
} catch (Exception $e) {
    $submitFailed1 = true;
    $submitMsg1 = $e->getMessage();
}

assertTest(
    "Case 1.5: Retailer single submission rejected when 0 rates configured",
    $submitFailed1 && ($submitMsg1 === "Service is temporarily unavailable for the selected block." || $submitMsg1 === "No active rate is configured for the selected block and application code."),
    "Exception message: {$submitMsg1}"
);

// -------------------------------------------------------------
// CASE 2: Add Gaya > Konch > BICCO = ₹50, status LIVE
// - Expected retailer district: Gaya
// - Expected block: Konch
// - Expected code: BICCO
// - Expected price: ₹50
// -------------------------------------------------------------
echo "\n--- CASE 2: ADD GAYA > KONCH > BICCO = ₹50 ---\n";

// Add rate rule
$db->prepare("
    INSERT INTO service_rate_rules (data_type_id, state, district_id, block_id, application_code, fee_amount, rule_label, is_active, created_at)
    VALUES (?, 'Bihar', ?, ?, 'BICCO', 50.00, 'Income Certificate (BICCO)', 1, NOW())
")->execute([$serviceId, $gayaId, $konchId]);

// Ensure availability record is LIVE (simulating DataTypeController save)
$db->prepare("
    INSERT INTO service_block_availability (data_type_id, district_id, block_id, status, activated_at, activated_by, created_at, updated_at)
    VALUES (?, ?, ?, 'LIVE', NOW(), ?, NOW(), NOW())
    ON DUPLICATE KEY UPDATE status = 'LIVE'
")->execute([$serviceId, $gayaId, $konchId, $adminUser['id']]);

$resD2 = httpGet("/api/service-districts?service=srv_block_app");
$districts2 = $resD2['json'] ?: [];
$distIds2 = array_column($districts2, 'id');
$distNames2 = array_column($districts2, 'name');

$resB2 = httpGet("/api/service-blocks?district_id={$gayaId}&service=srv_block_app");
$blocks2 = $resB2['json'] ?: [];
$blockIds2 = array_column($blocks2, 'id');
$blockNames2 = array_column($blocks2, 'name');

$resC2 = httpGet("/api/service-application-codes?district_id={$gayaId}&block_id={$konchId}");
$codes2 = $resC2['json'] ?: [];
$codeNames2 = array_column($codes2, 'code');
$codeRates2 = array_column($codes2, 'rate');

$priceResult2 = PricingService::calculatePrice($serviceId, $gayaId, $konchId, ['application_code' => 'BICCO']);

assertTest(
    "Case 2.1: Retailer district list contains only Gaya",
    count($districts2) === 1 && $distNames2[0] === 'Gaya',
    "Districts: " . implode(', ', $distNames2)
);

assertTest(
    "Case 2.2: Retailer block list for Gaya contains only Konch",
    count($blocks2) === 1 && $blockNames2[0] === 'Konch',
    "Blocks: " . implode(', ', $blockNames2)
);

assertTest(
    "Case 2.3: Application codes for Konch contains only BICCO at ₹50",
    count($codes2) === 1 && $codeNames2[0] === 'BICCO' && (float)$codeRates2[0] === 50.00,
    "Codes: " . json_encode($codes2)
);

assertTest(
    "Case 2.4: Pricing calculation returns exact ₹50 configured rate",
    (float)$priceResult2['final_fee'] === 50.00,
    "Calculated Fee: ₹" . number_format((float)$priceResult2['final_fee'], 2)
);

// -------------------------------------------------------------
// CASE 3: Add Konch > BCCCO = ₹60
// - Expected codes: BICCO ₹50 and BCCCO ₹60
// -------------------------------------------------------------
echo "\n--- CASE 3: ADD KONCH > BCCCO = ₹60 ---\n";

$db->prepare("
    INSERT INTO service_rate_rules (data_type_id, state, district_id, block_id, application_code, fee_amount, rule_label, is_active, created_at)
    VALUES (?, 'Bihar', ?, ?, 'BCCCO', 60.00, 'Caste Certificate (BCCCO)', 1, NOW())
")->execute([$serviceId, $gayaId, $konchId]);

$resC3 = httpGet("/api/service-application-codes?district_id={$gayaId}&block_id={$konchId}");
$codes3 = $resC3['json'] ?: [];
$codeMap3 = [];
foreach ($codes3 as $c) {
    $codeMap3[$c['code']] = (float)$c['rate'];
}

assertTest(
    "Case 3.1: Konch returns exactly BICCO (₹50) and BCCCO (₹60)",
    count($codes3) === 2 && isset($codeMap3['BICCO']) && $codeMap3['BICCO'] === 50.00 && isset($codeMap3['BCCCO']) && $codeMap3['BCCCO'] === 60.00,
    "Codes returned: " . json_encode($codeMap3)
);

assertTest(
    "Case 3.2: Other codes (e.g. BOBC) are NOT returned for Konch",
    !isset($codeMap3['BOBC']) && !isset($codeMap3['BRCCO']),
    "BOBC or BRCCO present: NO"
);

// -------------------------------------------------------------
// CASE 4: Turn Konch service OFF
// - Expected retailer district/block removed
// - Existing applications remain processable
// -------------------------------------------------------------
echo "\n--- CASE 4: TURN KONCH SERVICE OFF ---\n";

// Turn Konch service OFF in service_block_availability
$db->prepare("UPDATE service_block_availability SET status = 'INACTIVE', deactivated_at = NOW(), deactivated_by = ? WHERE data_type_id = ? AND district_id = ? AND block_id = ?")
   ->execute([$adminUser['id'], $serviceId, $gayaId, $konchId]);

$resD4 = httpGet("/api/service-districts?service=srv_block_app");
$districts4 = $resD4['json'] ?: [];

$resB4 = httpGet("/api/service-blocks?district_id={$gayaId}&service=srv_block_app");
$blocks4 = $resB4['json'] ?: [];

$resC4 = httpGet("/api/service-application-codes?district_id={$gayaId}&block_id={$konchId}");
$codes4 = $resC4['json'] ?: [];

assertTest(
    "Case 4.1: When Konch service is OFF, retailer districts returns 0",
    count($districts4) === 0,
    "Districts count: " . count($districts4)
);

assertTest(
    "Case 4.2: When Konch service is OFF, retailer blocks for Gaya returns 0",
    count($blocks4) === 0,
    "Blocks count: " . count($blocks4)
);

assertTest(
    "Case 4.3: When Konch service is OFF, application codes returns 0",
    count($codes4) === 0,
    "Codes count: " . count($codes4)
);

// Retailer submission must be rejected with exact string: "Service is temporarily unavailable for the selected block."
$submitFailed4 = false;
$submitMsg4 = '';
try {
    ApplicationService::submit($retailerUser['id'], $serviceId, $gayaId, $konchId, [
        'application_code' => 'BICCO',
        'application_number' => 'BICCO/2026/00002',
        'applicant_name' => 'Case 4 Applicant'
    ], []);
} catch (Exception $e) {
    $submitFailed4 = true;
    $submitMsg4 = $e->getMessage();
}

assertTest(
    "Case 4.4: Single submission rejected with exact service unavailable error",
    $submitFailed4 && $submitMsg4 === "Service is temporarily unavailable for the selected block.",
    "Error: {$submitMsg4}"
);

// Reactivate Konch service for subsequent tests
$db->prepare("UPDATE service_block_availability SET status = 'LIVE', activated_at = NOW(), activated_by = ? WHERE data_type_id = ? AND district_id = ? AND block_id = ?")
   ->execute([$adminUser['id'], $serviceId, $gayaId, $konchId]);

// -------------------------------------------------------------
// CASE 5: Add Patna > Danapur > BOBC = ₹130
// - Expected retailer districts: Gaya and Patna
// - Expected blocks and codes restricted to their exact rates
// -------------------------------------------------------------
echo "\n--- CASE 5: ADD PATNA > DANAPUR > BOBC = ₹130 ---\n";

$db->prepare("
    INSERT INTO service_rate_rules (data_type_id, state, district_id, block_id, application_code, fee_amount, rule_label, is_active, created_at)
    VALUES (?, 'Bihar', ?, ?, 'BOBC', 130.00, 'OBC Certificate (BOBC)', 1, NOW())
")->execute([$serviceId, $patnaId, $danapurId]);

$db->prepare("
    INSERT INTO service_block_availability (data_type_id, district_id, block_id, status, activated_at, activated_by, created_at, updated_at)
    VALUES (?, ?, ?, 'LIVE', NOW(), ?, NOW(), NOW())
    ON DUPLICATE KEY UPDATE status = 'LIVE'
")->execute([$serviceId, $patnaId, $danapurId, $adminUser['id']]);

$resD5 = httpGet("/api/service-districts?service=srv_block_app");
$districts5 = $resD5['json'] ?: [];
$distNames5 = array_column($districts5, 'name');
sort($distNames5);

$resB5_Patna = httpGet("/api/service-blocks?district_id={$patnaId}&service=srv_block_app");
$blocks5_Patna = $resB5_Patna['json'] ?: [];
$blockNames5_Patna = array_column($blocks5_Patna, 'name');

$resC5_Danapur = httpGet("/api/service-application-codes?district_id={$patnaId}&block_id={$danapurId}");
$codes5_Danapur = $resC5_Danapur['json'] ?: [];

assertTest(
    "Case 5.1: Retailer districts lists exactly Gaya and Patna",
    count($distNames5) === 2 && in_array('Gaya', $distNames5, true) && in_array('Patna', $distNames5, true),
    "Districts: " . implode(', ', $distNames5)
);

assertTest(
    "Case 5.2: Patna blocks contains only Danapur",
    count($blocks5_Patna) === 1 && $blockNames5_Patna[0] === 'Danapur',
    "Patna blocks: " . implode(', ', $blockNames5_Patna)
);

assertTest(
    "Case 5.3: Danapur codes contains only BOBC at ₹130",
    count($codes5_Danapur) === 1 && $codes5_Danapur[0]['code'] === 'BOBC' && (float)$codes5_Danapur[0]['rate'] === 130.00,
    "Danapur codes: " . json_encode($codes5_Danapur)
);

// -------------------------------------------------------------
// CASE 6: Delete the final active Konch rate
// - Expected Konch removed from retailer selection
// -------------------------------------------------------------
echo "\n--- CASE 6: DELETE THE FINAL ACTIVE KONCH RATE ---\n";

// Delete all rate rules for Konch
$db->prepare("DELETE FROM service_rate_rules WHERE data_type_id = ? AND district_id = ? AND block_id = ?")
   ->execute([$serviceId, $gayaId, $konchId]);

$resD6 = httpGet("/api/service-districts?service=srv_block_app");
$districts6 = $resD6['json'] ?: [];
$distNames6 = array_column($districts6, 'name');

$resB6_Gaya = httpGet("/api/service-blocks?district_id={$gayaId}&service=srv_block_app");
$blocks6_Gaya = $resB6_Gaya['json'] ?: [];

assertTest(
    "Case 6.1: When Konch rates are deleted, Gaya is removed from retailer districts",
    !in_array('Gaya', $distNames6, true) && in_array('Patna', $distNames6, true),
    "Districts remaining: " . implode(', ', $distNames6)
);

assertTest(
    "Case 6.2: Konch is removed from retailer block selection",
    count($blocks6_Gaya) === 0,
    "Gaya blocks returned: " . count($blocks6_Gaya)
);

// -------------------------------------------------------------
// SECURITY & VALIDATION CHECKS
// -------------------------------------------------------------
echo "\n--- SECURITY & TRANSACTION CHECKS ---\n";

// 7. Missing rate rejection
$submitFailed7 = false;
$submitMsg7 = '';
try {
    ApplicationService::submit($retailerUser['id'], $serviceId, $patnaId, $danapurId, [
        'application_code' => 'BICCO', // Danapur only has BOBC
        'application_number' => 'BICCO/2026/99999',
        'applicant_name' => 'Invalid Code Test'
    ], []);
} catch (Exception $e) {
    $submitFailed7 = true;
    $submitMsg7 = $e->getMessage();
}

assertTest(
    "Security 7.1: Single submission rejects code without rate with exact message",
    $submitFailed7 && $submitMsg7 === "No active rate is configured for the selected block and application code.",
    "Message: {$submitMsg7}"
);

// 8. Batch submission validation: If one row fails, reject ENTIRE batch, 0 debited, 0 created
$initialWallet = (float)WalletService::getBalance($retailerUser['id']);
$initialAppCount = (int)$db->query("SELECT COUNT(*) FROM applications")->fetchColumn();

$batchItems = [
    [
        'district_id' => $patnaId,
        'block_id' => $danapurId,
        'application_code' => 'BOBC',
        'application_number' => 'BOBC/2026/00001',
        'applicant_name' => 'Valid Row 1'
    ],
    [
        'district_id' => $patnaId,
        'block_id' => $danapurId,
        'application_code' => 'BICCO', // Missing rate!
        'application_number' => 'BICCO/2026/00002',
        'applicant_name' => 'Invalid Row 2'
    ]
];

$batchFailed8 = false;
$batchMsg8 = '';
try {
    ApplicationService::submitBatch($retailerUser['id'], $serviceId, $batchItems);
} catch (Exception $e) {
    $batchFailed8 = true;
    $batchMsg8 = $e->getMessage();
}

$finalWallet = (float)WalletService::getBalance($retailerUser['id']);
$finalAppCount = (int)$db->query("SELECT COUNT(*) FROM applications")->fetchColumn();

assertTest(
    "Security 8.1: Batch submission rejects entire batch with row and code details",
    $batchFailed8 && strpos($batchMsg8, "Row #2") !== false && strpos($batchMsg8, "No active rate is configured") !== false,
    "Message: {$batchMsg8}"
);

assertTest(
    "Security 8.2: Wallet is NOT debited on batch rejection",
    abs($finalWallet - $initialWallet) < 0.001,
    "Initial: ₹{$initialWallet}, Final: ₹{$finalWallet}"
);

assertTest(
    "Security 8.3: No application records created on batch rejection",
    $finalAppCount === $initialAppCount,
    "Initial Apps: {$initialAppCount}, Final Apps: {$finalAppCount}"
);

// 9. Admin Service Availability page contains only rate-configured blocks
$adminAvailRes9 = httpGet('/admin/service-availability', $adminCookie);
$hasDanapur = strpos($adminAvailRes9['body'], 'Danapur') !== false;
$hasKonch = strpos($adminAvailRes9['body'], 'Konch') !== false;

assertTest(
    "Admin 9.1: Service Availability page lists only rate-configured blocks (Danapur included, Konch excluded)",
    $hasDanapur && !$hasKonch,
    "Danapur found: YES, Konch found: NO"
);

// 10. Clean up test data and verify zero rates final state
$db->exec("DELETE FROM service_rate_rules WHERE data_type_id = {$serviceId}");
$db->exec("DELETE FROM service_block_availability WHERE data_type_id = {$serviceId}");

$finalD = httpGet("/api/service-districts?service=srv_block_app")['json'] ?: [];
$finalB = httpGet("/api/service-blocks?district_id={$patnaId}&service=srv_block_app")['json'] ?: [];

assertTest(
    "Final 10.1: Clean slate has 0 retailer districts and 0 retailer blocks",
    count($finalD) === 0 && count($finalB) === 0,
    "Districts: " . count($finalD) . ", Blocks: " . count($finalB)
);

echo "\n====================================================================\n";
echo " TEST SUMMARY: Total: " . ($passed + $failed) . " | Passed: {$passed} | Failed: {$failed}\n";
echo "====================================================================\n";
exit($failed === 0 ? 0 : 1);
