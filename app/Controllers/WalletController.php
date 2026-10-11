<?php
namespace App\Controllers;

use App\Config\Database;
use App\Services\AuthService;
use App\Services\WalletService;
use App\Services\AllApiProvider;
use App\Services\AuditService;
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
        $balance = WalletService::getBalance($userId);
        $providerInfo = $this->provider->getProviderInfo();

        // Recent topup orders
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM topup_orders WHERE user_id = ? ORDER BY id DESC LIMIT 5");
        $stmt->execute([$userId]);
        $recentOrders = $stmt->fetchAll();

        require dirname(__DIR__) . '/Views/user/topup.php';
    }

    public function initiateTopup(): void
    {
        if (!AuthService::check()) {
            header('Location: /login');
            exit;
        }

        if (!AuthService::validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $_SESSION['flash_error'] = 'Invalid CSRF token.';
            header('Location: /wallet/topup');
            exit;
        }

        $userId = AuthService::id();
        $user = AuthService::user();
        $amount = round((float)($_POST['amount'] ?? 0), 2);

        if ($amount < 50 || $amount > 25000) {
            $_SESSION['flash_error'] = __('min_amount_note', 'Minimum top-up amount is ₹50 and maximum is ₹25,000.');
            header('Location: /wallet/topup');
            exit;
        }

        $db = Database::getConnection();
        // Generate unique internal order ID
        $orderId = 'ORD-' . date('YmdHis') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));

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

        // Update order with QR details
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
            $qrResult['qr_image_url'] ?? null,
            $qrResult['payment_url'] ?? null,
            json_encode($qrResult['raw'] ?? []),
            $orderDbId
        ]);

        AuditService::log('TOPUP_INITIATED', 'TOPUP_ORDER', (string)$orderDbId, ['order_id' => $orderId, 'amount' => $amount], $userId, $user['role']);

        header('Location: /wallet/topup/pay?order_id=' . $orderId);
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

        if (!$order) {
            $_SESSION['flash_error'] = 'Payment order not found.';
            header('Location: /wallet/topup');
            exit;
        }

        $providerInfo = $this->provider->getProviderInfo();

        require dirname(__DIR__) . '/Views/user/payment_qr.php';
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

        $stmt = $db->prepare("SELECT * FROM topup_orders WHERE order_id = ?");
        $stmt->execute([$orderId]);
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

        // If already marked paid
        if ($order['status'] === 'PAID') {
            echo json_encode([
                'success' => true,
                'status' => 'PAID',
                'credited' => true,
                'message' => 'Payment confirmed and credited to wallet.'
            ]);
            exit;
        }

        // Check live status with ALL API provider
        $statusCheck = $this->provider->checkPaymentStatus($orderId);

        if ($statusCheck['status'] === 'PAID') {
            // Atomically credit wallet
            $creditRes = $this->executeOrderCredit($order['id'], $statusCheck['provider_txn_id'], $statusCheck['utr'], $statusCheck['raw']);

            echo json_encode([
                'success' => true,
                'status' => 'PAID',
                'credited' => true,
                'new_balance' => $creditRes['new_balance'] ?? null,
                'message' => 'Payment verified! Wallet has been credited.'
            ]);
            exit;
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
     * Webhook receiver for server-to-server callback
     */
    public function webhook(): void
    {
        header('Content-Type: application/json');

        $rawInput = file_get_contents('php://input');
        $payload = json_decode($rawInput, true) ?: $_POST;

        $sig = $_SERVER['HTTP_X_ALLAPI_SIGNATURE'] ?? $_SERVER['HTTP_SIGNATURE'] ?? null;
        $validation = $this->provider->validateWebhook($payload, $sig);

        if (!$validation['is_valid']) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid webhook payload']);
            exit;
        }

        $orderId = $validation['order_id'];
        $db = Database::getConnection();

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

        // Find order
        $stmt = $db->prepare("SELECT id, status FROM topup_orders WHERE order_id = ?");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();

        if ($order && $validation['status'] === 'PAID') {
            $this->executeOrderCredit($order['id'], $validation['provider_txn_id'], $validation['utr'], $payload);
        }

        echo json_encode(['success' => true, 'message' => 'Webhook processed successfully']);
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

            if ($order['status'] === 'PAID') {
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

            // Update topup order
            $upd = $db->prepare("
                UPDATE topup_orders
                SET status = 'PAID',
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
