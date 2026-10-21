<?php
/**
 * Welcome Bihar - Front Controller & Application Router
 * Bihar Unified District & Block Citizen Services Data Portal
 */

declare(strict_types=1);

// Built-in PHP development server: serve existing static assets directly
if (php_sapi_name() === 'cli-server') {
    $urlPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    if ($urlPath !== null && $urlPath !== '/' && $urlPath !== '') {
        $staticFile = __DIR__ . $urlPath;
        if (file_exists($staticFile) && is_file($staticFile)) {
            return false;
        }
    }
}

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
\App\Services\SystemUpdateService::ensureSchema();

// Environment-aware error reporting: log errors securely, suppress browser traces in production
$isProduction = strtolower(\App\Config\Config::get('APP_ENV', 'development')) === 'production'
    || strtolower(\App\Config\Config::get('APP_DEBUG', 'true')) === 'false';

$logDir = dirname(__DIR__) . '/storage/logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
}
ini_set('error_log', $logDir . '/app.log');

if ($isProduction) {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    ini_set('log_errors', '1');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
}

// Set security headers
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=(), camera=(), microphone=()');

$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
    || str_starts_with((string)\App\Config\Config::get('APP_URL', ''), 'https://');

if ($isHttps && $isProduction) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';
$requestMethod = $_SERVER['REQUEST_METHOD'];

// Validate active user status on every protected request
if (\App\Services\AuthService::check()) {
    $currentAuthUser = \App\Services\AuthService::user();
    if (!$currentAuthUser) {
        \App\Services\AuthService::logout();
        if (str_starts_with($requestUri, '/api/')) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Your account is inactive or session has expired.']);
            exit;
        }
        $_SESSION['flash_error'] = 'Your account has been deactivated or suspended. Please contact administrator.';
        header('Location: /login');
        exit;
    }
}

