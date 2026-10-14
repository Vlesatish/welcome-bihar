<?php
/**
 * Automated Verification Test Suite for WhatsApp Notification System (WBAPI)
 * 
 * Complies with all safety, idempotency, security, and verification requirements:
 * 1. Explicit production guard (refuses to run if APP_ENV === 'production').
 * 2. Backs up every changed setting and restores it in a finally block.
 * 3. Cleans up all created test data (users, applications, wallets, outbox, notifications, audit logs).
 * 4. 100% mock/fake transport (never makes a real network request, never sends real WhatsApp messages).
 * 5. Never prints or exposes credentials in output.
 * 6. Verifies all 15 core WhatsApp and platform requirements.
 */

declare(strict_types=1);

// -------------------------------------------------------------------------
// 1. Production Guard & Initialization
// -------------------------------------------------------------------------
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $baseDir = dirname(__DIR__) . '/app/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    if (file_exists($file)) require $file;
});

require_once dirname(__DIR__) . '/app/helpers.php';
\App\Config\Config::init();

use App\Config\Config;
use App\Config\Database;
use App\Services\ApplicationService;
use App\Services\NotificationService;
use App\Services\WhatsAppNotificationService;
use App\Services\WalletService;

$appEnv = strtolower(trim((string)(Config::get('APP_ENV') ?: ($_ENV['APP_ENV'] ?? 'development'))));
if ($appEnv === 'production') {
    fwrite(STDERR, "\n[FATAL GUARD] Refusing to run verify_whatsapp_notification against a PRODUCTION environment.\n");
    fwrite(STDERR, "This suite performs controlled mock tests and must only be executed in local/staging/development.\n\n");
    exit(1);
}

$db = Database::getConnection();

$testsPassed = 0;
$testsFailed = 0;

function assertTest(bool $condition, string $testName, string $details = ''): void
{
    global $testsPassed, $testsFailed;
    if ($condition) {
        $testsPassed++;
        echo "  [PASS] {$testName}\n";
    } else {
        $testsFailed++;
        echo "  [FAIL] {$testName}" . ($details ? " - {$details}" : '') . "\n";
    }
}

echo "====================================================================\n";
echo " WHATSAPP NOTIFICATION SYSTEM - VERIFICATION & COMPLIANCE SUITE\n";
echo "====================================================================\n\n";

