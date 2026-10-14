<?php
namespace App\Controllers;

use App\Config\Database;
use App\Services\AuthService;
use App\Services\AllApiProvider;
use App\Services\AuditService;
use App\Services\NotificationService;
use App\Services\WalletService;
use Exception;
use PDO;

class ReconciliationController
{
    private AllApiProvider $provider;

    public function __construct()
    {
        if (!AuthService::isAdmin()) {
            header('Location: /login');
            exit;
        }
        $this->provider = new AllApiProvider();
    }

    public function index(): void
    {
        $db = Database::getConnection();

        $status = $_GET['status'] ?? '';
        $search = trim($_GET['search'] ?? '');
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = 25;
        $offset = ($page - 1) * $limit;

        $where = ["1=1"];
        $params = [];

        if (!empty($status)) {
            $where[] = "t.status = :status";
            $params[':status'] = $status;
        }

        if (!empty($search)) {
            $where[] = "(t.order_id LIKE :search OR t.provider_txn_id LIKE :search OR t.provider_utr LIKE :search OR u.name LIKE :search OR u.mobile LIKE :search)";
            $params[':search'] = "%{$search}%";
        }

        $whereClause = implode(' AND ', $where);

        $cntStmt = $db->prepare("SELECT COUNT(*) FROM topup_orders t JOIN users u ON t.user_id = u.id WHERE {$whereClause}");
        $cntStmt->execute($params);
        $totalRecords = (int)$cntStmt->fetchColumn();
        $totalPages = ceil($totalRecords / $limit);

        $sql = "
            SELECT t.*, u.name as user_name, u.mobile as user_mobile, u.email as user_email, u.center_name,
                   w.balance as current_wallet_balance
            FROM topup_orders t
            JOIN users u ON t.user_id = u.id
            LEFT JOIN wallets w ON u.id = w.user_id
            WHERE {$whereClause}
            ORDER BY t.id DESC
            LIMIT {$limit} OFFSET {$offset}
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $orders = $stmt->fetchAll();

        // Status summaries
        $summary = $db->query("
            SELECT
                COUNT(*) as total,
                SUM(CASE WHEN status = 'PENDING' THEN 1 ELSE 0 END) as pending_cnt,
                SUM(CASE WHEN status = 'PAID' THEN 1 ELSE 0 END) as paid_cnt,
                SUM(CASE WHEN status = 'FAILED' THEN 1 ELSE 0 END) as failed_cnt,
                COALESCE(SUM(CASE WHEN status = 'PAID' THEN amount ELSE 0 END), 0) as paid_amount
            FROM topup_orders
        ")->fetch();

        $providerInfo = $this->provider->getProviderInfo();

        require dirname(__DIR__) . '/Views/admin/reconciliation.php';
    }

    /**
     * Recheck live status of an order with ALL API
     */
    public function recheckStatus(): void
    {
        if (!AuthService::validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $_SESSION['flash_error'] = 'Invalid CSRF token.';
            header('Location: /admin/reconciliation');
            exit;
        }

        $orderId = trim($_POST['order_id'] ?? '');
        $db = Database::getConnection();

        $stmt = $db->prepare("SELECT * FROM topup_orders WHERE order_id = ?");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();

        if (!$order) {
            $_SESSION['flash_error'] = 'Order not found.';
            header('Location: /admin/reconciliation');
            exit;
        }

        // Live check with provider
        $statusRes = $this->provider->checkPaymentStatus($orderId);

        if ($statusRes['status'] === 'PAID') {
            $walletCtrl = new WalletController();
            $walletCtrl->executeOrderCredit(
                (int)$order['id'],
                $statusRes['provider_txn_id'] ?? null,
                $statusRes['utr'] ?? null,
                $statusRes['raw'] ?? null,
                isset($statusRes['amount']) ? (float)$statusRes['amount'] : null,
                'INR',
                $order['order_id']
            );
            $_SESSION['flash_success'] = "Payment verified with ALL API provider! Order marked PAID and wallet credited.";
        } else {
            $_SESSION['flash_info'] = "Status from provider: " . $statusRes['status'] . " (" . $statusRes['message'] . ")";
        }

        header('Location: /admin/reconciliation');
        exit;
    }

    /**
     * Authorized manual reconciliation / verification
     */
    public function manualVerify(): void
    {
        if (!AuthService::validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $_SESSION['flash_error'] = 'Invalid CSRF token.';
            header('Location: /admin/reconciliation');
            exit;
        }

        $orderId = trim($_POST['order_id'] ?? '');
        $action = trim($_POST['action'] ?? '');
        $utr = trim($_POST['utr'] ?? '');
        $remarks = trim($_POST['remarks'] ?? '');

        $allowedActions = ['APPROVE_CREDIT', 'MARK_FAILED'];
        if (!in_array($action, $allowedActions, true)) {
            $_SESSION['flash_error'] = 'Invalid reconciliation action requested.';
            header('Location: /admin/reconciliation');
            exit;
        }

        if (empty($remarks)) {
            $_SESSION['flash_error'] = 'Mandatory review remarks are required for audit trail.';
            header('Location: /admin/reconciliation');
            exit;
        }

        $db = Database::getConnection();
        $db->beginTransaction();

        try {
            $stmt = $db->prepare("SELECT * FROM topup_orders WHERE order_id = ? FOR UPDATE");
            $stmt->execute([$orderId]);
            $order = $stmt->fetch();

            if (!$order) {
                throw new Exception('Order not found.');
            }

            $currentStatus = $order['status'];

            if ($action === 'APPROVE_CREDIT') {
                if ($currentStatus === 'PAID' || !empty($order['is_credited'])) {
                    throw new Exception('Order is already marked PAID and credited.');
                }
                if ($currentStatus === 'FAILED' || $currentStatus === 'EXPIRED') {
                    throw new Exception("Cannot approve a terminal order (Current status: {$currentStatus}).");
                }

                // Check for duplicate UTR across existing paid orders
                if (!empty($utr)) {
                    $dupCheck = $db->prepare("SELECT id, order_id FROM topup_orders WHERE provider_utr = ? AND status = 'PAID' AND id != ?");
                    $dupCheck->execute([$utr, $order['id']]);
                    $dup = $dupCheck->fetch();
                    if ($dup) {
                        throw new Exception("UTR '{$utr}' has already been credited for Order {$dup['order_id']}. Duplicate approval rejected.");
                    }
                }

                $userId = (int)$order['user_id'];
                $amount = (float)$order['amount'];
                $idempotencyKey = "TOPUP_ORDER_{$order['id']}_{$orderId}";

                // Credit wallet inside current transaction
                WalletService::credit(
                    $userId,
                    $amount,
                    'TOPUP_CREDIT',
                    'TOPUP_ORDER',
                    $orderId,
                    $idempotencyKey,
                    "Wallet recharge via UPI Scan & Pay (Order: {$orderId})",
                    "Wallet recharge via UPI Scan & Pay (Order: {$orderId})",
                    "Manual reconciliation approval | UTR: " . ($utr ?: 'N/A') . " | Remarks: {$remarks}",
                    AuthService::id()
                );

                $upd = $db->prepare("
                    UPDATE topup_orders
                    SET status = 'PAID',
                        is_credited = 1,
                        provider_txn_id = COALESCE(provider_txn_id, 'MANUAL_VERIFIED'),
                        provider_utr = COALESCE(?, provider_utr),
                        credited_at = NOW(),
                        manual_verified_by = ?,
                        manual_verified_at = NOW(),
                        manual_remarks = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $upd->execute([$utr ?: null, AuthService::id(), $remarks, $order['id']]);

                AuditService::log('TOPUP_MANUAL_APPROVED', 'TOPUP_ORDER', (string)$order['id'], [
                    'order_id' => $orderId,
                    'amount' => $amount,
                    'utr' => $utr,
                    'remarks' => $remarks
                ], AuthService::id(), 'ADMIN');

                NotificationService::create(
                    $userId,
                    'Payment Approved & Credited',
                    "₹" . number_format($amount, 2) . " has been credited to your wallet via manual reconciliation (Order: {$orderId}).",
                    'SUCCESS',
                    '/wallet/passbook'
                );

                $db->commit();
                $_SESSION['flash_success'] = "Order {$orderId} manually confirmed and wallet credited.";
            } elseif ($action === 'MARK_FAILED') {
                // Prevent marking paid or already terminal orders as failed
                if ($currentStatus === 'PAID') {
                    throw new Exception('Cannot mark a PAID order as failed. Wallet credit was already completed.');
                }
                if ($currentStatus === 'FAILED') {
                    throw new Exception('Order is already in FAILED status.');
                }

                $upd = $db->prepare("
                    UPDATE topup_orders
                    SET status = 'FAILED',
                        manual_verified_by = ?,
                        manual_verified_at = NOW(),
                        manual_remarks = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $upd->execute([AuthService::id(), $remarks, $order['id']]);

                AuditService::log('TOPUP_MANUAL_FAILED', 'TOPUP_ORDER', (string)$order['id'], [
                    'order_id' => $orderId,
                    'remarks' => $remarks
                ], AuthService::id(), 'ADMIN');

                $db->commit();
                $_SESSION['flash_success'] = "Order {$orderId} marked FAILED.";
            }
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $_SESSION['flash_error'] = $e->getMessage();
        }

        header('Location: /admin/reconciliation');
        exit;
    }
}
