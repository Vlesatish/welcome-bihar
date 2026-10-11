<?php
namespace App\Controllers;

use App\Config\Database;
use App\Services\AuthService;
use App\Services\ApplicationService;
use App\Services\CsvService;
use Exception;
use PDO;

class ApplicationController
{
    public function __construct()
    {
        if (!AuthService::check()) {
            header('Location: /login');
            exit;
        }
    }

    public function wizard(): void
    {
        $userId = AuthService::id();
        $db = Database::getConnection();

        // Fetch user permitted districts
        $user = AuthService::user();
        if ($user['role'] === 'ADMIN' || (int)$user['assigned_all_districts'] === 1) {
            $districts = $db->query("SELECT id, name, name_hi FROM districts WHERE is_active = 1 ORDER BY name ASC")->fetchAll();
        } else {
            $stmt = $db->prepare("
                SELECT DISTINCT d.id, d.name, d.name_hi
                FROM location_permissions lp
                JOIN districts d ON lp.district_id = d.id
                WHERE lp.user_id = ? AND d.is_active = 1
                ORDER BY d.name ASC
            ");
            $stmt->execute([$userId]);
            $districts = $stmt->fetchAll();
        }

        // Fetch active data types
        $dataTypes = $db->query("SELECT id, code, name_en, name_hi, description_en, description_hi, fee_amount, icon FROM data_types WHERE is_active = 1 ORDER BY sort_order ASC")->fetchAll();

        // Selected params if any
        $selectedDistrict = (int)($_GET['district_id'] ?? 0);
        $selectedBlock = (int)($_GET['block_id'] ?? 0);
        $selectedDataType = (int)($_GET['data_type_id'] ?? 0);

        $blocks = [];
        if ($selectedDistrict > 0) {
            $bStmt = $db->prepare("SELECT id, name, name_hi, code FROM blocks WHERE district_id = ? AND is_active = 1 ORDER BY name ASC");
            $bStmt->execute([$selectedDistrict]);
            $blocks = $bStmt->fetchAll();
        }

        $fields = [];
        $activeDataType = null;
        if ($selectedDataType > 0) {
            $dtStmt = $db->prepare("SELECT * FROM data_types WHERE id = ? AND is_active = 1");
            $dtStmt->execute([$selectedDataType]);
            $activeDataType = $dtStmt->fetch();

            if ($activeDataType) {
                $fStmt = $db->prepare("SELECT * FROM data_type_fields WHERE data_type_id = ? ORDER BY sort_order ASC");
                $fStmt->execute([$selectedDataType]);
                $fields = $fStmt->fetchAll();
            }
        }

        require dirname(__DIR__) . '/Views/user/wizard.php';
    }

    public function submit(): void
    {
        if (!AuthService::validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $_SESSION['flash_error'] = 'Invalid request security token.';
            header('Location: /applications/new');
            exit;
        }

        $userId = AuthService::id();
        $dataTypeId = (int)($_POST['data_type_id'] ?? 0);
        $districtId = (int)($_POST['district_id'] ?? 0);
        $blockId = (int)($_POST['block_id'] ?? 0);

        try {
            $result = ApplicationService::submit($userId, $dataTypeId, $districtId, $blockId, $_POST);
            $_SESSION['flash_success'] = $result['message'];
            header('Location: /applications/view?id=' . $result['application_id']);
            exit;
        } catch (Exception $e) {
            $_SESSION['flash_error'] = $e->getMessage();
            $redirectUrl = "/applications/new?district_id={$districtId}&block_id={$blockId}&data_type_id={$dataTypeId}";
            header('Location: ' . $redirectUrl);
            exit;
        }
    }

    public function index(): void
    {
        $user = AuthService::user();
        $userId = $user['id'];
        $role = $user['role'];
        $db = Database::getConnection();

        $status = $_GET['status'] ?? '';
        $dataTypeId = !empty($_GET['data_type_id']) ? (int)$_GET['data_type_id'] : 0;
        $districtId = !empty($_GET['district_id']) ? (int)$_GET['district_id'] : 0;
        $search = trim($_GET['search'] ?? '');
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = 20;
        $offset = ($page - 1) * $limit;

        $where = ["1=1"];
        $params = [];

        // Scope filter based on role
        if ($role === 'USER') {
            $where[] = "a.user_id = :scope_user_id";
            $params[':scope_user_id'] = $userId;
        } elseif ($role === 'DISTRIBUTOR') {
            $where[] = "a.distributor_id = :scope_dist_id";
            $params[':scope_dist_id'] = $userId;
        }

        if (!empty($status)) {
            $where[] = "a.status = :status";
            $params[':status'] = $status;
        }

        if (!empty($dataTypeId)) {
            $where[] = "a.data_type_id = :data_type_id";
            $params[':data_type_id'] = $dataTypeId;
        }

        if (!empty($districtId)) {
            $where[] = "a.district_id = :district_id";
            $params[':district_id'] = $districtId;
        }

        if (!empty($search)) {
            $where[] = "(a.app_ref LIKE :search OR a.applicant_name LIKE :search OR a.applicant_mobile LIKE :search OR a.applicant_identifier LIKE :search)";
            $params[':search'] = "%{$search}%";
        }

        $whereClause = implode(' AND ', $where);

        // Count total
        $cntStmt = $db->prepare("SELECT COUNT(*) FROM applications a WHERE {$whereClause}");
        $cntStmt->execute($params);
        $totalRecords = (int)$cntStmt->fetchColumn();
        $totalPages = ceil($totalRecords / $limit);

        // Fetch applications
        $sql = "
            SELECT a.id, a.app_ref, a.applicant_name, a.applicant_mobile, a.applicant_identifier,
                   a.status, a.fee_debited, a.commission_credited, a.created_at,
                   u.name as operator_name,
                   dt.name_en as dt_name_en, dt.name_hi as dt_name_hi,
                   d.name as district_name, d.name_hi as district_name_hi,
                   b.name as block_name, b.name_hi as block_name_hi
            FROM applications a
            JOIN users u ON a.user_id = u.id
            JOIN data_types dt ON a.data_type_id = dt.id
            JOIN districts d ON a.district_id = d.id
            JOIN blocks b ON a.block_id = b.id
            WHERE {$whereClause}
            ORDER BY a.id DESC
            LIMIT {$limit} OFFSET {$offset}
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $applications = $stmt->fetchAll();

        $dataTypes = $db->query("SELECT id, name_en, name_hi FROM data_types WHERE is_active = 1")->fetchAll();
        $districts = $db->query("SELECT id, name, name_hi FROM districts WHERE is_active = 1 ORDER BY name ASC")->fetchAll();

        require dirname(__DIR__) . '/Views/user/application_list.php';
    }

    public function view(): void
    {
        $appId = (int)($_GET['id'] ?? 0);
        $user = AuthService::user();
        $userId = $user['id'];
        $role = $user['role'];
        $db = Database::getConnection();

        $stmt = $db->prepare("
            SELECT a.*,
                   u.name as operator_name, u.mobile as operator_mobile, u.center_name,
                   p.name as distributor_name,
                   dt.name_en as dt_name_en, dt.name_hi as dt_name_hi, dt.code as dt_code,
                   d.name as district_name, d.name_hi as district_name_hi,
                   b.name as block_name, b.name_hi as block_name_hi
            FROM applications a
            JOIN users u ON a.user_id = u.id
            LEFT JOIN users p ON a.distributor_id = p.id
            JOIN data_types dt ON a.data_type_id = dt.id
            JOIN districts d ON a.district_id = d.id
            JOIN blocks b ON a.block_id = b.id
            WHERE a.id = ?
        ");
        $stmt->execute([$appId]);
        $app = $stmt->fetch();

        if (!$app) {
            $_SESSION['flash_error'] = 'Application record not found.';
            header('Location: /applications');
            exit;
        }

        // Scope check
        if ($role === 'USER' && (int)$app['user_id'] !== $userId) {
            $_SESSION['flash_error'] = 'Access denied. You can only view your own applications.';
            header('Location: /applications');
            exit;
        }

        if ($role === 'DISTRIBUTOR' && (int)$app['distributor_id'] !== $userId) {
            $_SESSION['flash_error'] = 'Access denied. You can only view applications in your team scope.';
            header('Location: /applications');
            exit;
        }

        // Dynamic field values
        $fvStmt = $db->prepare("
            SELECT afv.field_name, afv.field_value,
                   dtf.label_en, dtf.label_hi, dtf.field_type
            FROM application_field_values afv
            JOIN data_type_fields dtf ON afv.field_id = dtf.id
            WHERE afv.application_id = ?
            ORDER BY dtf.sort_order ASC
        ");
        $fvStmt->execute([$appId]);
        $fieldValues = $fvStmt->fetchAll();

        // Status history trail
        $histStmt = $db->prepare("
            SELECT * FROM application_status_history
            WHERE application_id = ?
            ORDER BY id ASC
        ");
        $histStmt->execute([$appId]);
        $history = $histStmt->fetchAll();

        // Permissions for review action
        $canReview = false;
        if ($role === 'ADMIN') {
            $canReview = true;
        } elseif ($role === 'DISTRIBUTOR') {
            $revSet = $db->query("SELECT setting_value FROM settings WHERE setting_key = 'distributor_review_required'")->fetchColumn();
            $canReview = ((int)($revSet ?: 1) === 1);
        }

        require dirname(__DIR__) . '/Views/user/application_detail.php';
    }

    public function updateStatus(): void
    {
        if (!AuthService::validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $_SESSION['flash_error'] = 'Invalid CSRF token.';
            header('Location: /applications');
            exit;
        }

        $appId = (int)($_POST['application_id'] ?? 0);
        $newStatus = trim($_POST['status'] ?? '');
        $remarks = trim($_POST['remarks'] ?? '');
        $rejectionReason = trim($_POST['rejection_reason'] ?? '');

        $user = AuthService::user();

        try {
            $result = ApplicationService::updateStatus(
                $appId,
                $newStatus,
                $user['id'],
                $user['role'],
                $user['name'],
                $remarks,
                $rejectionReason
            );
            $_SESSION['flash_success'] = $result['message'];
        } catch (Exception $e) {
            $_SESSION['flash_error'] = $e->getMessage();
        }

        header('Location: /applications/view?id=' . $appId);
        exit;
    }

    public function exportCsv(): void
    {
        $user = AuthService::user();
        $userId = $user['id'];
        $role = $user['role'];
        $db = Database::getConnection();

        $status = $_GET['status'] ?? '';
        $dataTypeId = !empty($_GET['data_type_id']) ? (int)$_GET['data_type_id'] : 0;
        $districtId = !empty($_GET['district_id']) ? (int)$_GET['district_id'] : 0;

        $where = ["1=1"];
        $params = [];

        if ($role === 'USER') {
            $where[] = "a.user_id = :scope_user_id";
            $params[':scope_user_id'] = $userId;
        } elseif ($role === 'DISTRIBUTOR') {
            $where[] = "a.distributor_id = :scope_dist_id";
            $params[':scope_dist_id'] = $userId;
        }

        if (!empty($status)) {
            $where[] = "a.status = :status";
            $params[':status'] = $status;
        }
        if (!empty($dataTypeId)) {
            $where[] = "a.data_type_id = :data_type_id";
            $params[':data_type_id'] = $dataTypeId;
        }
        if (!empty($districtId)) {
            $where[] = "a.district_id = :district_id";
            $params[':district_id'] = $districtId;
        }

        $whereClause = implode(' AND ', $where);

        $sql = "
            SELECT a.app_ref, dt.name_en as data_type, d.name as district, b.name as block,
                   a.applicant_name, a.applicant_mobile, a.applicant_identifier,
                   a.fee_debited, a.commission_credited, a.status,
                   u.name as operator_name, a.rejection_reason, a.created_at
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
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $headers = [
            'Reference', 'Data Type', 'District', 'Block',
            'Applicant Name', 'Mobile', 'Identifier (Aadhaar/Ration)',
            'Fee Debited (INR)', 'Commission (INR)', 'Status',
            'Operator Name', 'Rejection Reason', 'Submission Date'
        ];

        CsvService::streamCsv('welcome_bihar_applications_' . date('Ymd_His') . '.csv', $headers, $rows);
    }
}
