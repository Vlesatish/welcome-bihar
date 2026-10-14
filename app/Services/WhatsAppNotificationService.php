<?php
/**
 * WhatsApp Notification Service for Welcome Bihar Portal
 * Uses WBAPI (https://wa.panmitra.in/api.php) server-side endpoints:
 * - Send API:    POST https://wbapi.in/send-api.php
 * - Connect API: POST https://wbapi.in/connect-api.php
 */

declare(strict_types=1);

namespace App\Services;

use App\Config\Database;
use PDO;
use Throwable;

class WhatsAppNotificationService
{
    public const ALLOWED_API_HOST = 'wbapi.in';
    public const DEFAULT_BASE_URL = 'https://wbapi.in';
    public const SEND_ENDPOINT = '/send-api.php';
    public const CONNECT_ENDPOINT = '/connect-api.php';
    public const MAX_ATTEMPTS = 4;

    /**
     * Optional custom/mock transport for automated testing.
     * Callable signature: function(string $url, array $headers, array $postFields): array
     * Must return: ['http_code' => int, 'body' => string, 'error' => string, 'headers' => array]
     */
    private static $transport = null;

    /**
     * Set or reset the HTTP transport (used for mock testing without real network calls)
     */
    public static function setTransport(?callable $transport): void
    {
        self::$transport = $transport;
    }

    /**
     * Fetch configured WhatsApp settings with environment variable priority
     */
    public static function getSettings(): array
    {
        $db = Database::getConnection();
        $keys = [
            'whatsapp_enabled',
            'whatsapp_api_base_url',
            'whatsapp_api_token',
            'whatsapp_session_id',
            'whatsapp_default_country_code',
            'whatsapp_provider_blocked',
            'whatsapp_provider_blocked_reason',
            'whatsapp_last_status',
            'whatsapp_last_status_checked_at'
        ];

        $inClause = implode(',', array_fill(0, count($keys), '?'));
        $stmt = $db->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ($inClause)");
        $stmt->execute($keys);
        $dbSettings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];

        // Environment variable detection
        $hasEnvEnabled = (getenv('WHATSAPP_ENABLED') !== false || isset($_ENV['WHATSAPP_ENABLED']));
        $envEnabled = getenv('WHATSAPP_ENABLED') !== false ? getenv('WHATSAPP_ENABLED') : ($_ENV['WHATSAPP_ENABLED'] ?? null);
        $envBaseUrl = getenv('WHATSAPP_API_BASE_URL') !== false ? getenv('WHATSAPP_API_BASE_URL') : ($_ENV['WHATSAPP_API_BASE_URL'] ?? null);
        $envToken = getenv('WHATSAPP_API_TOKEN') !== false ? getenv('WHATSAPP_API_TOKEN') : ($_ENV['WHATSAPP_API_TOKEN'] ?? null);
        $envSession = getenv('WHATSAPP_SESSION_ID') !== false ? getenv('WHATSAPP_SESSION_ID') : ($_ENV['WHATSAPP_SESSION_ID'] ?? null);
        $envCode = getenv('WHATSAPP_DEFAULT_COUNTRY_CODE') !== false ? getenv('WHATSAPP_DEFAULT_COUNTRY_CODE') : ($_ENV['WHATSAPP_DEFAULT_COUNTRY_CODE'] ?? null);

        $enabledVal = $envEnabled !== null ? (string)$envEnabled : (string)($dbSettings['whatsapp_enabled'] ?? '1');
        $baseUrl = $envBaseUrl ?: ($dbSettings['whatsapp_api_base_url'] ?? self::DEFAULT_BASE_URL);
        $token = $envToken ?: ($dbSettings['whatsapp_api_token'] ?? '');
        $sessionId = $envSession ?: ($dbSettings['whatsapp_session_id'] ?? '');
        $countryCode = $envCode ?: ($dbSettings['whatsapp_default_country_code'] ?? '91');

        $isProviderBlocked = ((int)($dbSettings['whatsapp_provider_blocked'] ?? 0) === 1);
        $providerBlockedReason = (string)($dbSettings['whatsapp_provider_blocked_reason'] ?? '');

