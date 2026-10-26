<?php
declare(strict_types=1);

namespace App\Controllers\Traits;

use App\Config\Database;
use App\Services\AuthService;
use App\Services\StaffRoutingService;
use App\Services\OperatorAuthorizationService;
use PDO;

trait ApplicationAdminTrait
{
public function adminApplications(): void
    {
        $user = AuthService::user();
        if (!$user) {
            header('Location: /login');
            exit;
        }

        $isAdmin = AuthService::isAdmin();
        $hasOperatorAccess = AuthService::canAccessOperatorMode((int)$user['id']);
        $activeMode = AuthService::activePortalMode();

        if (!$isAdmin && (!$hasOperatorAccess || $activeMode !== 'OPERATOR')) {
            $_SESSION['flash_error'] = 'Administrative privilege or Staff Mode required.';
            header('Location: /user/dashboard');
            exit;
        }

        $db = Database::getConnection();

        // Auto-sync RTPS certificates in background without blocking page render
        try {
            $lastSync = $_SESSION['last_rtps_admin_bg_sync'] ?? 0;
            if (time() - $lastSync > 60) {
                $_SESSION['last_rtps_admin_bg_sync'] = time();
                \App\Services\RtpsCertificateService::dispatchBackgroundWorker(0);
            }
        } catch (\Throwable $e) {}

        // 1. Fetch active service (srv_block_app)
        $dtRow = $db->query("SELECT id, code, name_en, fee_amount FROM data_types WHERE code = 'srv_block_app' AND is_active = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        $dataTypeId = $dtRow ? (int)$dtRow['id'] : 4;

        // Operator scoping & zero access check
        $isOperator = !$isAdmin;
        $userId = (int)$user['id'];
        $operatorDistrictId = $isOperator ? (int)($user['assigned_district_id'] ?? 0) : 0;
        $operatorBlockId = $isOperator ? (int)($user['assigned_block_id'] ?? 0) : 0;
        $assignedBlocks = $isOperator ? AuthService::getOperatorAssignedBlocks($userId) : [];
        $unassignedOperator = ($isOperator && empty($assignedBlocks));

        if ($unassignedOperator) {
            $tabCounts = ['ALL' => 0, 'PENDING' => 0, 'IN_PROCESS' => 0, 'APPROVED' => 0, 'REJECTED' => 0];
            $currentTab = 'ALL';
            $search = '';
            $dateFilter = 'ALL';
            $districtId = 0;
            $blockId = 0;
            $appCode = '';
            $retailerId = 0;
            $applications = [];
            $districts = [];
            $blocks = [];
            $retailers = [];
            $appCodes = $this->getApplicationCodes();
            $page = 1;
            $totalPages = 1;
            $totalRecords = 0;
            require dirname(__DIR__, 2) . '/Views/admin/applications.php';
            return;
        }

        $assignedCodes = [];
        if ($isOperator) {
            $assignedCodes = $db->query("
                SELECT DISTINCT application_code
                FROM operator_application_code_assignments
                WHERE operator_user_id = {$userId} AND is_active = 1
            ")->fetchAll(PDO::FETCH_COLUMN) ?: [];
        }

        // 2. Tab counts for All | Pending | In Process | Approved | Rejected (Unified Single-Query)
        if ($isOperator) {
            $tabCounts = OperatorAuthorizationService::getStatusCounts($userId);
        } else {
            $tabCounts = [
                'ALL' => (int)$db->query("SELECT COUNT(*) FROM applications WHERE data_type_id = {$dataTypeId}")->fetchColumn(),
                'PENDING' => (int)$db->query("SELECT COUNT(*) FROM applications WHERE data_type_id = {$dataTypeId} AND status = 'PENDING'")->fetchColumn(),
                'IN_PROCESS' => (int)$db->query("SELECT COUNT(*) FROM applications WHERE data_type_id = {$dataTypeId} AND status IN ('IN_PROCESS', 'UNDER_REVIEW')")->fetchColumn(),
                'APPROVED' => (int)$db->query("SELECT COUNT(*) FROM applications WHERE data_type_id = {$dataTypeId} AND status IN ('APPROVED', 'COMPLETED')")->fetchColumn(),
                'REJECTED' => (int)$db->query("SELECT COUNT(*) FROM applications WHERE data_type_id = {$dataTypeId} AND status = 'REJECTED'")->fetchColumn(),
            ];
        }

        // 3. Query filters
        $currentTab = strtoupper(trim($_GET['status'] ?? 'ALL'));
        if (!in_array($currentTab, ['ALL', 'PENDING', 'IN_PROCESS', 'APPROVED', 'REJECTED'], true)) {
            $currentTab = 'ALL';
        }

        $search = trim($_GET['search'] ?? '');
        $dateFilter = strtoupper(trim($_GET['date_filter'] ?? 'ALL'));
        $districtId = !empty($_GET['district_id']) ? (int)$_GET['district_id'] : 0;
        $blockId = !empty($_GET['block_id']) ? (int)$_GET['block_id'] : 0;
        $appCode = strtoupper(trim($_GET['app_code'] ?? ''));
        $retailerId = !empty($_GET['retailer_id']) ? (int)$_GET['retailer_id'] : 0;
        $assignmentStatus = strtoupper(trim($_GET['assignment_status'] ?? 'ALL'));

        $filterParams = [
            'status' => $currentTab,
            'search' => $search,
            'date_filter' => $dateFilter,
            'district_id' => $districtId,
            'block_id' => $blockId,
            'app_code' => $appCode,
            'retailer_id' => $retailerId,
            'assignment_status' => $assignmentStatus,
        ];

        $filterBuild = OperatorAuthorizationService::buildFilterConditions($filterParams, $isOperator, $userId, 'a');
        $where = $filterBuild['where'];
        $params = $filterBuild['params'];

        if (!$isOperator) {
            $where[] = "a.data_type_id = :admin_data_type_id";
            $params[':admin_data_type_id'] = $dataTypeId;
        }

        $whereClause = !empty($where) ? implode(' AND ', $where) : '1=1';

        // Count total for pagination on complete authorized dataset
        $countSql = "SELECT COUNT(*) FROM applications a LEFT JOIN users u ON a.user_id = u.id WHERE {$whereClause}";
        $countStmt = $db->prepare($countSql);
        $countStmt->execute($params);
        $totalRecords = (int)$countStmt->fetchColumn();

        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = 25;
        $totalPages = max(1, ceil($totalRecords / $limit));
        if ($page > $totalPages) $page = $totalPages;
        $offset = ($page - 1) * $limit;

        // Fetch applications with LEFT JOINs to prevent dropped rows
        $sql = "
            SELECT a.id, a.app_ref, a.batch_ref, a.applicant_name, a.applicant_mobile, a.applicant_identifier,
                   a.fee_debited, a.commission_credited, a.status, a.admin_remarks, a.remarks,
                   a.rejection_reason, a.result_file, a.is_refunded, a.is_commission_paid,
                   a.district_id, a.block_id, a.application_code, a.assigned_operator_id, a.assigned_at, a.assignment_method,
                   a.created_at, a.updated_at,
                   u.id as retailer_id, u.name as retailer_name, u.mobile as retailer_mobile, u.center_name,
                   d.name as district_name, d.name_hi as district_name_hi,
                   b.name as block_name, b.name_hi as block_name_hi,
                   op.name as assigned_operator_name, op.mobile as assigned_operator_mobile
            FROM applications a
            LEFT JOIN users u ON a.user_id = u.id
            LEFT JOIN districts d ON a.district_id = d.id
            LEFT JOIN blocks b ON a.block_id = b.id
            LEFT JOIN users op ON a.assigned_operator_id = op.id
            WHERE {$whereClause}
            ORDER BY a.updated_at DESC, a.id DESC
            LIMIT {$limit} OFFSET {$offset}
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch dynamic fields and status history for these applications
        $appIds = array_column($applications, 'id');
        $fieldValuesByApp = [];
        $statusHistoryByApp = [];

        if (!empty($appIds)) {
            $inClause = implode(',', array_map('intval', $appIds));
            $fvRows = $db->query("
                SELECT afv.application_id, afv.field_name, afv.field_value, dtf.label_en, acf.label as acf_label
                FROM application_field_values afv
                LEFT JOIN data_type_fields dtf ON afv.field_id = dtf.id
                LEFT JOIN application_code_fields acf ON afv.code_field_id = acf.id
                WHERE afv.application_id IN ({$inClause})
            ")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($fvRows as $fv) {
                $lbl = !empty($fv['acf_label']) ? $fv['acf_label'] : (!empty($fv['label_en']) ? $fv['label_en'] : ucwords(str_replace(['_', '-'], ' ', (string)$fv['field_name'])));
                $fieldValuesByApp[$fv['application_id']][$fv['field_name']] = [
                    'label' => $lbl,
                    'value' => $fv['field_value']
                ];
            }

            $histRows = $db->query("
                SELECT application_id, from_status, to_status, actor_name, actor_role, remarks, rejection_reason, created_at
                FROM application_status_history
                WHERE application_id IN ({$inClause})
                ORDER BY id ASC
            ")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($histRows as $hr) {
                $statusHistoryByApp[$hr['application_id']][] = $hr;
            }
        }

        foreach ($applications as &$app) {
            $app['fields'] = $fieldValuesByApp[$app['id']] ?? [];
            $app['history'] = $statusHistoryByApp[$app['id']] ?? [];
            if (empty($app['application_code'])) {
                $app['application_code'] = $app['fields']['application_code']['value'] ?? '';
            }
            if (empty($app['application_code']) && preg_match('#^([A-Z]{3,6})/#', (string)$app['applicant_identifier'], $cm)) {
                $app['application_code'] = $cm[1];
            }
            if (empty($app['application_code'])) {
                $app['application_code'] = 'BICCO';
            }
        }
        unset($app);
        unset($app);

        if ($isOperator) {
            $districts = AuthService::getOperatorAssignedDistricts($userId);
            $blocks = AuthService::getOperatorAssignedBlocks($userId, $districtId > 0 ? $districtId : null);
        } else {
            $districts = $db->query("SELECT id, name, name_hi, state FROM districts WHERE is_active = 1 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
            $blocks = [];
            if ($districtId > 0) {
                $bStmt = $db->prepare("SELECT id, name, name_hi FROM blocks WHERE district_id = ? AND is_active = 1 ORDER BY name ASC");
                $bStmt->execute([$districtId]);
                $blocks = $bStmt->fetchAll(PDO::FETCH_ASSOC);
            }
        }

        if ($isOperator) {
            $opCond = OperatorAuthorizationService::getAuthorizedSqlCondition($userId, 'a');
            $retailersQuery = "
                SELECT DISTINCT u.id, u.name, u.mobile, u.center_name
                FROM users u
                JOIN applications a ON a.user_id = u.id
                WHERE {$opCond}
                ORDER BY u.name ASC
            ";
        } else {
            $retailersQuery = "
                SELECT DISTINCT u.id, u.name, u.mobile, u.center_name
                FROM users u
                JOIN applications a ON a.user_id = u.id
                WHERE a.data_type_id = {$dataTypeId}
                ORDER BY u.name ASC
            ";
        }
        $retailers = $db->query($retailersQuery)->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $appCodes = $this->getApplicationCodes();

        require dirname(__DIR__, 2) . '/Views/admin/applications.php';
    }

    
public function reassignStaff(): void
    {
        $user = AuthService::user();
        if (!$user || $user['role'] !== 'ADMIN') {
            $_SESSION['flash_error'] = 'Access denied: Admin only.';
            header('Location: /admin/applications');
            exit;
        }

        if (!AuthService::validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $_SESSION['flash_error'] = 'Invalid CSRF token.';
            header('Location: /admin/applications');
            exit;
        }

        $appId = (int)($_POST['application_id'] ?? 0);
        $newOperatorId = !empty($_POST['operator_id']) ? (int)$_POST['operator_id'] : null;
        $reason = trim((string)($_POST['reason'] ?? ''));

        if ($appId <= 0) {
            $_SESSION['flash_error'] = 'Invalid application ID.';
            header('Location: /admin/applications');
            exit;
        }

        try {
            \App\Services\StaffRoutingService::reassignApplication($appId, $newOperatorId, $reason, (int)$user['id']);
            $_SESSION['flash_success'] = 'Application operator updated successfully.';
        } catch (\Exception $e) {
            $_SESSION['flash_error'] = 'Failed to reassign: ' . $e->getMessage();
        }

        $redirect = !empty($_POST['redirect_to']) ? $_POST['redirect_to'] : '/admin/applications';
        header('Location: ' . $redirect);
        exit;
    }

    public function routeUnassignedApplications(): void
    {
        $user = AuthService::user();
        if (!$user || $user['role'] !== 'ADMIN') {
            $_SESSION['flash_error'] = 'Access denied: Admin only.';
            header('Location: /admin/applications');
            exit;
        }

        if (!AuthService::validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $_SESSION['flash_error'] = 'Invalid CSRF token.';
            header('Location: /admin/applications');
            exit;
        }

        $districtId = !empty($_POST['district_id']) ? (int)$_POST['district_id'] : null;
        $blockId = !empty($_POST['block_id']) ? (int)$_POST['block_id'] : null;

        try {
            $res = \App\Services\StaffRoutingService::routeUnassignedApplications($districtId, $blockId, (int)$user['id']);
            if ($res['routed_count'] > 0) {
                $_SESSION['flash_success'] = "Successfully routed {$res['routed_count']} unassigned applications to matching active operators.";
            } else {
                $_SESSION['flash_info'] = "No unassigned applications matched active operators.";
            }
        } catch (\Throwable $e) {
            $_SESSION['flash_error'] = "Routing failed: " . $e->getMessage();
        }

        $redirect = !empty($_POST['redirect_to']) ? $_POST['redirect_to'] : '/admin/applications?assignment_status=UNASSIGNED';
        header('Location: ' . $redirect);
        exit;
    }
}
