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
            'allapi_mode', 'allapi_base_url', 'allapi_create_endpoint', 'allapi_status_endpoint',
            'allapi_redirect_url', 'allapi_token'
        ];

        $stmt = $db->prepare("
            INSERT INTO settings (setting_key, setting_value, updated_at)
            VALUES (?, ?, NOW())
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()
        ");

        $updatedCount = 0;
        foreach ($allowedKeys as $key) {
            if (isset($_POST[$key])) {
                $val = trim((string)$_POST[$key]);
                $stmt->execute([$key, $val]);
                $updatedCount++;
            }
        }

        // Checkbox toggles that may be unchecked in POST
        if (!isset($_POST['distributor_review_required'])) {
            $stmt->execute(['distributor_review_required', '0']);
        }
        if (!isset($_POST['refund_on_rejection'])) {
            $stmt->execute(['refund_on_rejection', '0']);
        }

        AuditService::log('SETTINGS_UPDATED', 'SETTINGS', 'SYSTEM', ['updated_keys' => $updatedCount]);

        $_SESSION['flash_success'] = 'System settings updated successfully.';
        header('Location: /admin/settings');
        exit;
    }
}