        return [
            'enabled' => $enabledVal === '1' || strtolower($enabledVal) === 'true',
            'is_env_controlled' => $hasEnvEnabled,
            'api_base_url' => trim($baseUrl),
            'api_token' => trim($token),
            'session_id' => trim($sessionId),
            'default_country_code' => preg_replace('/\D/', '', (string)$countryCode) ?: '91',
            'provider_blocked' => $isProviderBlocked,
            'provider_blocked_reason' => $providerBlockedReason,
            'last_status' => (string)($dbSettings['whatsapp_last_status'] ?? 'Not Configured'),
            'last_status_checked_at' => (string)($dbSettings['whatsapp_last_status_checked_at'] ?? '')
        ];
    }

    /**
     * Check if sending is allowed (requires enabled AND provider health not blocked)
     */
    public static function isSendingAllowed(?array $settings = null): array
    {
        if ($settings === null) {
            $settings = self::getSettings();
        }

        if (!$settings['enabled']) {
            return [
                'allowed' => false,
                'status' => 'disabled',
                'reason' => 'WhatsApp notifications are currently disabled.'
            ];
        }

        if ($settings['provider_blocked']) {
            return [
                'allowed' => false,
                'status' => 'blocked',
                'reason' => 'Provider sending blocked: ' . ($settings['provider_blocked_reason'] ?: 'Authentication failure (HTTP 401).')
            ];
        }

        return ['allowed' => true, 'status' => 'active', 'reason' => 'OK'];
    }

    /**
     * Block sending due to provider authentication error (HTTP 401)
     * Sets runtime health flag in database without modifying .env
     */
    public static function blockProvider(string $reason): void
    {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("
                INSERT INTO settings (setting_key, setting_value, setting_group, description, updated_at)
                VALUES ('whatsapp_provider_blocked', '1', 'NOTIFICATION', 'Runtime health block flag', NOW())
                ON DUPLICATE KEY UPDATE setting_value = '1', updated_at = NOW()
            ");
            $stmt->execute();

            $stmt2 = $db->prepare("
                INSERT INTO settings (setting_key, setting_value, setting_group, description, updated_at)
                VALUES ('whatsapp_provider_blocked_reason', ?, 'NOTIFICATION', 'Runtime health block reason', NOW())
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()
            ");
            $stmt2->execute([$reason]);

            AuditService::log('WHATSAPP_SETTINGS_UPDATED', 'SETTINGS', 'whatsapp_provider_blocked', [
                'action' => 'provider_blocked',
                'reason' => $reason
            ]);
            error_log("WhatsApp provider sending blocked: " . $reason);
        } catch (Throwable $e) {
            error_log("Failed to set provider block: " . $e->getMessage());
        }
    }

    /**
     * Clear provider health block (allowed when admin updates credentials and verifies session)
     */
    public static function unblockProvider(): void
    {
        try {
            $db = Database::getConnection();
            $db->exec("
                UPDATE settings
                SET setting_value = '0', updated_at = NOW()
                WHERE setting_key = 'whatsapp_provider_blocked'
            ");
            $db->exec("
                UPDATE settings
                SET setting_value = '', updated_at = NOW()
                WHERE setting_key = 'whatsapp_provider_blocked_reason'
            ");

            AuditService::log('WHATSAPP_SETTINGS_UPDATED', 'SETTINGS', 'whatsapp_provider_blocked', [
                'action' => 'provider_unblocked'
            ]);
        } catch (Throwable $e) {
            error_log("Failed to clear provider block: " . $e->getMessage());
        }
    }

    /**
     * Send in-app notification to all active ADMIN users
     * Never exposes API tokens or unmasked mobile numbers
     */
    public static function alertAdmins(string $title, string $message, string $type = 'WARNING', ?string $link = '/admin/whatsapp/logs'): void
    {
        try {
            $db = Database::getConnection();
            $stmt = $db->query("SELECT id FROM users WHERE role = 'ADMIN' AND status = 'ACTIVE'");
            $adminIds = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

            foreach ($adminIds as $adminId) {
                NotificationService::create((int)$adminId, $title, $message, $type, $link);
            }
        } catch (Throwable $e) {
            error_log("Failed to alert admins: " . $e->getMessage());
        }
    }

    /**
     * Check WBAPI session connection status using connect-api.php endpoint
     * Returns: ['status' => 'Connected' | 'Disconnected' | 'Not Configured' | 'API Error', 'message' => string]
     * Does NOT send any test WhatsApp message!
     */
    public static function checkSessionStatus(?array $settings = null): array
    {
        if ($settings === null) {
            $settings = self::getSettings();
        }

        $apiToken = $settings['api_token'];
        $sessionId = $settings['session_id'];

        if (empty($apiToken) || empty($sessionId)) {
            self::saveLastStatus('Not Configured');
            return [
                'status' => 'Not Configured',
                'message' => 'API Token or Session ID is not configured.'
            ];
        }

        $baseUrl = rtrim($settings['api_base_url'] ?: self::DEFAULT_BASE_URL, '/');
        $parsedUrl = parse_url($baseUrl);
        $host = strtolower($parsedUrl['host'] ?? '');
        $scheme = strtolower($parsedUrl['scheme'] ?? '');

        if ($scheme !== 'https' || $host !== self::ALLOWED_API_HOST) {
            self::saveLastStatus('API Error');
            return [
                'status' => 'API Error',
                'message' => "Security rejection: API Base URL host must strictly be https://" . self::ALLOWED_API_HOST
            ];
        }

        $connectUrl = $baseUrl . self::CONNECT_ENDPOINT;
        $headers = [
            'x-api-key: ' . $apiToken
        ];
        $postFields = [
            'sessionId' => $sessionId,
            'action' => 'status'
        ];

        $response = self::executeHttpRequest($connectUrl, $headers, $postFields);
        $httpCode = $response['http_code'];
        $rawBody = $response['body'];
        $curlError = $response['error'];

        $json = null;
        if (!empty($rawBody)) {
            $json = @json_decode($rawBody, true);
        }

        // Handle 401 Unauthorized
        if ($httpCode === 401) {
            self::saveLastStatus('API Error');
            self::blockProvider('Invalid API token or unauthorized session (HTTP 401)');
            self::alertAdmins(
                'WhatsApp API Authentication Failed (HTTP 401)',
                'WBAPI rejected the API credentials. Provider sending is blocked until updated.',
                'DANGER',
                '/admin/settings#whatsapp-settings'
            );
            return [
                'status' => 'API Error',
                'message' => 'Authentication failed (HTTP 401): Invalid API token or unauthorized session.'
            ];
        }

        // Handle HTTP errors
        if ($httpCode >= 400 || $httpCode === 0) {
            self::saveLastStatus('API Error');
            $errMsg = $curlError ?: "Provider returned HTTP {$httpCode}";
            return [
                'status' => 'API Error',
                'message' => "Connection check failed ({$errMsg})"
            ];
        }

        // Parse JSON response
        $statusStr = '';
        if (is_array($json)) {
            $statusStr = strtolower((string)($json['data']['status'] ?? ($json['status'] ?? ($json['data']['connection'] ?? ''))));
            if (empty($statusStr) && isset($json['success']) && ($json['success'] === true || $json['success'] === 'true')) {
                $statusStr = 'connected';
            }
        }

        $connectedTokens = ['connected', 'authenticated', 'working', 'open', 'ready', 'active', 'online'];
        $disconnectedTokens = ['disconnected', 'closed', 'qr', 'not_connected', 'connecting', 'offline', 'scan_qr'];

        if (in_array($statusStr, $connectedTokens, true)) {
            self::saveLastStatus('Connected');
            // If was blocked, clear the block now that session is verified
            if ($settings['provider_blocked']) {
                self::unblockProvider();
            }
            return [
                'status' => 'Connected',
                'message' => 'WhatsApp session is connected and active.'
            ];
        }

        if (in_array($statusStr, $disconnectedTokens, true) || $statusStr === '') {
            self::saveLastStatus('Disconnected');
            self::alertAdmins(
                'WhatsApp Session Disconnected',
                'Connected WhatsApp session is currently offline or disconnected on WBAPI. Please reconnect your session.',
                'WARNING',
                '/admin/settings#whatsapp-settings'
            );
            return [
                'status' => 'Disconnected',
                'message' => 'WhatsApp session is disconnected or scanning QR code is required on WBAPI.'
            ];
        }

        self::saveLastStatus('Disconnected');
        return [
            'status' => 'Disconnected',
            'message' => 'WhatsApp session status: ' . htmlspecialchars($statusStr, ENT_QUOTES, 'UTF-8')
        ];
    }

    /**
     * Persist last verified session status in settings table
     */
    private static function saveLastStatus(string $status): void
    {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("
                INSERT INTO settings (setting_key, setting_value, setting_group, description, updated_at)
                VALUES ('whatsapp_last_status', ?, 'NOTIFICATION', 'Last checked WhatsApp connection status', NOW())
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()
            ");
            $stmt->execute([$status]);

            $stmt2 = $db->prepare("
                INSERT INTO settings (setting_key, setting_value, setting_group, description, updated_at)
                VALUES ('whatsapp_last_status_checked_at', NOW(), 'NOTIFICATION', 'Timestamp of last WhatsApp status check', NOW())
                ON DUPLICATE KEY UPDATE setting_value = NOW(), updated_at = NOW()
            ");
            $stmt2->execute();
        } catch (Throwable $e) {
            error_log("Failed to save last status: " . $e->getMessage());
        }
    }

    /**
     * Recover stuck outbox records that have been in PROCESSING for more than 10 minutes
     * Safely resets them to PENDING so they can be reclaimed
     */
    public static function recoverStuckWorkers(): int
    {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("
                UPDATE whatsapp_notification_outbox
                SET status = 'PENDING',
                    lock_token = NULL,
                    locked_at = NULL,
                    last_error = 'Recovered from stuck PROCESSING state (>10 minutes)',
                    updated_at = NOW()
                WHERE status = 'PROCESSING'
                  AND (locked_at <= NOW() - INTERVAL 10 MINUTE OR (locked_at IS NULL AND updated_at <= NOW() - INTERVAL 10 MINUTE))
            ");
            $stmt->execute();
            $recovered = $stmt->rowCount();
            if ($recovered > 0) {
                error_log("WhatsApp Outbox: Recovered {$recovered} stuck PROCESSING records back to PENDING.");
            }
            return $recovered;
        } catch (Throwable $e) {
            error_log("Failed to recover stuck workers: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Normalize Indian mobile phone number to country code format (e.g. 91XXXXXXXXXX)
     * - Strips spaces, +, dashes, brackets, and non-digits
     * - Prepends 91 to 10-digit Indian numbers starting with 6-9
     * - Keeps 12-digit numbers starting with 91 followed by 6-9
     * - Returns null if invalid
     */
    public static function normalizePhone(?string $phone, string $defaultCountryCode = '91'): ?string
    {
        if ($phone === null) {
            return null;
        }

        $cleaned = preg_replace('/[\s+\-()\[\]]/', '', $phone);
        $cleaned = preg_replace('/\D/', '', $cleaned);

        if ($cleaned === '') {
            return null;
        }

        // 10 digits starting with 6-9
        if (strlen($cleaned) === 10) {
            if (preg_match('/^[6-9]\d{9}$/', $cleaned)) {
                return $defaultCountryCode . $cleaned;
            }
            return null;
        }

        // 12 digits starting with 91 followed by 6-9
        if (strlen($cleaned) === 12 && str_starts_with($cleaned, '91')) {
            $tenDigit = substr($cleaned, 2);
            if (preg_match('/^[6-9]\d{9}$/', $tenDigit)) {
                return $cleaned;
            }
            return null;
        }

        return null;
    }

    /**
     * Mask mobile number for safe display in UI and audit logs (e.g. 91******1234)
     */
    public static function maskPhone(?string $phone): string
    {
        if (empty($phone)) {
            return 'N/A';
        }
        $digits = preg_replace('/\D/', '', $phone);
        $len = strlen($digits);
        if ($len <= 4) {
            return str_repeat('*', $len);
        }
        return substr($digits, 0, 2) . str_repeat('*', max(0, $len - 6)) . substr($digits, -4);
    }

    /**
     * Mask API token for safe display (never expose actual token in HTML/JS)
     */
    public static function maskToken(?string $token): string
    {
        if (empty($token)) {
            return '';
        }
        $len = strlen($token);
        if ($len <= 8) {
            return '••••••••';
        }
        return substr($token, 0, 3) . str_repeat('*', max(6, $len - 7)) . substr($token, -4);
    }

    /**
     * Build standard, English-only WhatsApp message for new application
     * Does NOT include Aadhaar, wallet balance, documents, or sensitive payment details.
     */
    public static function buildMessage(array $data): string
    {
        $applicantName = trim((string)($data['applicant_name'] ?? 'Applicant'));
        $applicationNumber = trim((string)($data['application_number'] ?? ($data['applicant_identifier'] ?? '')));
        $appRef = trim((string)($data['app_ref'] ?? ''));
        $districtName = trim((string)($data['district_name'] ?? 'District'));
        $blockName = trim((string)($data['block_name'] ?? 'Block'));
        $applicationCode = trim((string)($data['application_code'] ?? ''));

        if ($applicationNumber === '') {
            $applicationNumber = $appRef;
        }
        if ($applicationCode === '') {
            $applicationCode = 'N/A';
        }

        return "New application received\n\n"
            . "Applicant: {$applicantName}\n"
            . "Application No: {$applicationNumber}\n"
            . "Location: {$districtName} > {$blockName}";
    }

    /**
     * Queue notification inside the application submission transaction.
     * Selects all active operators assigned to the application's district & block.
     * Inserts idempotent outbox entries into `whatsapp_notification_outbox`.
     *
     * @return array Summary of queued, skipped, and invalid operator counts
     */
    public static function queueSubmissionNotification(
        int $applicationId,
        int $districtId,
        int $blockId,
        array $appData,
        PDO $db
    ): array {
        $settings = self::getSettings();
        $messageBody = self::buildMessage($appData);
        $appRef = (string)($appData['app_ref'] ?? "ID #{$applicationId}");
        $districtName = (string)($appData['district_name'] ?? 'District');
        $blockName = (string)($appData['block_name'] ?? 'Block');

        // Fetch all active operators assigned to the specific application district & block
        $opStmt = $db->prepare("
            SELECT id, name, mobile
            FROM users
            WHERE role = 'OPERATOR'
              AND status = 'ACTIVE'
              AND assigned_district_id = ?
              AND assigned_block_id = ?
            ORDER BY id ASC
        ");
        $opStmt->execute([$districtId, $blockId]);
        $operators = $opStmt->fetchAll(PDO::FETCH_ASSOC);

        $outboxInsert = $db->prepare("
            INSERT INTO whatsapp_notification_outbox (
                event_key, event_type, application_id, recipient_user_id, recipient_mobile,
                message_body, status, attempts, next_attempt_at, last_error, created_at, updated_at
            ) VALUES (
                ?, 'APPLICATION_SUBMITTED', ?, ?, ?,
                ?, ?, ?, ?, ?, NOW(), NOW()
            )
            ON DUPLICATE KEY UPDATE updated_at = NOW()
        ");

        // Case 1: No eligible Block Operator exists
        if (empty($operators)) {
            $noOpKey = "APPLICATION_SUBMITTED_NO_OPERATOR:{$applicationId}";
            $reason = 'No active Block Operator is assigned to this District and Block.';
            $outboxInsert->execute([
                $noOpKey,
                $applicationId,
                null,
                'UNASSIGNED',
                $messageBody,
                'SKIPPED',
                0,
                null,
                $reason
            ]);

            AuditService::log('WHATSAPP_NOTIFICATION_SKIPPED', 'APPLICATION', (string)$applicationId, [
                'event_key' => $noOpKey,
                'reason' => $reason,
                'district_id' => $districtId,
                'block_id' => $blockId
            ]);

            // Real Admin In-App Alert
            self::alertAdmins(
                'No Active Block Operator Assigned',
                "Application {$appRef} was submitted for location {$districtName} > {$blockName}, but no active Block Operator is assigned.",
                'WARNING',
                '/admin/whatsapp/logs'
            );

            return [
                'operators_found' => 0,
                'queued_count' => 0,
                'skipped_count' => 1,
                'warning' => $reason
            ];
        }

        // Case 2: Process eligible operators, remove duplicate phone numbers
        $queuedCount = 0;
        $skippedCount = 0;
        $seenMobiles = [];

        foreach ($operators as $op) {
            $opId = (int)$op['id'];
            $opName = (string)($op['name'] ?? 'Operator');
            $rawMobile = (string)($op['mobile'] ?? '');
            $normalizedMobile = self::normalizePhone($rawMobile, $settings['default_country_code']);

            $eventKey = "APPLICATION_SUBMITTED_OPERATOR:{$applicationId}:{$opId}";

            if (!$normalizedMobile) {
                // Operator exists but mobile is invalid
                $reason = 'Invalid operator mobile number format: ' . substr(preg_replace('/[^0-9]/', '', $rawMobile) ?: 'empty', 0, 10);
                $outboxInsert->execute([
                    $eventKey,
                    $applicationId,
                    $opId,
                    substr($rawMobile ?: 'INVALID', 0, 20),
                    $messageBody,
                    'SKIPPED',
                    0,
                    null,
                    $reason
                ]);

                AuditService::log('WHATSAPP_NOTIFICATION_SKIPPED', 'APPLICATION', (string)$applicationId, [
                    'event_key' => $eventKey,
                    'operator_id' => $opId,
                    'reason' => $reason
                ]);

                // Real Admin In-App Alert (without sensitive full mobile)
                self::alertAdmins(
                    'Invalid Operator Mobile Number',
                    "Block Operator '{$opName}' assigned to {$districtName} > {$blockName} has an invalid mobile format. WhatsApp notification was skipped.",
                    'WARNING',
                    '/admin/users'
                );

                $skippedCount++;
                continue;
            }

            // Remove duplicate recipients when multiple accounts have the same mobile number
            if (isset($seenMobiles[$normalizedMobile])) {
                $reason = "Duplicate mobile number already queued for operator #{$seenMobiles[$normalizedMobile]}";
                $outboxInsert->execute([
                    $eventKey,
                    $applicationId,
                    $opId,
                    $normalizedMobile,
                    $messageBody,
                    'SKIPPED',
                    0,
                    null,
                    $reason
                ]);

                $skippedCount++;
                continue;
            }

            $seenMobiles[$normalizedMobile] = $opId;

            // Insert pending outbox row
            $outboxInsert->execute([
                $eventKey,
                $applicationId,
                $opId,
                $normalizedMobile,
                $messageBody,
                'PENDING',
                0,
                date('Y-m-d H:i:s'),
                null
            ]);

            AuditService::log('WHATSAPP_NOTIFICATION_QUEUED', 'APPLICATION', (string)$applicationId, [
                'event_key' => $eventKey,
                'operator_id' => $opId,
                'recipient_mobile' => self::maskPhone($normalizedMobile)
            ]);

            $queuedCount++;
        }

        return [
            'operators_found' => count($operators),
            'queued_count' => $queuedCount,
            'skipped_count' => $skippedCount
        ];
    }

    /**
     * Dispatch pending outbox rows with atomic token claim and stuck recovery
     *
     * @param int $limit Maximum number of pending messages to process
     * @param int|null $specificId Optional specific outbox ID (for admin retry)
     * @return array Summary of dispatched results
     */
    public static function dispatchPending(int $limit = 20, ?int $specificId = null): array
    {
        // 1. Recover records stuck in PROCESSING for more than 10 minutes
        self::recoverStuckWorkers();

        // 2. Check if sending is allowed
        $settings = self::getSettings();
        $allowedCheck = self::isSendingAllowed($settings);
        if (!$allowedCheck['allowed']) {
            return [
                'status' => $allowedCheck['status'],
                'message' => $allowedCheck['reason'],
                'processed' => 0
            ];
        }

        $db = Database::getConnection();

        // 3. Find eligible candidate IDs
        if ($specificId !== null) {
            $stmt = $db->prepare("
                SELECT id FROM whatsapp_notification_outbox
                WHERE id = ?
                  AND status = 'PENDING'
                  AND (next_attempt_at IS NULL OR next_attempt_at <= NOW())
                  AND (lock_token IS NULL OR locked_at <= NOW() - INTERVAL 10 MINUTE)
                LIMIT 1
            ");
            $stmt->execute([$specificId]);
        } else {
            $stmt = $db->prepare("
                SELECT id FROM whatsapp_notification_outbox
                WHERE status = 'PENDING'
                  AND (next_attempt_at IS NULL OR next_attempt_at <= NOW())
                  AND (lock_token IS NULL OR locked_at <= NOW() - INTERVAL 10 MINUTE)
                ORDER BY id ASC
                LIMIT ?
            ");
            $stmt->bindValue(1, $limit, PDO::PARAM_INT);
            $stmt->execute();
        }

        $candidateIds = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

        $results = [
            'sent' => 0,
            'failed' => 0,
            'retrying' => 0,
            'processed' => 0
        ];

        // 4. Claim and send each candidate atomically
        foreach ($candidateIds as $cid) {
            $claimToken = bin2hex(random_bytes(16));

            // Atomic database claim: only the worker that matches and claims gets to send
            $claimStmt = $db->prepare("
                UPDATE whatsapp_notification_outbox
                SET status = 'PROCESSING',
                    lock_token = :token,
                    locked_at = NOW(),
                    updated_at = NOW()
                WHERE id = :id
                  AND status = 'PENDING'
                  AND (next_attempt_at IS NULL OR next_attempt_at <= NOW())
                  AND (lock_token IS NULL OR locked_at <= NOW() - INTERVAL 10 MINUTE)
            ");
            $claimStmt->execute([
                ':token' => $claimToken,
                ':id' => (int)$cid
            ]);

            if ($claimStmt->rowCount() === 0) {
                continue; // Claimed by another concurrent process
            }

            // Fetch the freshly claimed row
            $fetchStmt = $db->prepare("SELECT * FROM whatsapp_notification_outbox WHERE id = ? AND lock_token = ?");
            $fetchStmt->execute([(int)$cid, $claimToken]);
            $row = $fetchStmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                continue;
            }

            // Send via atomic lock
            $res = self::sendOne($row, $claimToken, $settings);
            $results['processed']++;
            if ($res['status'] === 'SENT') {
                $results['sent']++;
            } elseif ($res['status'] === 'FAILED') {
                $results['failed']++;
            } elseif ($res['status'] === 'PENDING') {
                $results['retrying']++;
            }
        }

        return $results;
    }

    /**
     * Send a single outbox row using POST https://wbapi.in/send-api.php (multipart/form-data)
     * Enforces atomic claim token check and reliable retry schedule
     */
    public static function sendOne(array $row, ?string $claimToken = null, ?array $settings = null): array
    {
        $db = Database::getConnection();
        if ($settings === null) {
            $settings = self::getSettings();
        }

        $rowId = (int)$row['id'];
        $appId = (int)$row['application_id'];
        $recipientUserId = $row['recipient_user_id'] ? (int)$row['recipient_user_id'] : null;
        $toMobile = (string)$row['recipient_mobile'];
        $messageBody = (string)$row['message_body'];
        $attempts = (int)$row['attempts'] + 1;

        // Never send a SENT record again
        $currStmt = $db->prepare("SELECT status, lock_token FROM whatsapp_notification_outbox WHERE id = ?");
        $currStmt->execute([$rowId]);
        $curr = $currStmt->fetch(PDO::FETCH_ASSOC);
        if (!$curr) {
            return ['status' => 'FAILED', 'error' => 'Outbox record not found'];
        }
        if ($curr['status'] === 'SENT') {
            return ['status' => 'SENT', 'error' => 'Record has already been sent'];
        }

        // Validate or establish atomic claim token
        if ($claimToken !== null) {
            if ($curr['lock_token'] !== $claimToken || $curr['status'] !== 'PROCESSING') {
                return ['status' => 'FAILED', 'error' => 'Lock token mismatch or row not claimed'];
            }
        } else {
            // Unclaimed direct call: atomically claim row now to prevent duplicate send
            $claimToken = bin2hex(random_bytes(16));
            $claimStmt = $db->prepare("
                UPDATE whatsapp_notification_outbox
                SET status = 'PROCESSING',
                    lock_token = :token,
                    locked_at = NOW(),
                    updated_at = NOW()
                WHERE id = :id
                  AND status != 'SENT'
                  AND (lock_token IS NULL OR locked_at <= NOW() - INTERVAL 10 MINUTE)
            ");
            $claimStmt->execute([':token' => $claimToken, ':id' => $rowId]);
            if ($claimStmt->rowCount() === 0) {
                return ['status' => 'FAILED', 'error' => 'Unable to claim row (already sent or locked by another worker)'];
            }
        }

        // Security check: validate API base URL host is strictly wbapi.in
        $baseUrl = rtrim($settings['api_base_url'] ?: self::DEFAULT_BASE_URL, '/');
        $parsedUrl = parse_url($baseUrl);
        $host = strtolower($parsedUrl['host'] ?? '');
        $scheme = strtolower($parsedUrl['scheme'] ?? '');

        if ($scheme !== 'https' || $host !== self::ALLOWED_API_HOST) {
            $errorMsg = "Security rejection: WhatsApp API host must be strictly https://" . self::ALLOWED_API_HOST;
            self::markFailedPermanent($db, $rowId, $claimToken, $attempts, 400, $errorMsg, $appId, $recipientUserId, $toMobile);
            return ['status' => 'FAILED', 'error' => $errorMsg];
        }

        $apiToken = $settings['api_token'];
        $sessionId = $settings['session_id'];

        if (empty($apiToken) || empty($sessionId)) {
            $errorMsg = "Configuration error: WhatsApp API token or Session ID is not configured.";
            self::markFailedPermanent($db, $rowId, $claimToken, $attempts, 400, $errorMsg, $appId, $recipientUserId, $toMobile);
            return ['status' => 'FAILED', 'error' => $errorMsg];
        }

        $endpointUrl = $baseUrl . self::SEND_ENDPOINT;

        // Multipart/form-data payload required by WBAPI
        $postFields = [
            'type' => 'text',
            'to' => $toMobile,
            'text' => $messageBody,
            'sessionId' => $sessionId
        ];

        $headers = [
            'x-api-key: ' . $apiToken
        ];

        // Execute request via mock transport or server-side cURL
        $response = self::executeHttpRequest($endpointUrl, $headers, $postFields);
        $httpCode = $response['http_code'];
        $rawBody = $response['body'];
        $curlError = $response['error'];
        $respHeaders = $response['headers'] ?? [];

        // Provider response parsing
        $json = null;
        if (!empty($rawBody)) {
            $json = @json_decode($rawBody, true);
        }

        // Success condition: HTTP 200..299 AND json['success'] is truthy
        $isSuccess = ($httpCode >= 200 && $httpCode < 300)
            && is_array($json)
            && isset($json['success'])
            && ($json['success'] === true || $json['success'] === 'true' || $json['success'] === 1);

        if ($isSuccess) {
            $rawStatus = strtolower(trim((string)($json['data']['status'] ?? 'sent')));
            $providerStatus = ($rawStatus === 'pending' || $rawStatus === '') ? 'sent' : $rawStatus;
            $providerMsgId = $json['data']['messageId'] ?? ($json['data']['id'] ?? null);

            $update = $db->prepare("
                UPDATE whatsapp_notification_outbox
                SET status = 'SENT',
                    lock_token = NULL,
                    locked_at = NULL,
                    attempts = ?,
                    response_code = ?,
                    provider_status = ?,
                    provider_message_id = ?,
                    last_error = NULL,
                    sent_at = NOW(),
                    updated_at = NOW(),
                    next_attempt_at = NULL
                WHERE id = ? AND (lock_token = ? OR ? IS NULL)
            ");
            $update->execute([$attempts, $httpCode, $providerStatus, $providerMsgId, $rowId, $claimToken, $claimToken]);

            AuditService::log('WHATSAPP_NOTIFICATION_SENT', 'APPLICATION', (string)$appId, [
                'outbox_id' => $rowId,
                'recipient_id' => $recipientUserId,
                'recipient_mobile' => self::maskPhone($toMobile),
                'provider_status' => $providerStatus,
                'attempts' => $attempts
            ]);

            // Auto-transition application from PENDING to IN_PROCESS upon confirmed WhatsApp notification delivery
            if ($appId > 0) {
                try {
                    $chkApp = $db->prepare("SELECT id, status, app_ref FROM applications WHERE id = ? LIMIT 1");
                    $chkApp->execute([$appId]);
                    $appRow = $chkApp->fetch(PDO::FETCH_ASSOC);

                    if ($appRow && $appRow['status'] === 'PENDING') {
                        $updApp = $db->prepare("
                            UPDATE applications 
                            SET status = 'IN_PROCESS', 
                                updated_at = NOW() 
                            WHERE id = ? AND status = 'PENDING'
                        ");
                        $updApp->execute([$appId]);

                        if ($updApp->rowCount() > 0) {
                            $hist = $db->prepare("
                                INSERT INTO application_status_history (
                                    application_id, from_status, to_status, actor_user_id,
                                    actor_name, actor_role, remarks, created_at
                                ) VALUES (
                                    ?, 'PENDING', 'IN_PROCESS', ?,
                                    'WhatsApp Notification System', 'SYSTEM', 'Auto-transitioned to IN_PROCESS upon confirmed WhatsApp notification sent to operator', NOW()
                                )
                            ");
                            $hist->execute([$appId, $recipientUserId]);

                            AuditService::log('APPLICATION_AUTO_IN_PROCESS', 'APPLICATION', (string)$appId, [
                                'app_ref' => $appRow['app_ref'],
                                'outbox_id' => $rowId,
                                'trigger' => 'WHATSAPP_CONFIRMED_SENT'
                            ]);
                        }
                    }
                } catch (\Throwable $e) {
                    error_log("Failed to auto-transition application {$appId} to IN_PROCESS: " . $e->getMessage());
                }
            }

            return ['status' => 'SENT', 'data' => $json['data'] ?? []];
        }

        // Error handling based on HTTP code and provider response
        $sanitizedError = self::sanitizeError($httpCode, $curlError, $rawBody, $json);

        // 400: Invalid or missing field -> Permanent failure
        if ($httpCode === 400) {
            self::markFailedPermanent($db, $rowId, $claimToken, $attempts, $httpCode, $sanitizedError, $appId, $recipientUserId, $toMobile);
            return ['status' => 'FAILED', 'error' => $sanitizedError];
        }

        // 401: Invalid API token -> Permanent failure, block provider sending, alert admin
        if ($httpCode === 401) {
            self::markFailedPermanent($db, $rowId, $claimToken, $attempts, $httpCode, $sanitizedError, $appId, $recipientUserId, $toMobile);
            self::blockProvider('Provider rejected API token with HTTP 401 Unauthorized');
            self::alertAdmins(
                'WhatsApp API Authentication Failed (HTTP 401)',
                'WBAPI rejected the API credentials. Provider sending is blocked until updated in Settings.',
                'DANGER',
                '/admin/settings#whatsapp-settings'
            );
            return ['status' => 'FAILED', 'error' => $sanitizedError];
        }

        // Check if Retry-After is supplied on 429
        $customDelay = null;
        if ($httpCode === 429) {
            $retryAfterHeader = $respHeaders['retry-after'] ?? ($respHeaders['Retry-After'] ?? null);
            if ($retryAfterHeader !== null && is_numeric($retryAfterHeader)) {
                $customDelay = max(60, min(86400, (int)$retryAfterHeader));
            }
            self::alertAdmins(
                'WhatsApp Quota Reached (HTTP 429)',
                'WBAPI message quota or rate limit reached. Delivery has been postponed and will retry automatically.',
                'WARNING',
                '/admin/whatsapp/logs'
            );
        }

        // Check max attempts
        if ($attempts >= self::MAX_ATTEMPTS) {
            $finalErr = "Max retries reached (" . self::MAX_ATTEMPTS . "). {$sanitizedError}";
            self::markFailedPermanent($db, $rowId, $claimToken, $attempts, $httpCode, $finalErr, $appId, $recipientUserId, $toMobile);
            self::alertAdmins(
                'WhatsApp Notification Delivery Failed',
                "WhatsApp notification for application #{$appId} failed permanently after " . self::MAX_ATTEMPTS . " attempts.",
                'DANGER',
                '/admin/whatsapp/logs'
            );
            return ['status' => 'FAILED', 'error' => $finalErr];
        }

        // Reliable Retry Backoff Schedule:
        // Attempt 1: immediate
        // Attempt 2: after 1 minute (+60s)
        // Attempt 3: after 5 minutes (+300s)
        // Attempt 4: after 15 minutes (+900s)
        $delaySeconds = $customDelay ?: match ($attempts) {
            1 => 60,
            2 => 300,
            default => 900
        };

        $nextAttemptAt = date('Y-m-d H:i:s', time() + $delaySeconds);

        $update = $db->prepare("
            UPDATE whatsapp_notification_outbox
            SET status = 'PENDING',
                lock_token = NULL,
                locked_at = NULL,
                attempts = ?,
                response_code = ?,
                last_error = ?,
                next_attempt_at = ?,
                updated_at = NOW()
            WHERE id = ? AND (lock_token = ? OR ? IS NULL)
        ");
        $update->execute([$attempts, $httpCode ?: null, $sanitizedError, $nextAttemptAt, $rowId, $claimToken, $claimToken]);

        AuditService::log('WHATSAPP_NOTIFICATION_FAILED', 'APPLICATION', (string)$appId, [
            'outbox_id' => $rowId,
            'recipient_id' => $recipientUserId,
            'recipient_mobile' => self::maskPhone($toMobile),
            'http_code' => $httpCode,
            'attempts' => $attempts,
            'next_attempt_at' => $nextAttemptAt,
            'error' => $sanitizedError
        ]);

        return [
            'status' => 'PENDING',
            'attempts' => $attempts,
            'next_attempt_at' => $nextAttemptAt,
            'error' => $sanitizedError
        ];
    }

    /**
     * Permanent failure helper
     */
    private static function markFailedPermanent(
        PDO $db,
        int $rowId,
        ?string $claimToken,
        int $attempts,
        int $httpCode,
        string $error,
        int $appId,
        ?int $recipientUserId,
        string $toMobile
    ): void {
        $update = $db->prepare("
            UPDATE whatsapp_notification_outbox
            SET status = 'FAILED',
                lock_token = NULL,
                locked_at = NULL,
                attempts = ?,
                response_code = ?,
                last_error = ?,
                next_attempt_at = NULL,
                updated_at = NOW()
            WHERE id = ? AND (lock_token = ? OR ? IS NULL)
        ");
        $update->execute([$attempts, $httpCode ?: null, $error, $rowId, $claimToken, $claimToken]);

        AuditService::log('WHATSAPP_NOTIFICATION_FAILED', 'APPLICATION', (string)$appId, [
            'outbox_id' => $rowId,
            'recipient_id' => $recipientUserId,
            'recipient_mobile' => self::maskPhone($toMobile),
            'http_code' => $httpCode,
            'attempts' => $attempts,
            'permanent' => true,
            'error' => $error
        ]);
    }

    /**
     * Sanitize error message to prevent leaking tokens, raw URLs, or credentials
     */
    private static function sanitizeError(int $httpCode, string $curlError, ?string $rawBody, ?array $json): string
    {
        if ($httpCode === 401) {
            return "Authentication failed (HTTP 401): Invalid API token or unauthorized session.";
        }
        if ($httpCode === 400) {
            $msg = $json['message'] ?? ($json['error'] ?? 'Bad request / invalid parameters');
            return "Invalid request (HTTP 400): " . htmlspecialchars(substr((string)$msg, 0, 150), ENT_QUOTES, 'UTF-8');
        }
        if ($httpCode === 429) {
            return "Provider rate limit or quota exceeded (HTTP 429). Retry scheduled.";
        }
        if ($httpCode === 502) {
            return "Provider gateway error (HTTP 502). Retry scheduled.";
        }
        if (!empty($curlError)) {
            // Strip any long token-like strings
            return "Connection error: " . preg_replace('/[a-zA-Z0-9_-]{20,}/', '***', substr($curlError, 0, 150));
        }
        if ($json === null && !empty($rawBody)) {
            return "Provider returned invalid non-JSON response (HTTP {$httpCode}).";
        }
        if (isset($json['message'])) {
            return "Provider error (HTTP {$httpCode}): " . htmlspecialchars(substr((string)$json['message'], 0, 150), ENT_QUOTES, 'UTF-8');
        }
        return "Temporary provider failure (HTTP {$httpCode}). Retry scheduled.";
    }

    /**
     * Execute server-side HTTP request via cURL with SSL verification and short timeouts
     */
    private static function executeHttpRequest(string $url, array $headers, array $postFields): array
    {
        // If a mock transport is registered, use it
        if (self::$transport !== null && is_callable(self::$transport)) {
            return call_user_func(self::$transport, $url, $headers, $postFields);
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        // Resolve CA certificate bundle for reliable SSL verification (prevents Windows OpenSSL issuer errors)
        $caPath = self::getCaBundlePath();
        if ($caPath !== null) {
            curl_setopt($ch, CURLOPT_CAINFO, $caPath);
        }

        $rawResponse = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $error = curl_error($ch);
        curl_close($ch);

        $parsedHeaders = [];
        $body = '';

        if ($rawResponse !== false && is_string($rawResponse)) {
            $headerText = substr($rawResponse, 0, $headerSize);
            $body = substr($rawResponse, $headerSize);

            foreach (explode("\r\n", $headerText) as $line) {
                if (str_contains($line, ':')) {
                    [$k, $v] = explode(':', $line, 2);
                    $parsedHeaders[strtolower(trim($k))] = trim($v);
                }
            }
        }

        return [
            'http_code' => $httpCode,
            'body' => $body,
            'error' => $error ?: '',
            'headers' => $parsedHeaders
        ];
    }

    /**
     * Resolve CA certificate bundle path to ensure strict SSL verification works reliably across Windows and Linux
     */
    private static function getCaBundlePath(): ?string
    {
        $iniCa = ini_get('curl.cainfo') ?: ini_get('openssl.cafile');
        if (!empty($iniCa) && file_exists($iniCa)) {
            return $iniCa;
        }

        $projectCa = dirname(__DIR__, 2) . '/storage/cacert.pem';
        if (file_exists($projectCa)) {
            return $projectCa;
        }

        $knownPaths = [
            'C:\\xampp\\apache\\bin\\curl-ca-bundle.crt',
            'C:\\xampp\\perl\\vendor\\lib\\Mozilla\\CA\\cacert.pem',
            'C:\\xampp\\phpMyAdmin\\vendor\\composer\\ca-bundle\\res\\cacert.pem',
            'C:\\Windows\\System32\\curl-ca-bundle.crt',
            '/etc/ssl/certs/ca-certificates.crt',
            '/etc/pki/tls/certs/ca-bundle.crt'
        ];
        foreach ($knownPaths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Admin action: Retry a FAILED outbox message.
     * Prevents duplicates by atomically resetting FAILED row to PENDING and invoking atomic dispatcher.
     * Never calls sendOne() directly on an unclaimed row!
     * Never touches a SENT row!
     */
    public static function retryFailedMessage(int $outboxId, int $adminUserId): array
    {
        $db = Database::getConnection();

        // Atomic check and reset: ONLY works on FAILED records!
        $resetStmt = $db->prepare("
            UPDATE whatsapp_notification_outbox
            SET status = 'PENDING',
                lock_token = NULL,
                locked_at = NULL,
                next_attempt_at = NOW(),
                last_error = CONCAT('Admin retry requested by admin #', ?, ' at ', NOW()),
                updated_at = NOW()
            WHERE id = ? AND status = 'FAILED'
        ");
        $resetStmt->execute([$adminUserId, $outboxId]);

        if ($resetStmt->rowCount() === 0) {
            // Check why it wasn't updated
            $chk = $db->prepare("SELECT status FROM whatsapp_notification_outbox WHERE id = ?");
            $chk->execute([$outboxId]);
            $currentStatus = $chk->fetchColumn();

            if ($currentStatus === 'SENT') {
                return [
                    'success' => false,
                    'error' => 'This message has already been delivered successfully. Duplicate sending is prevented.'
                ];
            }
            if ($currentStatus === 'PROCESSING') {
                return [
                    'success' => false,
                    'error' => 'This message is currently being processed by another worker.'
                ];
            }
            if ($currentStatus === 'PENDING') {
                return [
                    'success' => false,
                    'error' => 'This message is already queued for delivery.'
                ];
            }

            return [
                'success' => false,
                'error' => 'Record not found or not eligible for retry.'
            ];
        }

        AuditService::log('WHATSAPP_NOTIFICATION_QUEUED', 'APPLICATION', (string)$outboxId, [
            'action' => 'admin_retry',
            'outbox_id' => $outboxId,
            'admin_user_id' => $adminUserId
        ]);

        // Dispatch via atomic claim token (never calls sendOne on an unclaimed row!)
        $dispatchSummary = self::dispatchPending(1, $outboxId);

        return [
            'success' => true,
            'result' => $dispatchSummary
        ];
    }
}
