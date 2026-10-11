<?php
namespace App\Controllers;

use App\Config\Database;
use App\Services\AuthService;
use App\Services\AllApiProvider;
use App\Services\AuditService;
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
            $walletCtrl->executeOrderCredit($order['id'], $statusRes['provider_txn_id'], $statusRes['utr'], $statusRes['raw']);
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

        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM topup_orders WHERE order_id = ?");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();

        if (!$order) {
            $_SESSION['flash_error'] = 'Order not found.';
            header('Location: /admin/reconciliation');
            exit;
        }

        if (empty($remarks)) {
            $_SESSION['flash_error'] = 'Mandatory review remarks are required for audit trail.';
            header('Location: /admin/reconciliation');
            exit;
        }

        if ($action === 'APPROVE_CREDIT') {
            if ($order['status'] === 'PAID') {
                $_SESSION['flash_error'] = 'Order is already marked PAID.';
                header('Location: /admin/reconciliation');
                exit;
            }

            $walletCtrl = new WalletController();
            $walletCtrl->executeOrderCredit($order['id'], 'MANUAL_VERIFIED', $utr, ['manual_by' => AuthService::id(), 'remarks' => $remarks]);

            $upd = $db->prepare("
                UPDATE topup_orders
                SET manual_verified_by = ?,
                    manual_verified_at = NOW(),
                    manual_remarks = ?
                WHERE id = ?
            ");
            $upd->execute([AuthService::id(), $remarks, $order['id']]);

            AuditService::log('TOPUP_MANUAL_APPROVED', 'TOPUP_ORDER', (string)$order['id'], ['order_id' => $orderId, 'utr' => $utr, 'remarks' => $remarks]);
            $_SESSION['flash_success'] = "Order {$orderId} manually confirmed and wallet credited.";
        } elseif ($action === 'MARK_FAILED') {
            $upd = $db->prepare("
                UPDATE topup_orders
                SET status = 'FAILED',
                    manual_verified_by = ?,
                    manual_verified_at = NOW(),
                    manual_remarks = ?
                WHERE id = ?
            ");
            $upd->execute([AuthService::id(), $remarks, $order['id']]);

            AuditService::log('TOPUP_MANUAL_FAILED', 'TOPUP_ORDER', (string)$order['id'], ['order_id' => $orderId, 'remarks' => $remarks]);
            $_SESSION['flash_success'] = "Order {$orderId} marked FAILED.";
        }

        header('Location: /admin/reconciliation');
        exit;
    }
}
