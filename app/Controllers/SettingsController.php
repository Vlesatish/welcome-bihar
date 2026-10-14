<?php
namespace App\Controllers;

use App\Config\Database;
use App\Services\AuthService;
use App\Services\AuditService;
use Exception;

class SettingsController
{
    public function __construct()
    {
        if (!AuthService::isAdmin()) {
            header('Location: /login');
            exit;
        }
    }

    public function index(): void
    {
        $db = Database::getConnection();

        $rows = $db->query("SELECT setting_key, setting_value, setting_group, description FROM settings")->fetchAll();
        $settings = [];
        foreach ($rows as $r) {
            $settings[$r['setting_key']] = $r['setting_value'];
        }

        // Enrich with WhatsApp provider health status
        $wa = \App\Services\WhatsAppNotificationService::getSettings();
        $settings['is_env_controlled'] = $wa['is_env_controlled'];
        $settings['whatsapp_provider_blocked'] = $wa['provider_blocked'];
        $settings['whatsapp_provider_blocked_reason'] = $wa['provider_blocked_reason'];
        $settings['whatsapp_last_status'] = $wa['last_status'];
        $settings['whatsapp_last_status_checked_at'] = $wa['last_status_checked_at'];

        require dirname(__DIR__) . '/Views/admin/settings.php';
    }

    public function save(): void
    {
        if (!AuthService::validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $_SESSION['flash_error'] = 'Invalid CSRF token.';
            header('Location: /admin/settings');
            exit;
        }

        $db = Database::getConnection();

        $allowedKeys = [
            'portal_name', 'portal_tagline', 'default_language', 'support_phone', 'support_email',
            'distributor_review_required', 'refund_on_rejection',
            'gateway_auto_upi', 'gateway_merchant_name', 'gateway_upi_id',
            'allapi_mode', 'allapi_base_url', 'allapi_create_endpoint', 'allapi_status_endpoint',
            'allapi_redirect_url', 'allapi_callback_url', 'allapi_token',
            'whatsapp_enabled', 'whatsapp_api_base_url', 'whatsapp_api_token', 'whatsapp_session_id', 'whatsapp_default_country_code'
        ];

        // Security check for WhatsApp API Base URL: restrict strictly to wbapi.in and HTTPS
        if (isset($_POST['whatsapp_api_base_url'])) {
            $wUrl = trim((string)$_POST['whatsapp_api_base_url']);
            $parsed = parse_url($wUrl);
            $scheme = strtolower($parsed['scheme'] ?? '');
            $host = strtolower($parsed['host'] ?? '');
            if ($scheme !== 'https' || $host !== 'wbapi.in') {
                $_SESSION['flash_error'] = 'Security Error: WhatsApp API Base URL must use HTTPS and host wbapi.in';
                header('Location: /admin/settings');
                exit;
            }
        }

        $stmt = $db->prepare("
            INSERT INTO settings (setting_key, setting_value, updated_at)
            VALUES (?, ?, NOW())
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()
        ");

        $updatedCount = 0;
        $waUpdated = false;
        $hasEnvEnabled = (getenv('WHATSAPP_ENABLED') !== false || isset($_ENV['WHATSAPP_ENABLED']));

        foreach ($allowedKeys as $key) {
            if ($key === 'whatsapp_enabled' && $hasEnvEnabled) {
                // If controlled by environment, do not pretend database setting controls it
                continue;
            }

            if (isset($_POST[$key])) {
                $val = trim((string)$_POST[$key]);
                // If API token is blank on submit, preserve existing token!
                if ($key === 'allapi_token' && $val === '') {
                    continue;
                }
                if ($key === 'whatsapp_api_token' && $val === '') {
                    continue;
                }
                $stmt->execute([$key, $val]);
                $updatedCount++;
                if (str_starts_with($key, 'whatsapp_')) {
                    $waUpdated = true;
                }
            }
        }

        // Checkbox toggles that may be unchecked in POST
        if (!isset($_POST['gateway_auto_upi'])) {
            $stmt->execute(['gateway_auto_upi', '0']);
        }
        if (!isset($_POST['distributor_review_required'])) {
            $stmt->execute(['distributor_review_required', '0']);
        }
        if (!isset($_POST['refund_on_rejection'])) {
            $stmt->execute(['refund_on_rejection', '0']);
        }
        if (!$hasEnvEnabled && !isset($_POST['whatsapp_enabled'])) {
            $stmt->execute(['whatsapp_enabled', '0']);
            $waUpdated = true;
        }

        AuditService::log('SETTINGS_UPDATED', 'SETTINGS', 'SYSTEM', ['updated_keys' => $updatedCount]);
        if ($waUpdated) {
            AuditService::log('WHATSAPP_SETTINGS_UPDATED', 'SETTINGS', 'WHATSAPP', ['updated' => true]);

            // Auto-check connection status on credential update
            $statusRes = \App\Services\WhatsAppNotificationService::checkSessionStatus();
            if ($statusRes['status'] === 'Connected') {
                \App\Services\WhatsAppNotificationService::unblockProvider();
            }
        }

        $_SESSION['flash_success'] = 'System and WhatsApp notification settings updated successfully.';
        header('Location: /admin/settings#whatsapp-settings');
        exit;
    }

    public function checkWhatsAppSession(): void
    {
        if (!AuthService::validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $_SESSION['flash_error'] = 'Invalid CSRF token.';
            header('Location: /admin/settings#whatsapp-settings');
            exit;
        }

        $res = \App\Services\WhatsAppNotificationService::checkSessionStatus();
        if ($res['status'] === 'Connected') {
            $_SESSION['flash_success'] = 'WhatsApp Session Status: Connected. ' . $res['message'];
        } elseif ($res['status'] === 'Disconnected') {
            $_SESSION['flash_warning'] = 'WhatsApp Session Status: Disconnected. ' . $res['message'];
        } elseif ($res['status'] === 'Not Configured') {
            $_SESSION['flash_info'] = 'WhatsApp Session Status: Not Configured. ' . $res['message'];
        } else {
            $_SESSION['flash_error'] = 'WhatsApp Session Status: ' . $res['status'] . '. ' . $res['message'];
        }

        header('Location: /admin/settings#whatsapp-settings');
        exit;
    }

    public function unblockWhatsApp(): void
    {
        if (!AuthService::validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $_SESSION['flash_error'] = 'Invalid CSRF token.';
            header('Location: /admin/settings#whatsapp-settings');
            exit;
        }

        // Verify session first! Allow ADMIN to clear block only after successful session check
        $res = \App\Services\WhatsAppNotificationService::checkSessionStatus();
        if ($res['status'] === 'Connected') {
            \App\Services\WhatsAppNotificationService::unblockProvider();
            $_SESSION['flash_success'] = 'Provider block cleared successfully! Session verified as Connected.';
        } else {
            $_SESSION['flash_error'] = 'Cannot clear block: Session verification returned ' . $res['status'] . ' (' . $res['message'] . '). Please ensure valid credentials are saved before unblocking.';
        }

        header('Location: /admin/settings#whatsapp-settings');
        exit;
    }
}