// Route Dispatcher
try {
    switch ($requestUri) {
        // Landing & Auth
        case '/':
            if (\App\Services\AuthService::check()) {
                $role = \App\Services\AuthService::role();
                if ($role === 'ADMIN' || $role === 'OPERATOR') header('Location: /admin/dashboard');
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

        case '/distributor/retailers/new':
            $ctrl = new \App\Controllers\DistributorController();
            if ($requestMethod === 'POST') {
                $ctrl->createRetailer();
            } else {
                $ctrl->addRetailer();
            }
            break;

        case '/distributor/retailers/create':
            $ctrl = new \App\Controllers\DistributorController();
            $ctrl->createRetailer();
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
        case '/admin':
        case '/admin/dashboard':
            $ctrl = new \App\Controllers\AdminController();
            $ctrl->dashboard();
            break;

        case '/admin/system-update':
            $ctrl = new \App\Controllers\AdminController();
            $ctrl->systemUpdate();
            break;

        case '/admin/applications':
            $ctrl = new \App\Controllers\ApplicationController();
            $ctrl->adminApplications();
            break;

        case '/admin/applications/reassign':
            $ctrl = new \App\Controllers\ApplicationController();
            $ctrl->reassignStaff();
            break;

        case '/admin/staff-routing':
            $ctrl = new \App\Controllers\AdminController();
            $ctrl->staffRouting();
            break;

        case '/admin/staff-routing/save':
            $ctrl = new \App\Controllers\AdminController();
            $ctrl->saveStaffRouting();
            break;

        case '/admin/staff-routing/api-block-data':
            $ctrl = new \App\Controllers\AdminController();
            $ctrl->apiGetBlockStaffRouting();
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

        case '/admin/operators/create':
            $ctrl = new \App\Controllers\AdminController();
            $ctrl->createOperator();
            break;

        case '/admin/operators/reset-password':
            $ctrl = new \App\Controllers\AdminController();
            $ctrl->resetOperatorPassword();
            break;

        case '/admin/operators/reassign':
            $ctrl = new \App\Controllers\AdminController();
            $ctrl->reassignOperator();
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

        case '/admin/service-availability':
            $ctrl = new \App\Controllers\LocationController();
            $ctrl->serviceAvailabilityIndex();
            break;

        case '/admin/service-availability/toggle':
            $ctrl = new \App\Controllers\LocationController();
            $ctrl->toggleServiceAvailability();
            break;

        case '/admin/service-availability/quick-activate':
            $ctrl = new \App\Controllers\LocationController();
            $ctrl->quickActivate();
            break;

        case '/admin/data-types':

            $ctrl = new \App\Controllers\DataTypeController();
            $ctrl->index();
            break;

        case '/admin/data-types/save':
            $ctrl = new \App\Controllers\DataTypeController();
            $ctrl->saveDataType();
            break;

        case '/admin/data-types/toggle-status':
            $ctrl = new \App\Controllers\DataTypeController();
            $ctrl->toggleStatus();
            break;

        case '/admin/data-types/reorder':
            $ctrl = new \App\Controllers\DataTypeController();
            $ctrl->reorderPriority();
            break;

        case '/admin/data-types/notice':
            $ctrl = new \App\Controllers\DataTypeController();
            $ctrl->saveNotice();
            break;

        case '/admin/data-types/delete':
            $ctrl = new \App\Controllers\DataTypeController();
            $ctrl->deleteDataType();
            break;

        case '/admin/block-rates':
        case '/admin/data-types/rate-rules':
            $ctrl = new \App\Controllers\DataTypeController();
            $ctrl->rateRules();
            break;

        case '/admin/block-rates/save-fallbacks':
            $ctrl = new \App\Controllers\DataTypeController();
            $ctrl->saveFallbackRates();
            break;

        case '/admin/block-rates/quick-rate':
            $ctrl = new \App\Controllers\DataTypeController();
            $ctrl->quickUpdateRate();
            break;

        case '/admin/data-types/rate-rules/save':
            $ctrl = new \App\Controllers\DataTypeController();
            $ctrl->saveRateRule();
            break;

        case '/admin/data-types/rate-rules/toggle':
            $ctrl = new \App\Controllers\DataTypeController();
            $ctrl->toggleRateRule();
            break;

        case '/admin/data-types/rate-rules/delete':
            $ctrl = new \App\Controllers\DataTypeController();
            $ctrl->deleteRateRule();
            break;

        case '/admin/data-types/rate-rules/add-location':
            $ctrl = new \App\Controllers\DataTypeController();
            $ctrl->addLocationBlock();
            break;

        case '/admin/data-types/rate-rules/add-app-code':
            $ctrl = new \App\Controllers\DataTypeController();
            $ctrl->addCustomAppCode();
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

        case '/admin/application-code-forms':
            $ctrl = new \App\Controllers\ApplicationCodeFieldController();
            $ctrl->index();
            break;

        case '/admin/application-code-forms/save':
            $ctrl = new \App\Controllers\ApplicationCodeFieldController();
            $ctrl->saveField();
            break;

        case '/admin/application-code-forms/toggle':
            $ctrl = new \App\Controllers\ApplicationCodeFieldController();
            $ctrl->toggleActive();
            break;

        case '/admin/application-code-forms/delete':
            $ctrl = new \App\Controllers\ApplicationCodeFieldController();
            $ctrl->deleteField();
            break;

        case '/admin/application-code-forms/reorder':
            $ctrl = new \App\Controllers\ApplicationCodeFieldController();
            $ctrl->reorder();
            break;

        case '/admin/application-code-forms/init-core':
            $ctrl = new \App\Controllers\ApplicationCodeFieldController();
            $ctrl->initializeCoreFields();
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

        case '/admin/whatsapp/logs':
        case '/admin/whatsapp-logs':
            $ctrl = new \App\Controllers\AdminController();
            $ctrl->whatsappLogs();
            break;

        case '/admin/whatsapp/retry':
            $ctrl = new \App\Controllers\AdminController();
            $ctrl->whatsappRetry();
            break;

        case '/admin/whatsapp/check-session':
            $ctrl = new \App\Controllers\SettingsController();
            $ctrl->checkWhatsAppSession();
            break;

        case '/admin/whatsapp/unblock':
            $ctrl = new \App\Controllers\SettingsController();
            $ctrl->unblockWhatsApp();
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
        case '/wallet/topup/check-status':
            $ctrl = new \App\Controllers\WalletController();
            $ctrl->checkStatus();
            break;

        case '/wallet/topup/reconcile-pending':
            $ctrl = new \App\Controllers\WalletController();
            $ctrl->reconcilePending();
            break;

        case '/wallet/topup/callback':
            // Browser return callback: redirect to pay page or passbook with message
            $orderId = $_GET['order_id'] ?? '';
            $_SESSION['flash_info'] = 'Returned from UPI payment. Checking payment status...';
            header('Location: /wallet/topup/pay?order_id=' . urlencode($orderId));
            exit;

        // API endpoints
        case '/api/districts':
            $ctrl = new \App\Controllers\LocationController();
            $ctrl->getDistricts();
            break;

        case '/api/blocks':
        case '/api/locations/blocks':
        case '/locations/blocks':
            $ctrl = new \App\Controllers\LocationController();
            $ctrl->getBlocks();
            break;

        case '/api/service-districts':
            $ctrl = new \App\Controllers\LocationController();
            $ctrl->getServiceDistricts();
            break;

        case '/api/service-blocks':
            $ctrl = new \App\Controllers\LocationController();
            $ctrl->getServiceBlocks();
            break;

        case '/api/service-application-codes':
            $ctrl = new \App\Controllers\LocationController();
            $ctrl->getServiceApplicationCodes();
            break;

        case '/api/application-code-fields':
            $ctrl = new \App\Controllers\ApplicationCodeFieldController();
            $ctrl->apiGetFields();
            break;


        case '/api/block-rates/calculate':
        case '/api/calculate-fee':
            $ctrl = new \App\Controllers\ApplicationController();
            $ctrl->calculateFee();
            break;

        case '/api/block-rates':
            $ctrl = new \App\Controllers\ApplicationController();
            $ctrl->getBlockRates();
            break;

        case '/api/requests':
            $ctrl = new \App\Controllers\ApplicationController();
            if ($requestMethod === 'POST') {
                $ctrl->apiSubmitRequest();
            } else {
                $ctrl->apiGetRequests();
            }
            break;

        case '/api/requests/batch':
            $ctrl = new \App\Controllers\ApplicationController();
            $ctrl->apiSubmitBatch();
            break;

        case '/api/requests/update-status':
            $ctrl = new \App\Controllers\ApplicationController();
            $ctrl->apiUpdateStatus();
            break;

        case '/api/requests/chat':
            $ctrl = new \App\Controllers\ApplicationController();
            if ($requestMethod === 'POST') {
                $ctrl->apiSendChat();
            } else {
                $ctrl->apiGetChat();
            }
            break;

        case '/api/requests/upload':
            $ctrl = new \App\Controllers\ApplicationController();
            $ctrl->apiUploadFile();
            break;

        case '/api/webhook/allapi':
            $ctrl = new \App\Controllers\WalletController();
            $ctrl->webhook();
            break;

        default:
            // Check parameterized REST routes for requests
            if (preg_match('#^/api/requests/(\d+)/(status|chat)$#', $requestUri, $m)) {
                $ctrl = new \App\Controllers\ApplicationController();
                $_GET['id'] = (int)$m[1];
                if ($m[2] === 'status') {
                    $ctrl->apiUpdateStatus();
                } elseif ($m[2] === 'chat') {
                    if ($requestMethod === 'POST') $ctrl->apiSendChat();
                    else $ctrl->apiGetChat();
                }
                break;
            }

            http_response_code(404);
            require dirname(__DIR__) . '/app/Views/layouts/404.php';
            break;
    }
} catch (\Throwable $e) {
    error_log("Unhandled Application Exception [{$e->getFile()}:{$e->getLine()}]: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    http_response_code(500);

    $isProduction = strtolower(\App\Config\Config::get('APP_ENV', 'development')) === 'production'
        || strtolower(\App\Config\Config::get('APP_DEBUG', 'true')) === 'false';

    if (str_starts_with($requestUri, '/api/') || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => $isProduction ? 'Internal server error. Please try again later.' : $e->getMessage()
        ]);
        exit;
    }

    echo "<div style='font-family: sans-serif; padding: 2rem; max-width: 800px; margin: auto;'>";
    echo "<h2 style='color: #dc3545;'>An unexpected error occurred</h2>";
    if ($isProduction) {
        echo "<p style='color: #666;'>We encountered an internal error while processing your request. The incident has been recorded in the secure system log. Please try again shortly or contact support.</p>";
    } else {
        echo "<p style='color: #666;'>" . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</p>";
        echo "<pre style='background: #f8f9fa; padding: 1rem; border: 1px solid #ddd; border-radius: 4px; overflow-x: auto; font-size: 0.85rem;'>" . htmlspecialchars($e->getTraceAsString(), ENT_QUOTES, 'UTF-8') . "</pre>";
    }
    echo "<a href='/' style='display: inline-block; padding: 0.5rem 1rem; background: #0d6efd; color: #fff; text-decoration: none; border-radius: 4px;'>Return to Homepage</a>";
    echo "</div>";
}
