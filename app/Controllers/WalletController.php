<?php
namespace App\Controllers;

use App\Config\Database;
use App\Services\AuthService;
use App\Services\WalletService;
use App\Services\AllApiProvider;
use App\Services\AuditService;
use App\Services\NotificationService;
use App\Services\CsvService;
use Exception;
use PDO;

class WalletController
{
    private AllApiProvider $provider;

    public function __construct()
    {
        $this->provider = new AllApiProvider();
    }

    public function passbook(): void
    {
        if (!AuthService::check()) {
            header('Location: /login');
            exit;
        }

        $userId = AuthService::id();
        $balance = WalletService::getBalance($userId);

        $type = $_GET['type'] ?? '';
        $direction = $_GET['direction'] ?? '';
        $startDate = $_GET['start_date'] ?? '';
        $endDate = $_GET['end_date'] ?? '';

        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = 25;
        $offset = ($page - 1) * $limit;

        $filters = array_filter([
            'type' => $type,
            'direction' => $direction,
            'start_date' => $startDate,
            'end_date' => $endDate
        ]);

        $result = WalletService::getPassbook($userId, $limit, $offset, $filters);
        $totalRecords = $result['total'];
        $totalPages = ceil($totalRecords / $limit);
        $transactions = $result['transactions'];

        require dirname(__DIR__) . '/Views/user/passbook.php';
    }

