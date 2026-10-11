<?php
namespace App\Controllers;

use App\Config\Database;
use App\Services\AuthService;
use App\Services\WalletService;
use App\Services\ApplicationService;
use App\Services\AuditService;

class DistributorController
{
    public function __construct()
    {
        if (!AuthService::check()) {
            header('Location: /login');
            exit;
        }

        if (!AuthService::isDistributor() && !AuthService::isAdmin()) {
            header('Location: /user/dashboard');
            exit;
        }
    }

    public function dashboard(): void
    {
        $distId = AuthService::id();
        $db = Database::getConnection();

        // Wallet Balance
        $balance = WalletService::getBalance($distId);

        // Check if distributor review is enabled in settings
        $revSetting = $db->query("SELECT setting_value FROM settings WHERE setting_key = 'distributor_review_required'")->fetchColumn();
        $reviewEnabled = (int)($revSetting ?: 1);

        // Team operators count
        $teamCountStmt = $db->prepare("SELECT COUNT(*) FROM users WHERE parent_distributor_id = ?");
        $teamCountStmt->execute([$distId]);
        $totalTeamMembers = (int)$teamCountStmt->fetchColumn();

        // Team application stats
        $appStatsStmt = $db->prepare("
            SELECT
                COUNT(*) as total,
                SUM(CASE WHEN status = 'PENDING' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'UNDER_REVIEW' THEN 1 ELSE 0 END) as under_review,
                SUM(CASE WHEN status = 'APPROVED' THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN status = 'REJECTED' THEN 1 ELSE 0 END) as rejected,
                COALESCE(SUM(commission_credited), 0) as total_commission_earned
            FROM applications
            WHERE distributor_id = ?
        ");
        $appStatsStmt->execute([$distId]);
        $stats = $appStatsStmt->fetch();

        // Permitted districts & blocks
        $permStmt = $db->prepare("
            SELECT d.name as district_name, d.name_hi as district_name_hi, b.name as block_name
            FROM location_permissions lp
            JOIN districts d ON lp.district_id = d.id
            LEFT JOIN blocks b ON lp.block_id = b.id
            WHERE lp.user_id = ?
        ");
        $permStmt->execute([$distId]);
        $permittedLocations = $permStmt->fetchAll();

        // Recent applications from team
        $recentStmt = $db->prepare("
            SELECT a.id, a.app_ref, a.applicant_name, a.applicant_mobile, a.status, a.commission_credited, a.created_at,
                   u.name as operator_name, u.center_name,
                   dt.name_en as dt_name_en, dt.name_hi as dt_name_hi,
                   d.name as district_name, b.name as block_name
            FROM applications a
            JOIN users u ON a.user_id = u.id
            JOIN data_types dt ON a.data_type_id = dt.id
            JOIN districts d ON a.district_id = d.id
            JOIN blocks b ON a.block_id = b.id
            WHERE a.distributor_id = ?
            ORDER BY a.id DESC
            LIMIT 8
        ");
        $recentStmt->execute([$distId]);
        $recentApplications = $recentStmt->fetchAll();

        require dirname(__DIR__) . '/Views/distributor/dashboard.php';
    }

    public function team(): void
    {
        $distId = AuthService::id();
        $db = Database::getConnection();

        $stmt = $db->prepare("
            SELECT u.id, u.uuid, u.name, u.mobile, u.email, u.status, u.center_name, u.created_at,
                   w.balance,
                   (SELECT COUNT(*) FROM applications WHERE user_id = u.id) as app_count
            FROM users u
            LEFT JOIN wallets w ON u.id = w.user_id
            WHERE u.parent_distributor_id = ?
            ORDER BY u.id DESC
        ");
        $stmt->execute([$distId]);
        $team = $stmt->fetchAll();

        require dirname(__DIR__) . '/Views/distributor/team.php';
    }

    public function applications(): void
    {
        $distId = AuthService::id();
        $db = Database::getConnection();

        $status = $_GET['status'] ?? '';
        $dataTypeId = !empty($_GET['data_type_id']) ? (int)$_GET['data_type_id'] : 0;
        $search = trim($_GET['search'] ?? '');

        $where = ["a.distributor_id = :dist_id"];
        $params = [':dist_id' => $distId];

        if (!empty($status)) {
            $where[] = "a.status = :status";
            $params[':status'] = $status;
        }

        if (!empty($dataTypeId)) {
            $where[] = "a.data_type_id = :dt_id";
            $params[':dt_id'] = $dataTypeId;
        }

        if (!empty($search)) {
            $where[] = "(a.app_ref LIKE :search OR a.applicant_name LIKE :search OR a.applicant_mobile LIKE :search)";
            $params[':search'] = "%{$search}%";
        }

        $whereClause = implode(' AND ', $where);

        $sql = "
            SELECT a.id, a.app_ref, a.applicant_name, a.applicant_mobile, a.applicant_identifier,
                   a.status, a.fee_debited, a.commission_credited, a.created_at,
                   u.name as operator_name,
                   dt.name_en as dt_name_en, dt.name_hi as dt_name_hi,
                   d.name as district_name, b.name as block_name
            FROM applications a
            JOIN users u ON a.user_id = u.id
            JOIN data_types dt ON a.data_type_id = dt.id
            JOIN districts d ON a.district_id = d.id
            JOIN blocks b ON a.block_id = b.id
            WHERE {$whereClause}
            ORDER BY a.id DESC
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $applications = $stmt->fetchAll();

        $dataTypes = $db->query("SELECT id, name_en, name_hi FROM data_types WHERE is_active = 1")->fetchAll();
        $revSetting = $db->query("SELECT setting_value FROM settings WHERE setting_key = 'distributor_review_required'")->fetchColumn();
        $reviewEnabled = (int)($revSetting ?: 1);

        require dirname(__DIR__) . '/Views/distributor/applications.php';
    }

    public function reviewApplication(): void
    {
        if (!AuthService::validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $_SESSION['flash_error'] = 'Invalid CSRF token.';
            header('Location: /distributor/applications');
            exit;
        }

        $appId = (int)($_POST['application_id'] ?? 0);
        $targetStatus = trim($_POST['status'] ?? '');
        $remarks = trim($_POST['remarks'] ?? '');
        $rejectionReason = trim($_POST['rejection_reason'] ?? '');

        try {
            $res = ApplicationService::updateStatus(
                $appId,
                $targetStatus,
                AuthService::id(),
                'DISTRIBUTOR',
                AuthService::user()['name'] ?? 'Distributor',
                $remarks,
                $rejectionReason
            );
            $_SESSION['flash_success'] = $res['message'];
        } catch (\Exception $e) {
            $_SESSION['flash_error'] = $e->getMessage();
        }

        header('Location: /applications/view?id=' . $appId);
        exit;
    }
}