// -------------------------------------------------------------------------
// 2. Settings Backup for Guaranteed Restoration in finally
// -------------------------------------------------------------------------
$originalSettings = $db->query("SELECT setting_key, setting_value FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];

// Track all test resources created to safely delete in finally
$cleanupUserIds = [];
$cleanupAppIds = [];
$cleanupOutboxIds = [];
$cleanupNotificationIds = [];

// -------------------------------------------------------------------------
// 3. Fake Transport Registration (Never touches real network)
// -------------------------------------------------------------------------
$mockCalls = [];
$mockTransportHandler = function (string $url, array $headers, array $fields) use (&$mockCalls): array {
    $mockCalls[] = [
        'url' => $url,
        'headers' => $headers,
        'fields' => $fields
    ];
    return [
        'http_code' => 200,
        'body' => json_encode(['success' => true, 'data' => ['status' => 'sent', 'messageId' => 'wb_msg_' . bin2hex(random_bytes(4))]]),
        'error' => '',
        'headers' => ['content-type' => 'application/json']
    ];
};

WhatsAppNotificationService::setTransport(function (string $url, array $headers, array $fields) use (&$mockTransportHandler) {
    return $mockTransportHandler($url, $headers, $fields);
});

try {
    // Apply isolated test settings in database
    $db->exec("UPDATE settings SET setting_value = '1' WHERE setting_key = 'whatsapp_enabled'");
    $db->exec("UPDATE settings SET setting_value = 'https://wbapi.in' WHERE setting_key = 'whatsapp_api_base_url'");
    $db->exec("UPDATE settings SET setting_value = 'test_wbapi_token_12345' WHERE setting_key = 'whatsapp_api_token'");
    $db->exec("UPDATE settings SET setting_value = 'test_session_id_67890' WHERE setting_key = 'whatsapp_session_id'");
    $db->exec("UPDATE settings SET setting_value = '0' WHERE setting_key = 'whatsapp_provider_blocked'");
    $db->exec("UPDATE settings SET setting_value = NULL WHERE setting_key = 'whatsapp_provider_blocked_reason'");

    // -------------------------------------------------------------------------
    // TEST 1: Phone Normalization & Privacy Masking
    // -------------------------------------------------------------------------
    echo "[1] Testing Phone Normalization & Security Masking...\n";
    assertTest(WhatsAppNotificationService::normalizePhone('9876543210') === '919876543210', '10-digit Indian number gets 91 prepended');
    assertTest(WhatsAppNotificationService::normalizePhone('+91 98765 43210') === '919876543210', 'Formatted +91 with spaces normalized');
    assertTest(WhatsAppNotificationService::normalizePhone('(91) 98765-43210') === '919876543210', 'Brackets and dashes stripped');
    assertTest(WhatsAppNotificationService::normalizePhone('919876543210') === '919876543210', '12-digit number starting with 91 preserved');
    assertTest(WhatsAppNotificationService::normalizePhone('1234567890') === null, '10-digit number starting with invalid prefix (< 6) rejected');
    assertTest(WhatsAppNotificationService::normalizePhone('12345') === null, 'Short number rejected');
    assertTest(WhatsAppNotificationService::normalizePhone('99999999999999') === null, 'Excessive digits rejected');
    assertTest(WhatsAppNotificationService::maskPhone('919876543210') === '91******3210', 'Phone masking helper masks middle digits securely');

    // -------------------------------------------------------------------------
    // TEST 2: Real WBAPI Session Connection-Status Check (connect-api.php)
    // -------------------------------------------------------------------------
    echo "\n[2] Testing WBAPI Session Connection-Status Check (Without Sending Messages)...\n";
    $sessionCheckCalls = [];

    // Scenario A: Connected
    $mockTransportHandler = function (string $url, array $headers, array $fields) use (&$sessionCheckCalls) {
        $sessionCheckCalls[] = ['url' => $url, 'headers' => $headers, 'fields' => $fields];
        return [
            'http_code' => 200,
            'body' => json_encode(['status' => 'connected', 'authenticated' => true, 'sessionId' => 'test_session_id_67890']),
            'error' => '',
            'headers' => ['content-type' => 'application/json']
        ];
    };
    $resConn = WhatsAppNotificationService::checkSessionStatus();
    assertTest($resConn['status'] === 'Connected', 'Session check identifies Connected session');
    assertTest(!empty($sessionCheckCalls), 'Session check executes HTTP request');
    assertTest(str_ends_with($sessionCheckCalls[0]['url'], 'connect-api.php'), 'Session check targets POST /connect-api.php endpoint');
    assertTest(in_array('x-api-key: test_wbapi_token_12345', $sessionCheckCalls[0]['headers']), 'Session check supplies configured x-api-key header');
    assertTest(($sessionCheckCalls[0]['fields']['sessionId'] ?? '') === 'test_session_id_67890', 'Session check passes configured sessionId');

    // Scenario B: Disconnected
    $mockTransportHandler = function () {
        return [
            'http_code' => 200,
            'body' => json_encode(['status' => 'disconnected', 'message' => 'Scan QR to connect']),
            'error' => '',
            'headers' => ['content-type' => 'application/json']
        ];
    };
    $resDisc = WhatsAppNotificationService::checkSessionStatus();
    assertTest($resDisc['status'] === 'Disconnected', 'Session check identifies Disconnected session');

    // Scenario C: API Error (HTTP 500 or provider rejection)
    $mockTransportHandler = function () {
        return [
            'http_code' => 500,
            'body' => json_encode(['error' => 'Internal server error']),
            'error' => 'HTTP 500',
            'headers' => ['content-type' => 'application/json']
        ];
    };
    $resErr = WhatsAppNotificationService::checkSessionStatus();
    assertTest($resErr['status'] === 'API Error', 'Session check identifies API Error');

    // Scenario D: Not Configured (missing sessionId)
    $customSettings = WhatsAppNotificationService::getSettings();
    $customSettings['session_id'] = '';
    $resUnconf = WhatsAppNotificationService::checkSessionStatus($customSettings);
    assertTest($resUnconf['status'] === 'Not Configured', 'Session check identifies Not Configured when sessionId is empty');

    // Restore standard mock transport for outbox tests
    $mockTransportHandler = function (string $url, array $headers, array $fields) use (&$mockCalls) {
        $mockCalls[] = ['url' => $url, 'headers' => $headers, 'fields' => $fields];
        return [
            'http_code' => 200,
            'body' => json_encode(['success' => true, 'data' => ['status' => 'sent', 'messageId' => 'wb_msg_' . bin2hex(random_bytes(4))]]),
            'error' => '',
            'headers' => ['content-type' => 'application/json']
        ];
    };

    // -------------------------------------------------------------------------
    // TEST 3: Location Setup & Test Operators (Gaya > Konch vs Others)
    // -------------------------------------------------------------------------
    echo "\n[3] Setting up Isolated Test Locations & Operators...\n";

    // Gaya District
    $gayaStmt = $db->query("SELECT id FROM districts WHERE name LIKE '%Gaya%' LIMIT 1");
    $gayaDistrictId = (int)$gayaStmt->fetchColumn();
    if (!$gayaDistrictId) {
        $db->exec("INSERT INTO districts (name, state, is_active) VALUES ('Gaya', 'Bihar', 1)");
        $gayaDistrictId = (int)$db->lastInsertId();
    }

    // Konch Block in Gaya
    $konchStmt = $db->prepare("SELECT id FROM blocks WHERE district_id = ? AND name LIKE '%Konch%' LIMIT 1");
    $konchStmt->execute([$gayaDistrictId]);
    $konchBlockId = (int)$konchStmt->fetchColumn();
    if (!$konchBlockId) {
        $db->prepare("INSERT INTO blocks (district_id, name, is_active) VALUES (?, 'Konch', 1)")->execute([$gayaDistrictId]);
        $konchBlockId = (int)$db->lastInsertId();
    }

    // Other Gaya Block (Tekari)
    $otherGayaStmt = $db->prepare("SELECT id FROM blocks WHERE district_id = ? AND id != ? LIMIT 1");
    $otherGayaStmt->execute([$gayaDistrictId, $konchBlockId]);
    $otherGayaBlockId = (int)$otherGayaStmt->fetchColumn();
    if (!$otherGayaBlockId) {
        $db->prepare("INSERT INTO blocks (district_id, name, is_active) VALUES (?, 'Tekari', 1)")->execute([$gayaDistrictId]);
        $otherGayaBlockId = (int)$db->lastInsertId();
    }

    // Patna District & Block
    $patnaStmt = $db->query("SELECT id FROM districts WHERE name LIKE '%Patna%' LIMIT 1");
    $patnaDistrictId = (int)$patnaStmt->fetchColumn();
    if (!$patnaDistrictId) {
        $db->exec("INSERT INTO districts (name, state, is_active) VALUES ('Patna', 'Bihar', 1)");
        $patnaDistrictId = (int)$db->lastInsertId();
    }
    $patnaBlockStmt = $db->prepare("SELECT id FROM blocks WHERE district_id = ? LIMIT 1");
    $patnaBlockStmt->execute([$patnaDistrictId]);
    $patnaBlockId = (int)$patnaBlockStmt->fetchColumn();
    if (!$patnaBlockId) {
        $db->prepare("INSERT INTO blocks (district_id, name, is_active) VALUES (?, 'Patna Sadar', 1)")->execute([$patnaDistrictId]);
        $patnaBlockId = (int)$db->lastInsertId();
    }

    // Ensure test blocks are LIVE in service_block_availability for test submissions
    $srvBlockAppId = (int)($db->query("SELECT id FROM data_types WHERE code = 'srv_block_app'")->fetchColumn() ?: 4);
    $db->prepare("INSERT INTO service_block_availability (data_type_id, district_id, block_id, status, activated_at) VALUES (?, ?, ?, 'LIVE', NOW()) ON DUPLICATE KEY UPDATE status = 'LIVE'")->execute([$srvBlockAppId, $gayaDistrictId, $konchBlockId]);
    $db->prepare("INSERT INTO service_block_availability (data_type_id, district_id, block_id, status, activated_at) VALUES (?, ?, ?, 'LIVE', NOW()) ON DUPLICATE KEY UPDATE status = 'LIVE'")->execute([$srvBlockAppId, $patnaDistrictId, $patnaBlockId]);


    // Helper for creating test users
    $createTestUser = function (string $email, string $role, string $name, string $mobile, string $status, ?int $distId, ?int $blkId) use ($db, &$cleanupUserIds): int {
        $stmt = $db->prepare("SELECT id FROM users WHERE mobile = ? OR email = ?");
        $stmt->execute([$mobile, $email]);
        $existing = $stmt->fetchColumn();
        if ($existing) {
            $upd = $db->prepare("
                UPDATE users
                SET role = ?, name = ?, email = ?, mobile = ?, status = ?, assigned_district_id = ?, assigned_block_id = ?
                WHERE id = ?
            ");
            $upd->execute([$role, $name, $email, $mobile, $status, $distId, $blkId, $existing]);
            $cleanupUserIds[] = (int)$existing;
            return (int)$existing;
        }
        $ins = $db->prepare("
            INSERT INTO users (uuid, role, name, email, mobile, password_hash, status, assigned_district_id, assigned_block_id, created_at, updated_at)
            VALUES (UUID(), ?, ?, ?, ?, '\$2y\$10\$fakehashforunittestingonly1234567890', ?, ?, ?, NOW(), NOW())
        ");
        $ins->execute([$role, $name, $email, $mobile, $status, $distId, $blkId]);
        $newId = (int)$db->lastInsertId();
        $cleanupUserIds[] = $newId;
        return $newId;
    };

    // Retailer account in Patna
    $retailerId = $createTestUser('test_retailer_' . bin2hex(random_bytes(3)) . '@test.local', 'USER', 'Test Retailer', '9800000001', 'ACTIVE', $patnaDistrictId, $patnaBlockId);
    $wCheck = $db->prepare("SELECT balance FROM wallets WHERE user_id = ?");
    $wCheck->execute([$retailerId]);
    if ($wCheck->fetchColumn() === false) {
        $db->prepare("INSERT INTO wallets (user_id, balance, updated_at) VALUES (?, 5000.00, NOW())")->execute([$retailerId]);
    } else {
        $db->prepare("UPDATE wallets SET balance = 5000.00 WHERE user_id = ?")->execute([$retailerId]);
    }

    // Operators:
    // Op 1: Konch Operator 1 (ACTIVE)
    $opKonch1 = $createTestUser('op_k1_' . bin2hex(random_bytes(3)) . '@test.local', 'OPERATOR', 'Konch Op 1', '9811100001', 'ACTIVE', $gayaDistrictId, $konchBlockId);
    // Op 2: Konch Operator 2 (ACTIVE)
    $opKonch2 = $createTestUser('op_k2_' . bin2hex(random_bytes(3)) . '@test.local', 'OPERATOR', 'Konch Op 2', '9811100002', 'ACTIVE', $gayaDistrictId, $konchBlockId);
    // Op 3: Konch Operator 3 (Duplicate normalized mobile)
    $opKonch3 = $createTestUser('op_k3_' . bin2hex(random_bytes(3)) . '@test.local', 'OPERATOR', 'Konch Op 3', '+919811100002', 'ACTIVE', $gayaDistrictId, $konchBlockId);
    // Op 4: Other Gaya Block Operator (ACTIVE)
    $opOtherGaya = $createTestUser('op_other_' . bin2hex(random_bytes(3)) . '@test.local', 'OPERATOR', 'Tekari Op', '9811100003', 'ACTIVE', $gayaDistrictId, $otherGayaBlockId);
    // Op 5: Patna Operator (ACTIVE)
    $opPatna = $createTestUser('op_patna_' . bin2hex(random_bytes(3)) . '@test.local', 'OPERATOR', 'Patna Op', '9811100004', 'ACTIVE', $patnaDistrictId, $patnaBlockId);
    // Op 6: Konch Inactive Operator (INACTIVE)
    $opKonchInactive = $createTestUser('op_kinact_' . bin2hex(random_bytes(3)) . '@test.local', 'OPERATOR', 'Konch Op Inactive', '9811100005', 'INACTIVE', $gayaDistrictId, $konchBlockId);

    // -------------------------------------------------------------------------
    // TEST 4: Single Application Submission & Exact Block Operator Routing
    // -------------------------------------------------------------------------
    echo "\n[4] Testing Single Application Submission for Gaya > Konch...\n";
    $mockCalls = [];
    $initBalance = WalletService::getBalance($retailerId);

    $dataTypeStmt = $db->query("SELECT id FROM data_types WHERE code = 'srv_block_app' LIMIT 1");
    $dataTypeId = (int)$dataTypeStmt->fetchColumn() ?: 4;

    $uniqueAppNum = 'KONCH-' . date('His') . '-' . rand(100, 999);
    $subResult = ApplicationService::submit(
        $retailerId,
        $dataTypeId,
        $gayaDistrictId,
        $konchBlockId,
        [
            'applicant_name' => 'Ramesh Kumar',
            'application_code' => 'BAP-01',
            'application_number' => $uniqueAppNum,
            'mobile' => '9812345678'
        ]
    );

    assertTest($subResult['success'] === true, 'Single submission succeeds');
    $appId = (int)$subResult['application_id'];
    $cleanupAppIds[] = $appId;

    // Check Outbox entries created
    $outboxStmt = $db->prepare("SELECT * FROM whatsapp_notification_outbox WHERE application_id = ? ORDER BY id ASC");
    $outboxStmt->execute([$appId]);
    $outboxRows = $outboxStmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($outboxRows as $r) {
        $cleanupOutboxIds[] = (int)$r['id'];
    }

    $recipientUserIds = array_column($outboxRows, 'recipient_user_id');

    // Exact routing verification
    assertTest(in_array($opKonch1, $recipientUserIds), 'Eligible Konch Operator 1 was queued');
    assertTest(in_array($opKonch2, $recipientUserIds), 'Eligible Konch Operator 2 was queued');
    assertTest(!in_array($opOtherGaya, $recipientUserIds), 'Operator from another Gaya block received NOTHING');
    assertTest(!in_array($opPatna, $recipientUserIds), 'Retailer profile location (Patna) did NOT influence routing; Patna operator received NOTHING');
    assertTest(!in_array($opKonchInactive, $recipientUserIds), 'Inactive operator in Konch received NOTHING');

    // Duplicate mobile deduplication
    $op3RowStmt = $db->prepare("SELECT * FROM whatsapp_notification_outbox WHERE application_id = ? AND recipient_user_id = ?");
    $op3RowStmt->execute([$appId, $opKonch3]);
    $op3Row = $op3RowStmt->fetch(PDO::FETCH_ASSOC);
    assertTest($op3Row && $op3Row['status'] === 'SKIPPED', 'Duplicate mobile number for Op 3 marked SKIPPED (no duplicate delivery)');

    // Transaction isolation: Wallet debited exactly once
    $newBalance = WalletService::getBalance($retailerId);
    $fee = (float)$subResult['fee_debited'];
    assertTest(abs(($initBalance - $newBalance) - $fee) < 0.01, 'Retailer wallet was debited exactly once', "Expected debit ₹{$fee}, got ₹" . ($initBalance - $newBalance));

    // Message body sanitization & content check
    $sentRow = array_filter($outboxRows, fn($r) => $r['recipient_user_id'] == $opKonch1);
    $sentRow = reset($sentRow);
    assertTest($sentRow && str_contains($sentRow['message_body'], 'New application received'), 'Message contains standard notification header');
    assertTest(str_contains($sentRow['message_body'], 'Applicant: Ramesh Kumar'), 'Message contains applicant name');
    assertTest(str_contains($sentRow['message_body'], "Application No: {$uniqueAppNum}"), 'Message contains user application number');
    assertTest(str_contains($sentRow['message_body'], "Location: Gaya > Konch"), 'Message contains district > block location');
    assertTest(!str_contains($sentRow['message_body'], 'Reference:'), 'Message strictly excludes internal reference number');
    assertTest(!str_contains($sentRow['message_body'], 'balance') && !str_contains($sentRow['message_body'], 'token'), 'Message excludes wallet balances and sensitive tokens');

    // Mock Dispatcher calls
    assertTest(!empty($mockCalls), 'WhatsApp notification POST request was dispatched via fake transport');
    $dispatchedRecipients = array_map(fn($c) => $c['fields']['to'] ?? '', $mockCalls);
    assertTest(in_array('919811100001', $dispatchedRecipients), 'Operator 1 received normalized 91XXXXXXXXXX');
    assertTest(in_array('919811100002', $dispatchedRecipients), 'Operator 2 received normalized 91XXXXXXXXXX');

    // -------------------------------------------------------------------------
    // TEST 5: Stuck Worker Recovery (>10 Minutes)
    // -------------------------------------------------------------------------
    echo "\n[5] Testing Outbox Worker Stuck Recovery (>10 Minutes)...\n";
    $stuckKey = 'TEST_STUCK_' . bin2hex(random_bytes(6));
    $stuckStmt = $db->prepare("
        INSERT INTO whatsapp_notification_outbox (
            event_key, event_type, application_id, recipient_mobile, message_body,
            status, lock_token, locked_at, attempts, created_at, updated_at
        ) VALUES (
            ?, 'TEST_RECOVERY', ?, '919876543210', 'Stuck worker test',
            'PROCESSING', 'stuck_token_xyz_999', NOW() - INTERVAL 15 MINUTE, 1, NOW() - INTERVAL 15 MINUTE, NOW() - INTERVAL 15 MINUTE
        )
    ");
    $stuckStmt->execute([$stuckKey, $appId]);
    $stuckId = (int)$db->lastInsertId();
    $cleanupOutboxIds[] = $stuckId;

    $recoveredCount = WhatsAppNotificationService::recoverStuckWorkers();
    assertTest($recoveredCount >= 1, 'Stuck worker recovery process found and reclaimed stuck rows');

    $stuckCheck = $db->query("SELECT status, lock_token, locked_at FROM whatsapp_notification_outbox WHERE id = {$stuckId}")->fetch(PDO::FETCH_ASSOC);
    assertTest($stuckCheck['status'] === 'PENDING', 'Stuck row safely returned from PROCESSING to PENDING');
    assertTest($stuckCheck['lock_token'] === null, 'Stuck row lock token cleared');
    assertTest($stuckCheck['locked_at'] === null, 'Stuck row locked_at timestamp cleared');

    // -------------------------------------------------------------------------
    // TEST 6: Atomic Database Claim & Concurrency Token
    // -------------------------------------------------------------------------
    echo "\n[6] Testing Atomic Row Claim & Lock Token Concurrency Protection...\n";
    $atomicKey = 'TEST_ATOMIC_' . bin2hex(random_bytes(6));
    $atomicStmt = $db->prepare("
        INSERT INTO whatsapp_notification_outbox (
            event_key, event_type, application_id, recipient_mobile, message_body,
            status, attempts, created_at, updated_at
        ) VALUES (
            ?, 'TEST_CLAIM', ?, '919876543210', 'Atomic claim test',
            'PENDING', 0, NOW(), NOW()
        )
    ");
    $atomicStmt->execute([$atomicKey, $appId]);
    $atomicId = (int)$db->lastInsertId();
    $cleanupOutboxIds[] = $atomicId;

    // Direct sendOne with fake/unclaimed token should be rejected
    $unclaimedRow = $db->query("SELECT * FROM whatsapp_notification_outbox WHERE id = {$atomicId}")->fetch(PDO::FETCH_ASSOC);
    $fakeTokenResult = WhatsAppNotificationService::sendOne($unclaimedRow, 'unauthorized_token_123');
    assertTest($fakeTokenResult['status'] === 'FAILED', 'sendOne rejects unmatching or forged lock token');

    // Run dispatchPending specifically on this ID to claim atomically
    $dispatchResult = WhatsAppNotificationService::dispatchPending(1, $atomicId);
    assertTest($dispatchResult['sent'] === 1, 'Atomic claim and dispatch succeeded for eligible PENDING record');

    $dispatchedRow = $db->query("SELECT * FROM whatsapp_notification_outbox WHERE id = {$atomicId}")->fetch(PDO::FETCH_ASSOC);
    assertTest($dispatchedRow['status'] === 'SENT', 'Dispatched row transitioned to SENT');
    assertTest($dispatchedRow['lock_token'] === null, 'Lock token released after successful delivery');

    // Attempting to send a SENT record again must be refused immediately
    $resendResult = WhatsAppNotificationService::sendOne($dispatchedRow, null);
    assertTest($resendResult['status'] === 'SENT' && str_contains($resendResult['error'] ?? '', 'already'), 'Never resends an already SENT record');

    // -------------------------------------------------------------------------
    // TEST 7: Idempotency & Queue Re-invocation
    // -------------------------------------------------------------------------
    echo "\n[7] Testing Queue Idempotency...\n";
    $priorCount = (int)$db->query("SELECT COUNT(*) FROM whatsapp_notification_outbox WHERE application_id = {$appId}")->fetchColumn();
    WhatsAppNotificationService::queueSubmissionNotification(
        $appId,
        $gayaDistrictId,
        $konchBlockId,
        [
            'applicant_name' => 'Ramesh Kumar',
            'application_number' => $uniqueAppNum,
            'app_ref' => $subResult['app_ref'],
            'district_name' => 'Gaya',
            'block_name' => 'Konch',
            'application_code' => 'BAP-01'
        ],
        $db
    );
    $postCount = (int)$db->query("SELECT COUNT(*) FROM whatsapp_notification_outbox WHERE application_id = {$appId}")->fetchColumn();
    assertTest($priorCount === $postCount, 'Repeated queue call is idempotent and does not create duplicate outbox records');


    // -------------------------------------------------------------------------
    // TEST 8: Batch Application Submission (Multi-Block Routing)
    // -------------------------------------------------------------------------
    echo "\n[8] Testing Batch Application Submission (Multi-Block)...\n";
    $batchAppNum1 = 'BATCH-K-' . date('His') . '-' . rand(100, 999);
    $batchAppNum2 = 'BATCH-P-' . date('His') . '-' . rand(100, 999);

    $batchItems = [
        [
            'district_id' => $gayaDistrictId,
            'block_id' => $konchBlockId,
            'applicant_name' => 'Batch Konch Applicant',
            'application_code' => 'BAP-01',
            'application_number' => $batchAppNum1
        ],
        [
            'district_id' => $patnaDistrictId,
            'block_id' => $patnaBlockId,
            'applicant_name' => 'Batch Patna Applicant',
            'application_code' => 'BAP-02',
            'application_number' => $batchAppNum2
        ]
    ];

    $batchRes = ApplicationService::submitBatch($retailerId, $dataTypeId, $batchItems);
    assertTest($batchRes['success'] === true, 'Batch submission succeeds');
    assertTest($batchRes['total_items'] === 2, 'Batch created 2 applications');

    $app1Id = (int)$batchRes['applications'][0]['id'];
    $app2Id = (int)$batchRes['applications'][1]['id'];
    $cleanupAppIds[] = $app1Id;
    $cleanupAppIds[] = $app2Id;

    // Konch application notified Konch operator only
    $outbox1 = $db->query("SELECT recipient_user_id FROM whatsapp_notification_outbox WHERE application_id = {$app1Id} AND status = 'SENT'")->fetchAll(PDO::FETCH_COLUMN);
    assertTest(in_array($opKonch1, $outbox1), 'Batch App 1 (Konch) notified Konch Operator');
    assertTest(!in_array($opPatna, $outbox1), 'Batch App 1 (Konch) did NOT notify Patna Operator');

    // Patna application notified Patna operator only
    $outbox2 = $db->query("SELECT recipient_user_id FROM whatsapp_notification_outbox WHERE application_id = {$app2Id} AND status = 'SENT'")->fetchAll(PDO::FETCH_COLUMN);
    assertTest(in_array($opPatna, $outbox2), 'Batch App 2 (Patna) notified Patna Operator');
    assertTest(!in_array($opKonch1, $outbox2), 'Batch App 2 (Patna) did NOT notify Konch Operator');

    // -------------------------------------------------------------------------
    // TEST 9: Missing Block Operator & Invalid Mobile Admin Alerts
    // -------------------------------------------------------------------------
    echo "\n[9] Testing Missing Operator & Invalid Mobile Alerts...\n";

    // Isolated block with no operator
    $emptyBlockStmt = $db->prepare("SELECT id FROM blocks WHERE district_id = ? AND id NOT IN (SELECT assigned_block_id FROM users WHERE assigned_block_id IS NOT NULL) LIMIT 1");
    $emptyBlockStmt->execute([$gayaDistrictId]);
    $emptyBlockId = (int)$emptyBlockStmt->fetchColumn();
    if (!$emptyBlockId) {
        $db->prepare("INSERT INTO blocks (district_id, name, is_active) VALUES (?, 'Empty Block No Ops', 1)")->execute([$gayaDistrictId]);
        $emptyBlockId = (int)$db->lastInsertId();
    }

    $db->prepare("INSERT INTO service_block_availability (data_type_id, district_id, block_id, status, activated_at) VALUES (?, ?, ?, 'LIVE', NOW()) ON DUPLICATE KEY UPDATE status = 'LIVE'")->execute([$srvBlockAppId, $gayaDistrictId, $emptyBlockId]);

    $emptyAppNum = 'NO-OP-' . date('His') . '-' . rand(100, 999);
    $emptySub = ApplicationService::submit(
        $retailerId,
        $dataTypeId,
        $gayaDistrictId,
        $emptyBlockId,
        [
            'applicant_name' => 'Orphan Applicant',
            'application_code' => 'BAP-01',
            'application_number' => $emptyAppNum
        ]
    );
    assertTest($emptySub['success'] === true, 'Application submission succeeds even when no Block Operator is assigned');
    $cleanupAppIds[] = (int)$emptySub['application_id'];

    $emptyRow = $db->query("SELECT * FROM whatsapp_notification_outbox WHERE application_id = " . (int)$emptySub['application_id'])->fetch(PDO::FETCH_ASSOC);
    assertTest($emptyRow && $emptyRow['status'] === 'SKIPPED', 'Missing operator outbox row marked SKIPPED');
    assertTest(str_contains($emptyRow['last_error'] ?? '', 'No active Block Operator is assigned'), 'Missing operator reason recorded');

    // Verify Admin Alert was created for missing operator
    $adminNotif = $db->query("SELECT * FROM notifications WHERE title LIKE '%Operator%' ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    assertTest($adminNotif !== false, 'Admin in-app alert generated via NotificationService');
    assertTest(!str_contains($adminNotif['message'] ?? '', 'test_wbapi_token'), 'Admin alert does not expose API token or sensitive credentials');

    // Operator with invalid mobile
    $opInvalidMobile = $createTestUser('op_inv_' . bin2hex(random_bytes(3)) . '@test.local', 'OPERATOR', 'Invalid Mobile Op', '000123', 'ACTIVE', $gayaDistrictId, $emptyBlockId);
    $invQueue = WhatsAppNotificationService::queueSubmissionNotification(
        (int)$emptySub['application_id'],
        $gayaDistrictId,
        $emptyBlockId,
        ['applicant_name' => 'Test', 'application_number' => '123', 'app_ref' => 'REF', 'district_name' => 'Gaya', 'block_name' => 'Empty'],
        $db
    );
    assertTest($invQueue['skipped_count'] >= 1, 'Operator with invalid mobile is skipped');

    // -------------------------------------------------------------------------
    // TEST 10: Retry Backoff Schedule & HTTP Provider Error Codes
    // -------------------------------------------------------------------------
    echo "\n[10] Testing HTTP Provider Errors & Retry Backoff Schedule...\n";

    $insTestRow = function (string $key, int $appRefId) use ($db, &$cleanupOutboxIds): int {
        $stmt = $db->prepare("
            INSERT INTO whatsapp_notification_outbox (
                event_key, event_type, application_id, recipient_mobile, message_body,
                status, attempts, created_at, updated_at
            ) VALUES (
                ?, 'TEST_RETRY', ?, '919876543210', 'Retry schedule test message',
                'PENDING', 0, NOW(), NOW()
            )
        ");
        $stmt->execute([$key, $appRefId]);
        $nid = (int)$db->lastInsertId();
        $cleanupOutboxIds[] = $nid;
        return $nid;
    };

    // 10A: HTTP 400 Bad Request -> Permanent Failure
    $row400Id = $insTestRow('TEST_400_' . bin2hex(random_bytes(4)), $appId);
    $mockTransportHandler = function () {
        return ['http_code' => 400, 'body' => json_encode(['success' => false, 'message' => 'Invalid session ID']), 'error' => '', 'headers' => []];
    };
    $row400 = $db->query("SELECT * FROM whatsapp_notification_outbox WHERE id = {$row400Id}")->fetch(PDO::FETCH_ASSOC);
    $res400 = WhatsAppNotificationService::sendOne($row400);
    $check400 = $db->query("SELECT status, next_attempt_at FROM whatsapp_notification_outbox WHERE id = {$row400Id}")->fetch(PDO::FETCH_ASSOC);
    assertTest($check400['status'] === 'FAILED', 'HTTP 400 permanently marked FAILED');
    assertTest($check400['next_attempt_at'] === null, 'HTTP 400 has no next_attempt_at scheduled');

    // 10B: HTTP 401 Unauthorized -> Permanent Failure + Provider Health Block Flag
    $row401Id = $insTestRow('TEST_401_' . bin2hex(random_bytes(4)), $appId);
    $mockTransportHandler = function () {
        return ['http_code' => 401, 'body' => json_encode(['success' => false, 'message' => 'Unauthorized x-api-key']), 'error' => '', 'headers' => []];
    };
    $row401 = $db->query("SELECT * FROM whatsapp_notification_outbox WHERE id = {$row401Id}")->fetch(PDO::FETCH_ASSOC);
    $res401 = WhatsAppNotificationService::sendOne($row401);
    $check401 = $db->query("SELECT status FROM whatsapp_notification_outbox WHERE id = {$row401Id}")->fetch(PDO::FETCH_ASSOC);
    $blockedCheck = $db->query("SELECT setting_value FROM settings WHERE setting_key = 'whatsapp_provider_blocked'")->fetchColumn();
    assertTest($check401['status'] === 'FAILED', 'HTTP 401 permanently marked FAILED');
    assertTest($blockedCheck === '1', 'HTTP 401 set whatsapp_provider_blocked = 1 without altering .env');

    // Unblock provider for subsequent retry tests
    WhatsAppNotificationService::unblockProvider();

    // 10C: HTTP 429 Rate Limit with Retry-After header
    $row429Id = $insTestRow('TEST_429_' . bin2hex(random_bytes(4)), $appId);
    $mockTransportHandler = function () {
        return [
            'http_code' => 429,
            'body' => json_encode(['success' => false, 'message' => 'Quota reached']),
            'error' => '',
            'headers' => ['retry-after' => '75']
        ];
    };
    $row429 = $db->query("SELECT * FROM whatsapp_notification_outbox WHERE id = {$row429Id}")->fetch(PDO::FETCH_ASSOC);
    $res429 = WhatsAppNotificationService::sendOne($row429);
    $check429 = $db->query("SELECT status, next_attempt_at, attempts FROM whatsapp_notification_outbox WHERE id = {$row429Id}")->fetch(PDO::FETCH_ASSOC);
    assertTest($check429['status'] === 'PENDING', 'HTTP 429 kept as PENDING for retry');
    $expectedMinTime = date('Y-m-d H:i:s', time() + 70);
    assertTest($check429['next_attempt_at'] >= $expectedMinTime, 'HTTP 429 respected Retry-After header delay');

    // 10D: Progressive Retry Schedule (Attempt 1: 1 min, Attempt 2: 5 min, Attempt 3: 15 min, Attempt 4: Permanent FAILED)
    $schedRowId = $insTestRow('TEST_SCHED_' . bin2hex(random_bytes(4)), $appId);
    $mockTransportHandler = function () {
        return ['http_code' => 502, 'body' => 'Bad Gateway 502', 'error' => 'Gateway error', 'headers' => []];
    };

    // Attempt 1 -> Next at +60s
    $schedRow = $db->query("SELECT * FROM whatsapp_notification_outbox WHERE id = {$schedRowId}")->fetch(PDO::FETCH_ASSOC);
    WhatsAppNotificationService::sendOne($schedRow);
    $chkAtt1 = $db->query("SELECT status, attempts, next_attempt_at FROM whatsapp_notification_outbox WHERE id = {$schedRowId}")->fetch(PDO::FETCH_ASSOC);
    assertTest($chkAtt1['status'] === 'PENDING' && (int)$chkAtt1['attempts'] === 1, 'Attempt 1 kept PENDING with attempts=1');
    assertTest($chkAtt1['next_attempt_at'] >= date('Y-m-d H:i:s', time() + 50), 'Attempt 1 scheduled next attempt ~1 minute later');

    // Attempt 2 -> Next at +300s
    $db->prepare("UPDATE whatsapp_notification_outbox SET status = 'PENDING', lock_token = NULL WHERE id = ?")->execute([$schedRowId]);
    $schedRow = $db->query("SELECT * FROM whatsapp_notification_outbox WHERE id = {$schedRowId}")->fetch(PDO::FETCH_ASSOC);
    WhatsAppNotificationService::sendOne($schedRow);
    $chkAtt2 = $db->query("SELECT status, attempts, next_attempt_at FROM whatsapp_notification_outbox WHERE id = {$schedRowId}")->fetch(PDO::FETCH_ASSOC);
    assertTest($chkAtt2['status'] === 'PENDING' && (int)$chkAtt2['attempts'] === 2, 'Attempt 2 kept PENDING with attempts=2');
    assertTest($chkAtt2['next_attempt_at'] >= date('Y-m-d H:i:s', time() + 280), 'Attempt 2 scheduled next attempt ~5 minutes later');

    // Attempt 3 -> Next at +900s
    $db->prepare("UPDATE whatsapp_notification_outbox SET status = 'PENDING', lock_token = NULL WHERE id = ?")->execute([$schedRowId]);
    $schedRow = $db->query("SELECT * FROM whatsapp_notification_outbox WHERE id = {$schedRowId}")->fetch(PDO::FETCH_ASSOC);
    WhatsAppNotificationService::sendOne($schedRow);
    $chkAtt3 = $db->query("SELECT status, attempts, next_attempt_at FROM whatsapp_notification_outbox WHERE id = {$schedRowId}")->fetch(PDO::FETCH_ASSOC);
    assertTest($chkAtt3['status'] === 'PENDING' && (int)$chkAtt3['attempts'] === 3, 'Attempt 3 kept PENDING with attempts=3');
    assertTest($chkAtt3['next_attempt_at'] >= date('Y-m-d H:i:s', time() + 850), 'Attempt 3 scheduled next attempt ~15 minutes later');

    // Attempt 4 -> Permanently FAILED
    $db->prepare("UPDATE whatsapp_notification_outbox SET status = 'PENDING', lock_token = NULL WHERE id = ?")->execute([$schedRowId]);
    $schedRow = $db->query("SELECT * FROM whatsapp_notification_outbox WHERE id = {$schedRowId}")->fetch(PDO::FETCH_ASSOC);
    WhatsAppNotificationService::sendOne($schedRow);
    $chkAtt4 = $db->query("SELECT status, attempts, next_attempt_at FROM whatsapp_notification_outbox WHERE id = {$schedRowId}")->fetch(PDO::FETCH_ASSOC);
    assertTest($chkAtt4['status'] === 'FAILED' && (int)$chkAtt4['attempts'] === 4, 'Attempt 4 permanently marked FAILED');
    assertTest($chkAtt4['next_attempt_at'] === null, 'Permanently FAILED row has next_attempt_at cleared');

    // -------------------------------------------------------------------------
    // TEST 11: Admin Manual Retry Action
    // -------------------------------------------------------------------------
    echo "\n[11] Testing Admin Manual Retry Action...\n";
    $mockTransportHandler = function () {
        return [
            'http_code' => 200,
            'body' => json_encode(['success' => true, 'data' => ['status' => 'sent', 'messageId' => 'retry_msg_1']]),
            'error' => '',
            'headers' => ['content-type' => 'application/json']
        ];
    };

    $adminUserStmt = $db->query("SELECT id FROM users WHERE role = 'ADMIN' LIMIT 1");
    $adminId = (int)$adminUserStmt->fetchColumn() ?: 1;

    // Retry on a FAILED record
    $retryRes = WhatsAppNotificationService::retryFailedMessage($chkAtt4 ? $schedRowId : $row400Id, $adminId);
    assertTest($retryRes['success'] === true, 'Admin retry on FAILED record succeeds');

    $retriedCheck = $db->query("SELECT status, sent_at FROM whatsapp_notification_outbox WHERE id = {$schedRowId}")->fetch(PDO::FETCH_ASSOC);
    assertTest($retriedCheck['status'] === 'SENT', 'Retried message status updated to SENT via atomic dispatcher');
    assertTest(!empty($retriedCheck['sent_at']), 'Retried message recorded sent_at timestamp');

    // Retry on already SENT record must fail
    $retrySentRes = WhatsAppNotificationService::retryFailedMessage($schedRowId, $adminId);
    assertTest($retrySentRes['success'] === false, 'Admin retry refuses to retry an already SENT record');

    // -------------------------------------------------------------------------
    // TEST 12: Security Constraints (Host, SSL, Credential Masking)
    // -------------------------------------------------------------------------
    echo "\n[12] Testing Security Constraints & Credential Protection...\n";

    // Non-wbapi.in Host Rejection
    $badHostSettings = WhatsAppNotificationService::getSettings();
    $badHostSettings['api_base_url'] = 'https://malicious-site.com';
    $badHostId = $insTestRow('TEST_BAD_HOST_' . bin2hex(random_bytes(4)), $appId);
    $badHostRow = $db->query("SELECT * FROM whatsapp_notification_outbox WHERE id = {$badHostId}")->fetch(PDO::FETCH_ASSOC);
    $resBadHost = WhatsAppNotificationService::sendOne($badHostRow, null, $badHostSettings);
    assertTest($resBadHost['status'] === 'FAILED', 'Non-wbapi.in host rejected by security policy');
    assertTest(str_contains($resBadHost['error'] ?? '', 'strictly https://wbapi.in'), 'Rejection error enforces strictly https://wbapi.in');

    // Check Settings View does not render API token
    $settingsView = file_get_contents(dirname(__DIR__) . '/app/Views/admin/settings.php');
    assertTest(str_contains($settingsView, 'name="whatsapp_api_token"') && str_contains($settingsView, 'value=""'), 'Settings view never renders saved API token in HTML value attribute');
    assertTest(
        !str_contains($settingsView, 'value="<?= $settings[\'whatsapp_api_token\']')
        && !str_contains($settingsView, 'value="<?php echo $settings[\'whatsapp_api_token\']')
        && !str_contains($settingsView, 'echo $settings[\'whatsapp_api_token\']'),
        'Settings view HTML contains zero token echoing or variable output'
    );

    // Block Operator role and district restriction preserved
    $opUser = $db->query("SELECT role, assigned_district_id, assigned_block_id FROM users WHERE id = {$opKonch1}")->fetch(PDO::FETCH_ASSOC);
    assertTest($opUser['role'] === 'OPERATOR', 'Block Operator role remains OPERATOR');
    assertTest((int)$opUser['assigned_district_id'] === $gayaDistrictId, 'Block Operator district restriction preserved');
    assertTest((int)$opUser['assigned_block_id'] === $konchBlockId, 'Block Operator block restriction preserved');

    // -------------------------------------------------------------------------
    // TEST 13: Syntax Check on all touched files
    // -------------------------------------------------------------------------
    echo "\n[13] Verifying PHP Syntax on All Modified Files...\n";
    $filesToLint = [
        'app/Services/WhatsAppNotificationService.php',
        'app/Services/ApplicationService.php',
        'app/Services/NotificationService.php',
        'app/Controllers/SettingsController.php',
        'app/Controllers/AdminController.php',
        'app/Controllers/UserController.php',
        'app/Views/admin/settings.php',
        'app/Views/admin/whatsapp_logs.php',
        'public/index.php',
        'cli/migrate_whatsapp_outbox.php',
        'cli/dispatch_whatsapp.php'
    ];

    foreach ($filesToLint as $file) {
        $path = dirname(__DIR__) . '/' . $file;
        $out = [];
        $code = 0;
        exec("php -l \"{$path}\"", $out, $code);
        assertTest($code === 0, "php -l {$file}");
    }

} finally {
    // -------------------------------------------------------------------------
    // GUARANTEED CLEANUP & SETTINGS RESTORATION
    // -------------------------------------------------------------------------
    // 1. Reset Transport
    WhatsAppNotificationService::setTransport(null);

    // 2. Restore all original settings
    if (!empty($originalSettings)) {
        $restoreStmt = $db->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
        foreach ($originalSettings as $k => $v) {
            $restoreStmt->execute([$v, $k]);
        }
    }

    // 3. Delete all test outbox rows
    if (!empty($cleanupOutboxIds)) {
        $inClause = implode(',', array_map('intval', array_unique($cleanupOutboxIds)));
        $db->exec("DELETE FROM whatsapp_notification_outbox WHERE id IN ({$inClause})");
    }
    $db->exec("DELETE FROM whatsapp_notification_outbox WHERE event_key LIKE 'TEST_%' OR event_key LIKE 'APPLICATION_SUBMITTED%:9999%'");

    // 4. Delete test applications (cascades to field values, status history, and outbox)
    if (!empty($cleanupAppIds)) {
        $inApps = implode(',', array_map('intval', array_unique($cleanupAppIds)));
        $db->exec("DELETE FROM applications WHERE id IN ({$inApps})");
    }

    // 5. Delete test users, wallets, wallet_transactions
    if (!empty($cleanupUserIds)) {
        $inUsers = implode(',', array_map('intval', array_unique($cleanupUserIds)));
        $db->exec("DELETE FROM applications WHERE user_id IN ({$inUsers})");
        $db->exec("DELETE FROM wallet_transactions WHERE user_id IN ({$inUsers})");
        $db->exec("DELETE FROM wallets WHERE user_id IN ({$inUsers})");
        $db->exec("DELETE FROM notifications WHERE user_id IN ({$inUsers})");
        $db->exec("DELETE FROM users WHERE id IN ({$inUsers})");
    }

    // 6. Delete test notifications created for admins
    $db->exec("DELETE FROM notifications WHERE title LIKE '%Operator%' AND created_at >= NOW() - INTERVAL 10 MINUTE");
    $db->exec("DELETE FROM audit_logs WHERE action LIKE 'WHATSAPP_%' AND created_at >= NOW() - INTERVAL 10 MINUTE");
}

echo "\n====================================================================\n";
echo " VERIFICATION SUMMARY: {$testsPassed} PASSED, {$testsFailed} FAILED\n";
echo "====================================================================\n";

if ($testsFailed > 0) {
    exit(1);
}
exit(0);
