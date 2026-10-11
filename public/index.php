<?php
/**
 * Welcome Bihar - Front Controller & Application Router
 * Bihar Unified District & Block Citizen Services Data Portal
 */

declare(strict_types=1);

// Error reporting in development
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Autoloader for App namespace
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $baseDir = dirname(__DIR__) . '/app/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// Initialize Configuration, Environment, and Helpers
require_once dirname(__DIR__) . '/app/helpers.php';
\App\Config\Config::init();

// Set security headers
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';
$requestMethod = $_SERVER['REQUEST_METHOD'];

// Route Dispatcher
try {
    switch ($requestUri) {
        // Landing & Auth
        case '/':
            if (\App\Services\AuthService::check()) {
                $role = \App\Services\AuthService::role();
                if ($role === 'ADMIN') header('Location: /admin/dashboard');
                elseif ($role === 'DISTRIBUTOR') header('Location: /distributor/dashboard');
                else header('Location: /user/dashboard');
            } else {
                header('Location: /login');
            }
            exit;

        case '/login':
            $ctrl = new \App\Controllers\AuthController();
            if ($requestMethod === 'POST') $ctrl->login();
            else $ctrl->showLogin();
            break;

        case '/register':
            $ctrl = new \App\Controllers\AuthController();
            if ($requestMethod === 'POST') $ctrl->register();
            else $ctrl->showRegister();
            break;

        case '/logout':
            $ctrl = new \App\Controllers\AuthController();
            $ctrl->logout();
            break;

        case '/lang/switch':
            $ctrl = new \App\Controllers\AuthController();
            $ctrl->switchLang();
            break;

        // User
        case '/user/dashboard':
            $ctrl = new \App\Controllers\UserController();
            $ctrl->dashboard();
            break;

        case '/user/profile':
            $ctrl = new \App\Controllers\UserController();
            if ($requestMethod === 'POST') $ctrl->updateProfile();
            else $ctrl->profile();
            break;

        // Distributor
        case '/distributor/dashboard':
            $ctrl = new \App\Controllers\DistributorController();
            $ctrl->dashboard();
            break;

        case '/distributor/team':
            $ctrl = new \App\Controllers\DistributorController();
            $ctrl->team();
            break;

        case '/distributor/applications':
            $ctrl = new \App\Controllers\DistributorController();
            $ctrl->applications();
            break;

        case '/distributor/applications/review':
            $ctrl = new \App\Controllers\DistributorController();
            $ctrl->reviewApplication();
            break;

        // Admin
        case '/admin/dashboard':
            $ctrl = new \App\Controllers\AdminController();
            $ctrl->dashboard();
            break;

        case '/admin/users':
            $ctrl = new \App\Controllers\AdminController();
            $ctrl->users();
            break;

        case '/admin/users/detail':
            $ctrl = new \App\Controllers\AdminController();
            $ctrl->userDetail();
            break;

        case '/admin/users/update-status':
            $ctrl = new \App\Controllers\AdminController();
            $ctrl->updateUserStatus();
            break;

        case '/admin/users/add-perm':
            $ctrl = new \App\Controllers\AdminController();
            $ctrl->addLocationPermission();
            break;

        case '/admin/users/remove-perm':
            $ctrl = new \App\Controllers\AdminController();
            $ctrl->removeLocationPermission();
            break;

        case '/admin/users/wallet-adjust':
            $ctrl = new \App\Controllers\AdminController();
            $ctrl->manualWalletAdjustment();
            break;

        case '/admin/locations':
            $ctrl = new \App\Controllers\LocationController();
            $ctrl->index();
            break;

        case '/admin/locations/save-district':
            $ctrl = new \App\Controllers\LocationController();
            $ctrl->saveDistrict();
            break;

        case '/admin/locations/save-block':
            $ctrl = new \App\Controllers\LocationController();
            $ctrl->saveBlock();
            break;

        case '/admin/locations/export-csv':
            $ctrl = new \App\Controllers\LocationController();
            $ctrl->exportCsv();
            break;

        case '/admin/locations/import-csv':
            $ctrl = new \App\Controllers\LocationController();
            $ctrl->importCsv();
            break;

        case '/admin/data-types':
            $ctrl = new \App\Controllers\DataTypeController();
            $ctrl->index();
            break;

        case '/admin/data-types/save':
            $ctrl = new \App\Controllers\DataTypeController();
            $ctrl->saveDataType();
            break;

        case '/admin/data-types/fields':
            $ctrl = new \App\Controllers\DataTypeController();
            $ctrl->fields();
            break;

        case '/admin/data-types/fields/save':
            $ctrl = new \App\Controllers\DataTypeController();
            $ctrl->saveField();
            break;

        case '/admin/data-types/fields/delete':
            $ctrl = new \App\Controllers\DataTypeController();
            $ctrl->deleteField();
            break;

        case '/admin/settings':
            $ctrl = new \App\Controllers\SettingsController();
            if ($requestMethod === 'POST') $ctrl->save();
            else $ctrl->index();
            break;

        case '/admin/reconciliation':
            $ctrl = new \App\Controllers\ReconciliationController();
            $ctrl->index();
            break;

        case '/admin/reconciliation/recheck':
            $ctrl = new \App\Controllers\ReconciliationController();
            $ctrl->recheckStatus();
            break;

        case '/admin/reconciliation/verify':
            $ctrl = new \App\Controllers\ReconciliationController();
            $ctrl->manualVerify();
            break;

        case '/admin/audit-logs':
            $ctrl = new \App\Controllers\AdminController();
            $ctrl->auditLogs();
            break;

        // Applications
        case '/applications':
            $ctrl = new \App\Controllers\ApplicationController();
            $ctrl->index();
            break;

        case '/applications/new':
            $ctrl = new \App\Controllers\ApplicationController();
            $ctrl->wizard();
            break;

        case '/applications/submit':
            $ctrl = new \App\Controllers\ApplicationController();
            $ctrl->submit();
            break;

        case '/applications/view':
            $ctrl = new \App\Controllers\ApplicationController();
            $ctrl->view();
            break;

        case '/applications/update-status':
            $ctrl = new \App\Controllers\ApplicationController();
            $ctrl->updateStatus();
            break;

        case '/applications/export-csv':
            $ctrl = new \App\Controllers\ApplicationController();
            $ctrl->exportCsv();
            break;

        // Wallet & Payment
        case '/wallet/passbook':
            $ctrl = new \App\Controllers\WalletController();
            $ctrl->passbook();
            break;

        case '/wallet/passbook/export-csv':
            $ctrl = new \App\Controllers\WalletController();
            $ctrl->exportPassbookCsv();
            break;

        case '/wallet/topup':
            $ctrl = new \App\Controllers\WalletController();
            $ctrl->showTopup();
            break;

        case '/wallet/topup/initiate':
            $ctrl = new \App\Controllers\WalletController();
            $ctrl->initiateTopup();
            break;

        case '/wallet/topup/pay':
            $ctrl = new \App\Controllers\WalletController();
            $ctrl->showPaymentQr();
            break;

        case '/wallet/topup/status':
            $ctrl = new \App\Controllers\WalletController();
            $ctrl->checkStatus();
            break;

        case '/wallet/topup/callback':
            // Browser return callback: redirect to pay page or passbook with message
            $orderId = $_GET['order_id'] ?? '';
            $_SESSION['flash_info'] = 'Returned from UPI payment. Checking payment status...';
            header('Location: /wallet/topup/pay?order_id=' . urlencode($orderId));
            exit;

        // API endpoints
        case '/api/blocks':
            $ctrl = new \App\Controllers\LocationController();
            $ctrl->getBlocks();
            break;

        case '/api/webhook/allapi':
            $ctrl = new \App\Controllers\WalletController();
            $ctrl->webhook();
            break;

        default:
            http_response_code(404);
            require dirname(__DIR__) . '/app/Views/layouts/404.php';
            break;
    }
} catch (\Throwable $e) {
    error_log("Unhandled Application Exception: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    http_response_code(500);
    echo "<div style='font-family: sans-serif; padding: 2rem; max-width: 800px; margin: auto;'>";
    echo "<h2 style='color: #dc3545;'>An unexpected error occurred</h2>";
    echo "<p style='color: #666;'>" . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</p>";
    echo "<a href='/' style='display: inline-block; padding: 0.5rem 1rem; background: #0d6efd; color: #fff; text-decoration: none; border-radius: 4px;'>Return to Homepage</a>";
    echo "</div>";
}