    public function showTopup(): void
    {
        if (!AuthService::check()) {
            header('Location: /login');
            exit;
        }

        $userId = AuthService::id();
        $db = Database::getConnection();

        $balance = WalletService::getBalance($userId);
        $providerInfo = $this->provider->getProviderInfo();

        // Check auto UPI setting
        $autoUpiSetting = $db->query("SELECT setting_value FROM settings WHERE setting_key = 'gateway_auto_upi'")->fetchColumn();
        $autoUpiEnabled = ($autoUpiSetting === false || ((string)$autoUpiSetting !== '0' && strtolower((string)$autoUpiSetting) !== 'false'));

        // Auto-reconcile recent pending orders on page visit so completed payments credit immediately
        try {
            $pendingStmt = $db->prepare("
                SELECT id, order_id FROM topup_orders 
                WHERE user_id = ? AND status = 'PENDING' AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
                ORDER BY id DESC LIMIT 3
            ");
            $pendingStmt->execute([$userId]);
            $pendingList = $pendingStmt->fetchAll();

            foreach ($pendingList as $po) {
                $statusCheck = $this->provider->checkPaymentStatus($po['order_id']);
                if (!empty($statusCheck['status']) && $statusCheck['status'] === 'PAID') {
                    $this->executeOrderCredit((int)$po['id'], $statusCheck['provider_txn_id'] ?? null, $statusCheck['utr'] ?? null, $statusCheck['raw'] ?? null);
                    $balance = WalletService::getBalance($userId);
                }
            }
        } catch (\Throwable $e) {
            error_log("Auto-reconcile on topup error: " . $e->getMessage());
        }

        // Recent topup orders (fresh after check)
        $stmt = $db->prepare("SELECT * FROM topup_orders WHERE user_id = ? ORDER BY id DESC LIMIT 15");
        $stmt->execute([$userId]);
        $recentOrders = $stmt->fetchAll();

        // Recent active pending order (< 15 min old) to recover on page reload
        $pendingStmt = $db->prepare("
            SELECT * FROM topup_orders 
            WHERE user_id = ? AND status = 'PENDING' AND expires_at > NOW() AND created_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)
            ORDER BY id DESC LIMIT 1
        ");
        $pendingStmt->execute([$userId]);
        $activePendingOrder = $pendingStmt->fetch();

        require dirname(__DIR__) . '/Views/user/topup.php';
    }

    public function initiateTopup(): void
    {
        if (!AuthService::check()) {
            header('Location: /login');
            exit;
        }

        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
               || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
               || isset($_POST['ajax']);

        if (!AuthService::validateCsrfToken($_POST['csrf_token'] ?? '')) {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Invalid CSRF token. Please refresh the page.']);
                exit;
            }
            $_SESSION['flash_error'] = 'Invalid CSRF token.';
            header('Location: /wallet/topup');
            exit;
        }

        $userId = AuthService::id();
        $user = AuthService::user();
        $amount = round((float)($_POST['amount'] ?? 0), 2);

        if ($amount < 50 || $amount > 50000) {
            $msg = __('min_amount_note', 'Minimum top-up amount is ₹50 and maximum is ₹50,000.');
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $msg]);
                exit;
            }
            $_SESSION['flash_error'] = $msg;
            header('Location: /wallet/topup');
            exit;
        }

        $db = Database::getConnection();

        // Check if Automatic UPI Gateway is enabled in settings
        $autoUpiSetting = $db->query("SELECT setting_value FROM settings WHERE setting_key = 'gateway_auto_upi'")->fetchColumn();
        if ($autoUpiSetting !== false && ((string)$autoUpiSetting === '0' || strtolower((string)$autoUpiSetting) === 'false')) {
            $msg = 'Automatic UPI payment gateway is currently disabled by administrator. Please use manual bank transfer or contact support.';
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $msg]);
                exit;
            }
            $_SESSION['flash_error'] = $msg;
            header('Location: /wallet/topup');
            exit;
        }

        // Idempotency check: Reuse existing active pending order within 60 seconds to avoid duplicates on double-click
        $recentStmt = $db->prepare("
            SELECT * FROM topup_orders 
            WHERE user_id = ? AND amount = ? AND status = 'PENDING' AND expires_at > NOW() AND created_at >= DATE_SUB(NOW(), INTERVAL 60 SECOND)
            ORDER BY id DESC LIMIT 1
        ");
        $recentStmt->execute([$userId, $amount]);
        $recentPending = $recentStmt->fetch();

        if ($recentPending && !empty($recentPending['qr_image_url'])) {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'order_id' => $recentPending['order_id'],
                    'amount' => (float)$recentPending['amount'],
                    'qr_image_url' => $recentPending['qr_image_url'],
                    'payment_url' => $recentPending['payment_url'] ?? null
                ]);
                exit;
            }
            header('Location: /wallet/topup?open_order=' . urlencode($recentPending['order_id']));
            exit;
        }

        // Generate unique clean alphanumeric order ID (No hyphens, strictly compatible with ALLAPI)
        $orderId = 'ORD' . date('YmdHis') . strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));

        // Create pending order row in database BEFORE calling provider
        $ins = $db->prepare("
            INSERT INTO topup_orders (
                order_id, user_id, amount, status, provider,
                expires_at, created_at
            ) VALUES (
                ?, ?, ?, 'PENDING', 'ALL_API',
                DATE_ADD(NOW(), INTERVAL 15 MINUTE), NOW()
            )
        ");
        $ins->execute([$orderId, $userId, $amount]);
        $orderDbId = (int)$db->lastInsertId();

        // Call ALL API QR generation service
        $customer = [
            'name' => $user['name'],
            'mobile' => $user['mobile'],
            'email' => $user['email']
        ];

        $qrResult = $this->provider->createQrPayment($orderId, $amount, $customer);

        // Clearly handle QR provider failure - never treat unsuccessful QR as valid payment flow
        if (empty($qrResult['success']) || empty($qrResult['qr_image_url'])) {
            $upd = $db->prepare("
                UPDATE topup_orders
                SET status = 'FAILED',
                    raw_init_response = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $upd->execute([
                json_encode($qrResult['raw'] ?? ['error' => $qrResult['message'] ?? 'Provider failed to generate QR']),
                $orderDbId
            ]);

            AuditService::log('TOPUP_INIT_FAILED', 'TOPUP_ORDER', (string)$orderDbId, [
                'order_id' => $orderId,
                'amount' => $amount,
                'error' => $qrResult['message'] ?? 'Provider error'
            ], $userId, $user['role']);

            $errMsg = 'Unable to generate UPI payment QR: ' . ($qrResult['message'] ?? 'Service temporarily unavailable') . '. Please try again.';
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $errMsg]);
                exit;
            }
            $_SESSION['flash_error'] = $errMsg;
            header('Location: /wallet/topup');
            exit;
        }

        // Update order with confirmed QR details
        $upd = $db->prepare("
            UPDATE topup_orders
            SET provider_txn_id = ?,
                qr_image_url = ?,
                payment_url = ?,
                raw_init_response = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $upd->execute([
            $qrResult['provider_txn_id'] ?? null,
            $qrResult['qr_image_url'],
            $qrResult['payment_url'] ?? null,
            json_encode($qrResult['raw'] ?? []),
            $orderDbId
        ]);

        AuditService::log('TOPUP_INITIATED', 'TOPUP_ORDER', (string)$orderDbId, ['order_id' => $orderId, 'amount' => $amount], $userId, $user['role']);

        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'order_id' => $orderId,
                'amount' => $amount,
                'qr_image_url' => $qrResult['qr_image_url'],
                'fixed_uri' => $qrResult['fixed_uri'] ?? null,
                'payment_url' => $qrResult['payment_url'] ?? null
            ]);
            exit;
        }

        header('Location: /wallet/topup?open_order=' . urlencode($orderId));
        exit;
    }

    public function showPaymentQr(): void
    {
        if (!AuthService::check()) {
            header('Location: /login');
            exit;
        }

        $orderId = trim($_GET['order_id'] ?? '');
        $userId = AuthService::id();
        $db = Database::getConnection();

        $stmt = $db->prepare("SELECT * FROM topup_orders WHERE order_id = ? AND user_id = ?");
        $stmt->execute([$orderId, $userId]);
        $order = $stmt->fetch();

        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
               || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
               || isset($_GET['ajax']);

        if (!$order) {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Payment order not found.']);
                exit;
            }
            $_SESSION['flash_error'] = 'Payment order not found.';
            header('Location: /wallet/topup');
            exit;
        }

        // If order failed or QR could not be generated, do not display active payment flow
        if ($order['status'] === 'FAILED' || empty($order['qr_image_url'])) {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'This payment order failed to generate a valid UPI QR. Please initiate a new recharge.']);
                exit;
            }
            $_SESSION['flash_error'] = 'This payment order failed to generate a valid UPI QR. Please initiate a new recharge.';
            header('Location: /wallet/topup');
            exit;
        }

        // Live check if already paid before serving QR
        if ($order['status'] === 'PENDING') {
            try {
                $statusCheck = $this->provider->checkPaymentStatus($order['order_id']);
                if (!empty($statusCheck['status']) && $statusCheck['status'] === 'PAID') {
                    $this->executeOrderCredit(
                        (int)$order['id'],
                        $statusCheck['provider_txn_id'] ?? null,
                        $statusCheck['utr'] ?? null,
                        $statusCheck['raw'] ?? null,
                        isset($statusCheck['amount']) ? (float)$statusCheck['amount'] : null,
                        'INR',
                        $order['order_id']
                    );
                    $order['status'] = 'PAID';
                }
            } catch (\Throwable $e) {}
        }

        // If order already paid, redirect or return paid status
        if ($order['status'] === 'PAID') {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'status' => 'PAID',
                    'credited' => true,
                    'amount' => (float)$order['amount'],
                    'message' => 'Payment confirmed and credited to wallet.'
                ]);
                exit;
            }
            $_SESSION['flash_info'] = 'This top-up order is already completed and credited.';
            header('Location: /wallet/topup');
            exit;
        }

        if ($isAjax) {
            $rawInit = !empty($order['raw_init_response']) ? json_decode($order['raw_init_response'], true) : [];
            $results = $rawInit['results'] ?? [];
            $upiIntent = is_array($results['upi_intent'] ?? null) ? $results['upi_intent'] : [];
            $rawUpiUri = $upiIntent['bhim'] ?? $upiIntent['phonepe'] ?? '';
            $fixedUri = '';
            if (!empty($rawUpiUri)) {
                $fixedUri = preg_replace('/&mam=[0-9.]+/i', '', $rawUpiUri);
                $fixedUri = preg_replace('/&am=[0-9.]+/i', '&am=' . number_format((float)$order['amount'], 2, '.', ''), $fixedUri);
                if (strpos($fixedUri, 'cu=') === false) {
                    $fixedUri .= '&cu=INR';
                }
            }

            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'order_id' => $order['order_id'],
                'amount' => (float)$order['amount'],
                'status' => $order['status'],
                'qr_image_url' => $order['qr_image_url'],
                'fixed_uri' => $fixedUri,
                'payment_url' => $order['payment_url'] ?? null
            ]);
            exit;
        }

        header('Location: /wallet/topup?open_order=' . urlencode($order['order_id']));
        exit;
    }

    /**
     * Polling AJAX endpoint to check live payment status
     */
    public function checkStatus(): void
    {
        header('Content-Type: application/json');

        if (!AuthService::check()) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit;
        }

        $orderId = trim($_GET['order_id'] ?? $_POST['order_id'] ?? '');
        $userId = AuthService::id();
        $db = Database::getConnection();

        $cleanId = preg_replace('/[^a-zA-Z0-9]/', '', $orderId);
        $stmt = $db->prepare("SELECT * FROM topup_orders WHERE order_id = ? OR REPLACE(order_id, '-', '') = ?");
        $stmt->execute([$orderId, $cleanId]);
        $order = $stmt->fetch();

        if (!$order) {
            echo json_encode(['success' => false, 'message' => 'Order not found']);
            exit;
        }

        // Ownership check
        if (!AuthService::isAdmin() && (int)$order['user_id'] !== $userId) {
            echo json_encode(['success' => false, 'message' => 'Forbidden']);
            exit;
        }

        // Release session lock before calling external cURL so parallel requests don't hang!
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        // If already marked terminal status
        if ($order['status'] === 'PAID') {
            echo json_encode([
                'success' => true,
                'status' => 'PAID',
                'credited' => true,
                'amount' => (float)$order['amount'],
                'new_balance' => WalletService::getBalance((int)$order['user_id']),
                'message' => 'Payment confirmed and credited to wallet.'
            ]);
            exit;
        }

        if ($order['status'] === 'FAILED' || $order['status'] === 'EXPIRED') {
            echo json_encode([
                'success' => true,
                'status' => $order['status'],
                'credited' => false,
                'amount' => (float)$order['amount'],
                'message' => 'Payment order is ' . $order['status'] . '.'
            ]);
            exit;
        }

        // Check local expiration
        if (!empty($order['expires_at']) && strtotime($order['expires_at']) < time()) {
            $db->prepare("UPDATE topup_orders SET status = 'EXPIRED', updated_at = NOW() WHERE id = ? AND status = 'PENDING'")->execute([(int)$order['id']]);
            echo json_encode([
                'success' => true,
                'status' => 'EXPIRED',
                'credited' => false,
                'amount' => (float)$order['amount'],
                'message' => 'Payment order has expired. Please initiate a new recharge.'
            ]);
            exit;
        }

        // Check live status with ALL API provider
        $statusCheck = $this->provider->checkPaymentStatus($orderId);

        if ($statusCheck['status'] === 'PAID') {
            try {
                // Atomically credit wallet
                $creditRes = $this->executeOrderCredit($order['id'], $statusCheck['provider_txn_id'], $statusCheck['utr'], $statusCheck['raw']);

                echo json_encode([
                    'success' => true,
                    'status' => 'PAID',
                    'credited' => true,
                    'amount' => (float)$order['amount'],
                    'new_balance' => $creditRes['new_balance'] ?? WalletService::getBalance((int)$order['user_id']),
                    'message' => 'Payment verified! Wallet has been credited.'
                ]);
                exit;
            } catch (\Throwable $e) {
                error_log("Credit execution failed for order {$orderId}: " . $e->getMessage());
                echo json_encode([
                    'success' => false,
                    'status' => 'PENDING',
                    'credited' => false,
                    'message' => 'Payment detected, processing credit: ' . $e->getMessage()
                ]);
                exit;
            }
        }

        if ($statusCheck['status'] === 'FAILED' || $statusCheck['status'] === 'EXPIRED') {
            $updTerm = $db->prepare("UPDATE topup_orders SET status = ?, raw_status_response = ?, updated_at = NOW() WHERE id = ? AND status = 'PENDING'");
            $updTerm->execute([
                $statusCheck['status'],
                json_encode($statusCheck['raw'] ?? []),
                (int)$order['id']
            ]);
        }

        echo json_encode([
            'success' => true,
            'status' => $statusCheck['status'],
            'credited' => false,
            'message' => $statusCheck['message']
        ]);
        exit;
    }

    /**
     * Non-blocking background reconciliation for recent pending orders
     */
    public function reconcilePending(): void
    {
        header('Content-Type: application/json');

        if (!AuthService::check()) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit;
        }

        $userId = AuthService::id();
        $db = Database::getConnection();

        // Release session lock immediately so other requests are not blocked
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $reconciled = 0;
        try {
            $stmt = $db->prepare("
                SELECT id, order_id, amount FROM topup_orders 
                WHERE user_id = ? AND status = 'PENDING' AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
                ORDER BY id DESC LIMIT 2
            ");
            $stmt->execute([$userId]);
            $pending = $stmt->fetchAll();

            foreach ($pending as $po) {
                $statusCheck = $this->provider->checkPaymentStatus($po['order_id']);
                if (!empty($statusCheck['status']) && $statusCheck['status'] === 'PAID') {
                    $this->executeOrderCredit((int)$po['id'], $statusCheck['provider_txn_id'] ?? null, $statusCheck['utr'] ?? null, $statusCheck['raw'] ?? null);
                    $reconciled++;
                }
            }
        } catch (\Throwable $e) {}

        echo json_encode([
            'success' => true,
            'reconciled' => $reconciled,
            'new_balance' => $reconciled > 0 ? WalletService::getBalance($userId) : null
        ]);
        exit;
    }

    /**
     * Webhook receiver for server-to-server callback
     */
    public function webhook(): void
    {
        header('Content-Type: application/json');

        $rawInput = file_get_contents('php://input');
        $payload = json_decode($rawInput, true) ?: $_POST;

        $sig = $_SERVER['HTTP_X_ALLAPI_SIGNATURE'] ?? $_SERVER['HTTP_X_SIGNATURE'] ?? $_SERVER['HTTP_SIGNATURE'] ?? null;
        $validation = $this->provider->validateWebhook($payload, $sig, $rawInput);

        if (!$validation['is_valid']) {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'message' => $validation['error'] ?? 'Invalid webhook signature or payload'
            ]);
            exit;
        }

        $orderId = $validation['order_id'];
        $db = Database::getConnection();

        // Verify order exists in system before any credit or event logging
        $stmt = $db->prepare("SELECT id, user_id, amount, status FROM topup_orders WHERE order_id = ?");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();

        if (!$order) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Order not found']);
            exit;
        }

        // Validate signed payload amount is present and matches the order amount
        $payloadAmt = $validation['amount'] ?? null;
        if ($payloadAmt === null || !is_numeric($payloadAmt)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Transaction amount missing in signed webhook payload']);
            exit;
        }

        if (abs((float)$payloadAmt - (float)$order['amount']) > 0.01) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Transaction amount mismatch with order']);
            exit;
        }

        // Idempotent webhook event logging
        $insEvent = $db->prepare("
            INSERT INTO payment_provider_events (provider, event_type, order_id, event_id, payload, ip_address, created_at)
            VALUES ('ALL_API', 'WEBHOOK', ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE processed = 1
        ");
        $insEvent->execute([
            $orderId,
            $validation['event_id'],
            $rawInput,
            $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
        ]);

        // Credit wallet only after verification and if order is not already paid
        if ($validation['status'] === 'PAID') {
            $this->executeOrderCredit($order['id'], $validation['provider_txn_id'], $validation['utr'], $payload);
        }

        echo json_encode(['success' => true, 'message' => 'Webhook verified and processed successfully']);
        exit;
    }

    /**
     * Atomically mark topup order paid and credit wallet exactly once
     */
    public function executeOrderCredit(int $orderDbId, ?string $providerTxnId, ?string $utr, ?array $rawPayload): array
    {
        $db = Database::getConnection();
        $db->beginTransaction();

        try {
            $stmt = $db->prepare("SELECT * FROM topup_orders WHERE id = ? FOR UPDATE");
            $stmt->execute([$orderDbId]);
            $order = $stmt->fetch();

            if (!$order) {
                throw new Exception("Order not found.");
            }

            if ($order['status'] === 'PAID' || !empty($order['is_credited'])) {
                $db->commit();
                return ['success' => true, 'already_credited' => true];
            }

            $userId = (int)$order['user_id'];
            $amount = (float)$order['amount'];
            $orderId = $order['order_id'];

            // Credit wallet using WalletService with unique idempotency key
            $idempotencyKey = "TOPUP_ORDER_{$orderDbId}_{$orderId}";
            $creditResult = WalletService::credit(
                $userId,
                $amount,
                'TOPUP_CREDIT',
                'TOPUP_ORDER',
                $orderId,
                $idempotencyKey,
                "Wallet recharge via UPI Scan & Pay (Order: {$orderId})",
                "Wallet recharge via UPI Scan & Pay (Order: {$orderId})",
                "UPI Provider Txn: " . ($providerTxnId ?: 'N/A') . " | UTR: " . ($utr ?: 'N/A'),
                $userId
            );

            // Ensure is_credited column exists defensively
            try {
                $hasCol = $db->query("SHOW COLUMNS FROM `topup_orders` LIKE 'is_credited'")->fetch();
                if (!$hasCol) {
                    $db->exec("ALTER TABLE `topup_orders` ADD COLUMN `is_credited` TINYINT(1) NOT NULL DEFAULT 0 AFTER `status`");
                }
            } catch (\Throwable $e) {}

            // Update topup order atomically
            $upd = $db->prepare("
                UPDATE topup_orders
                SET status = 'PAID',
                    is_credited = 1,
                    provider_txn_id = COALESCE(?, provider_txn_id),
                    provider_utr = COALESCE(?, provider_utr),
                    credited_at = NOW(),
                    raw_status_response = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $upd->execute([
                $providerTxnId,
                $utr,
                $rawPayload ? json_encode($rawPayload) : null,
                $orderDbId
            ]);

            AuditService::log('TOPUP_PAID_CREDITED', 'TOPUP_ORDER', (string)$orderDbId, [
                'order_id' => $orderId,
                'amount' => $amount,
                'provider_txn_id' => $providerTxnId,
                'utr' => $utr
            ], $userId);

            // In-app notification to user
            NotificationService::create(
                $userId,
                'Payment Credited',
                "₹" . number_format($amount, 2) . " has been credited to your wallet (Order: {$orderId}).",
                'SUCCESS',
                '/wallet/passbook'
            );

            $db->commit();

            return [
                'success' => true,
                'new_balance' => $creditResult['new_balance'] ?? 0.00
            ];
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public function exportPassbookCsv(): void
    {
        if (!AuthService::check()) {
            header('Location: /login');
            exit;
        }

        $userId = AuthService::id();
        $db = Database::getConnection();

        $stmt = $db->prepare("
            SELECT txn_ref, created_at, type, direction, amount, balance_before, balance_after,
                   description_en, remarks, status
            FROM wallet_transactions
            WHERE user_id = ?
            ORDER BY id DESC
        ");
        $stmt->execute([$userId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $headers = [
            'Transaction ID', 'Date & Time', 'Transaction Type', 'Direction',
            'Amount (INR)', 'Previous Balance (INR)', 'New Balance (INR)',
            'Description', 'Remarks', 'Status'
        ];

        CsvService::streamCsv('welcome_bihar_passbook_' . date('Ymd_His') . '.csv', $headers, $rows);
    }
}
