<?php
namespace App\Controllers;

use App\Config\Database;
use App\Services\AuthService;
use App\Services\WalletService;
use App\Services\ApplicationService;
use App\Services\AuditService;
use App\Services\CsvService;

class AdminController
{
    public function __construct()
    {
        if (!AuthService::check()) {
            header('Location: /login');
            exit;
        }

        if (!AuthService::isAdmin()) {
            $_SESSION['flash_error'] = 'Access denied. Administrative privilege required.';
            header('Location: /user/dashboard');
            exit;
        }
    }

    public function dashboard(): void
    {
        $db = Database::getConnection();

        // 1. Metric Cards
        $userCount = (int)$db->query("SELECT COUNT(*) FROM users WHERE role = 'USER'")->fetchColumn();
        $distCount = (int)$db->query("SELECT COUNT(*) FROM users WHERE role = 'DISTRIBUTOR'")->fetchColumn();

        $appStats = $db->query("
            SELECT
                COUNT(*) as total,
                SUM(CASE WHEN status = 'PENDING' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'UNDER_REVIEW' THEN 1 ELSE 0 END) as under_review,
                SUM(CASE WHEN status = 'APPROVED' THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN status = 'REJECTED' THEN 1 ELSE 0 END) as rejected,
                COALESCE(SUM(fee_debited), 0) as total_fees,
                COALESCE(SUM(commission_credited), 0) as total_commissions
            FROM applications
        ")->fetch();

        $topupStats = $db->query("
            SELECT
                SUM(CASE WHEN status = 'PENDING' THEN 1 ELSE 0 END) as pending_count,
                SUM(CASE WHEN status = 'PAID' THEN 1 ELSE 0 END) as paid_count,
                COALESCE(SUM(CASE WHEN status = 'PAID' THEN amount ELSE 0 END), 0) as total_topup_amount
            FROM topup_orders
        ")->fetch();

        $walletStats = $db->query("
            SELECT
                COALESCE(SUM(balance), 0) as total_system_balance,
                COALESCE(SUM(total_credited), 0) as total_credited,
                COALESCE(SUM(total_debited), 0) as total_debited
            FROM wallets
        ")->fetch();

        // 2. Recent Applications Pending Admin Action
        $pendingApps = $db->query("
            SELECT a.id, a.app_ref, a.applicant_name, a.applicant_mobile, a.status, a.fee_debited, a.created_at,
                   u.name as operator_name,
                   dt.name_en as dt_name_en, dt.name_hi as dt_name_hi,
                   d.name as district_name, b.name as block_name
            FROM applications a
            JOIN users u ON a.user_id = u.id
            JOIN data_types dt ON a.data_type_id = dt.id
            JOIN districts d ON a.district_id = d.id
            JOIN blocks b ON a.block_id = b.id
            ORDER BY a.id DESC
            LIMIT 8
        ")->fetchAll();

        // 3. Recent Audit Logs
        $recentAudits = $db->query("
            SELECT al.*, u.name as actor_name
            FROM audit_logs al
            LEFT JOIN users u ON al.actor_user_id = u.id
            ORDER BY al.id DESC
            LIMIT 6
        ")->fetchAll();

        require dirname(__DIR__) . '/Views/admin/dashboard.php';
    }

    public function users(): void
    {
        $db = Database::getConnection();

        $role = $_GET['role'] ?? '';
        $status = $_GET['status'] ?? '';
        $search = trim($_GET['search'] ?? '');

        $where = ["1=1"];
        $params = [];

        if (!empty($role)) {
            $where[] = "u.role = :role";
            $params[':role'] = $role;
        }

        if (!empty($status)) {
            $where[] = "u.status = :status";
            $params[':status'] = $status;
        }

        if (!empty($search)) {
            $where[] = "(u.name LIKE :search OR u.mobile LIKE :search OR u.email LIKE :search OR u.center_name LIKE :search)";
            $params[':search'] = "%{$search}%";
        }

        $whereClause = implode(' AND ', $where);

        $sql = "
            SELECT u.id, u.uuid, u.role, u.name, u.mobile, u.email, u.status, u.center_name,
                   u.assigned_all_districts, u.created_at,
                   p.name as distributor_name,
                   w.balance,
                   (SELECT COUNT(*) FROM applications WHERE user_id = u.id) as app_count
            FROM users u
            LEFT JOIN users p ON u.parent_distributor_id = p.id
            LEFT JOIN wallets w ON u.id = w.user_id
            WHERE {$whereClause}
            ORDER BY u.id DESC
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $users = $stmt->fetchAll();

        $distributors = $db->query("SELECT id, name FROM users WHERE role = 'DISTRIBUTOR' AND status = 'ACTIVE' ORDER BY name ASC")->fetchAll();
        $districts = $db->query("SELECT id, name, name_hi FROM districts WHERE is_active = 1 ORDER BY name ASC")->fetchAll();

        require dirname(__DIR__) . '/Views/admin/users.php';
    }

    public function userDetail(): void
    {
        $userId = (int)($_GET['id'] ?? 0);
        $db = Database::getConnection();

        $stmt = $db->prepare("
            SELECT u.*, p.name as distributor_name, w.balance, w.total_credited, w.total_debited
            FROM users u
            LEFT JOIN users p ON u.parent_distributor_id = p.id
            LEFT JOIN wallets w ON u.id = w.user_id
            WHERE u.id = ?
        ");
        $stmt->execute([$userId]);
        $targetUser = $stmt->fetch();

        if (!$targetUser) {
            $_SESSION['flash_error'] = 'User not found.';
            header('Location: /admin/users');
            exit;
        }

        // Permitted locations
        $permStmt = $db->prepare("
            SELECT lp.id, d.name as district_name, d.id as district_id, b.name as block_name, b.id as block_id
            FROM location_permissions lp
            JOIN districts d ON lp.district_id = d.id
            LEFT JOIN blocks b ON lp.block_id = b.id
            WHERE lp.user_id = ?
        ");
        $permStmt->execute([$userId]);
        $permissions = $permStmt->fetchAll();

        // Recent user transactions
        $passbook = WalletService::getPassbook($userId, 10, 0);

        // Dist list and blocks for adding permission
        $districts = $db->query("SELECT id, name, name_hi FROM districts WHERE is_active = 1 ORDER BY name ASC")->fetchAll();
        $distributors = $db->query("SELECT id, name FROM users WHERE role = 'DISTRIBUTOR' AND status = 'ACTIVE' ORDER BY name ASC")->fetchAll();

        require dirname(__DIR__) . '/Views/admin/user_detail.php';
    }

    public function updateUserStatus(): void
    {
        if (!AuthService::validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $_SESSION['flash_error'] = 'Invalid CSRF token.';
            header('Location: /admin/users');
            exit;
        }

        $userId = (int)($_POST['user_id'] ?? 0);
        $newStatus = trim($_POST['status'] ?? '');
        $distributorId = !empty($_POST['parent_distributor_id']) ? (int)$_POST['parent_distributor_id'] : null;
        $assignAll = isset($_POST['assigned_all_districts']) ? 1 : 0;

        $db = Database::getConnection();
        $stmt = $db->prepare("
            UPDATE users
            SET status = ?,
                parent_distributor_id = ?,
                assigned_all_districts = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$newStatus, $distributorId, $assignAll, $userId]);

        AuditService::log('USER_UPDATE', 'USER', (string)$userId, ['status' => $newStatus, 'distributor_id' => $distributorId]);

        $_SESSION['flash_success'] = 'User account updated successfully.';
        header('Location: /admin/users/detail?id=' . $userId);
        exit;
    }

    public function addLocationPermission(): void
    {
        if (!AuthService::validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $_SESSION['flash_error'] = 'Invalid CSRF token.';
            header('Location: /admin/users');
            exit;
        }

        $userId = (int)($_POST['user_id'] ?? 0);
        $districtId = (int)($_POST['district_id'] ?? 0);
        $blockId = !empty($_POST['block_id']) ? (int)$_POST['block_id'] : null;

        if ($userId && $districtId) {
            $db = Database::getConnection();
            $stmt = $db->prepare("
                INSERT IGNORE INTO location_permissions (user_id, district_id, block_id, created_at)
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->execute([$userId, $districtId, $blockId]);

            AuditService::log('LOCATION_PERM_ADDED', 'USER', (string)$userId, ['district_id' => $districtId, 'block_id' => $blockId]);
            $_SESSION['flash_success'] = 'Location assignment added.';
        }

        header('Location: /admin/users/detail?id=' . $userId);
        exit;
    }

    public function removeLocationPermission(): void
    {
        if (!AuthService::validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $_SESSION['flash_error'] = 'Invalid CSRF token.';
            header('Location: /admin/users');
            exit;
        }

        $permId = (int)($_POST['perm_id'] ?? 0);
        $userId = (int)($_POST['user_id'] ?? 0);

        if ($permId) {
            $db = Database::getConnection();
            $stmt = $db->prepare("DELETE FROM location_permissions WHERE id = ?");
            $stmt->execute([$permId]);
            AuditService::log('LOCATION_PERM_REMOVED', 'USER', (string)$userId, ['perm_id' => $permId]);
            $_SESSION['flash_success'] = 'Location assignment removed.';
        }

        header('Location: /admin/users/detail?id=' . $userId);
        exit;
    }

    public function manualWalletAdjustment(): void
    {
        if (!AuthService::validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $_SESSION['flash_error'] = 'Invalid CSRF token.';
            header('Location: /admin/users');
            exit;
        }

        $userId = (int)($_POST['user_id'] ?? 0);
        $direction = strtoupper(trim($_POST['direction'] ?? 'CREDIT'));
        $amount = (float)($_POST['amount'] ?? 0);
        $reason = trim($_POST['reason'] ?? '');

        if ($amount <= 0 || empty($reason)) {
            $_SESSION['flash_error'] = 'Amount and mandatory adjustment reason are required.';
            header('Location: /admin/users/detail?id=' . $userId);
            exit;
        }

        try {
            $idemKey = 'ADMIN_ADJ_' . $userId . '_' . microtime(true);
            if ($direction === 'CREDIT') {
                WalletService::credit(
                    $userId,
                    $amount,
                    'ADMIN_ADJUSTMENT',
                    'ADMIN_MANUAL',
                    'ADJ-' . time(),
                    $idemKey,
                    "Admin balance credit adjustment: {$reason}",
                    "Admin balance credit adjustment: {$reason}",
                    $reason,
                    AuthService::id()
                );
            } else {
                WalletService::debit(
                    $userId,
                    $amount,
                    'ADMIN_ADJUSTMENT',
                    'ADMIN_MANUAL',
                    'ADJ-' . time(),
                    $idemKey,
                    "Admin balance debit adjustment: {$reason}",
                    "Admin balance debit adjustment: {$reason}",
                    $reason,
                    AuthService::id()
                );
            }

            $_SESSION['flash_success'] = 'Wallet adjustment applied successfully.';
        } catch (\Exception $e) {
            $_SESSION['flash_error'] = $e->getMessage();
        }

        header('Location: /admin/users/detail?id=' . $userId);
        exit;
    }

    public function auditLogs(): void
    {
        $db = Database::getConnection();

        $action = $_GET['action'] ?? '';
        $targetType = $_GET['target_type'] ?? '';

        $where = ["1=1"];
        $params = [];

        if (!empty($action)) {
            $where[] = "al.action = :action";
            $params[':action'] = $action;
        }

        if (!empty($targetType)) {
            $where[] = "al.target_type = :target_type";
            $params[':target_type'] = $targetType;
        }

        $whereClause = implode(' AND ', $where);

        $stmt = $db->prepare("
            SELECT al.*, u.name as actor_name, u.email as actor_email
            FROM audit_logs al
            LEFT JOIN users u ON al.actor_user_id = u.id
            WHERE {$whereClause}
            ORDER BY al.id DESC
            LIMIT 100
        ");
        $stmt->execute($params);
        $logs = $stmt->fetchAll();

        require dirname(__DIR__) . '/Views/admin/audit_logs.php';
    }
}
