<?php
declare(strict_types=1);

namespace App\Controllers\Traits;

use App\Config\Database;
use App\Services\AuthService;
use App\Services\StaffRoutingService;
use PDO;

trait ApplicationAdminTrait
{
public function adminApplications(): void
    {
        $user = AuthService::user();
        $role = $user['role'];
        if (!in_array($role, ['ADMIN', 'OPERATOR'], true)) {
            $_SESSION['flash_error'] = 'Administrative privilege required.';
            header('Location: /user/dashboard');
            exit;
        }

        $db = Database::getConnection();

        // 1. Fetch active service (srv_block_app)
        $dtRow = $db->query("SELECT id, code, name_en, fee_amount FROM data_types WHERE code = 'srv_block_app' AND is_active = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        $dataTypeId = $dtRow ? (int)$dtRow['id'] : 4;

        // Operator scoping & zero access check
        $isOperator = ($role === 'OPERATOR');
        $operatorDistrictId = $isOperator ? (int)($user['assigned_district_id'] ?? 0) : 0;
        $operatorBlockId = $isOperator ? (int)($user['assigned_block_id'] ?? 0) : 0;
        $unassignedOperator = ($isOperator && ($operatorDistrictId <= 0 || $operatorBlockId <= 0));

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

        $userId = (int)$user['id'];
        $opWhere = $isOperator ? " AND assigned_operator_id = {$userId} AND district_id = {$operatorDistrictId} AND block_id = {$operatorBlockId}" : "";

        // 2. Tab counts for All | Pending | In Process | Approved | Rejected
        $tabCounts = [
            'ALL' => (int)$db->query("SELECT COUNT(*) FROM applications WHERE data_type_id = {$dataTypeId} {$opWhere}")->fetchColumn(),
            'PENDING' => (int)$db->query("SELECT COUNT(*) FROM applications WHERE data_type_id = {$dataTypeId} AND status = 'PENDING' {$opWhere}")->fetchColumn(),
            'IN_PROCESS' => (int)$db->query("SELECT COUNT(*) FROM applications WHERE data_type_id = {$dataTypeId} AND status IN ('IN_PROCESS', 'UNDER_REVIEW') {$opWhere}")->fetchColumn(),
            'APPROVED' => (int)$db->query("SELECT COUNT(*) FROM applications WHERE data_type_id = {$dataTypeId} AND status IN ('APPROVED', 'COMPLETED') {$opWhere}")->fetchColumn(),
            'REJECTED' => (int)$db->query("SELECT COUNT(*) FROM applications WHERE data_type_id = {$dataTypeId} AND status = 'REJECTED' {$opWhere}")->fetchColumn(),
        ];

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

        if ($isOperator) {
            $districtId = $operatorDistrictId;
            $blockId = $operatorBlockId;
        }

        $where = ["a.data_type_id = :data_type_id"];
        $params = [':data_type_id' => $dataTypeId];

        if ($isOperator) {
            $where[] = "a.assigned_operator_id = :op_id AND a.district_id = :op_dist AND a.block_id = :op_block";
            $params[':op_id'] = $userId;
            $params[':op_dist'] = $operatorDistrictId;
            $params[':op_block'] = $operatorBlockId;
        }

        if ($currentTab === 'PENDING') {
            $where[] = "a.status = 'PENDING'";
        } elseif ($currentTab === 'IN_PROCESS') {
            $where[] = "a.status IN ('IN_PROCESS', 'UNDER_REVIEW')";
        } elseif ($currentTab === 'APPROVED') {
            $where[] = "a.status IN ('APPROVED', 'COMPLETED')";
        } elseif ($currentTab === 'REJECTED') {
            $where[] = "a.status = 'REJECTED'";
        }

        if (!empty($search)) {
            $where[] = "(a.applicant_identifier LIKE :search_number OR a.applicant_name LIKE :search_name OR a.applicant_mobile LIKE :search_mobile OR a.app_ref LIKE :search_ref OR a.batch_ref LIKE :search_batch OR u.name LIKE :search_uname OR u.mobile LIKE :search_umobile)";
            $searchVal = "%{$search}%";
            $params[':search_number'] = $searchVal;
            $params[':search_name'] = $searchVal;
            $params[':search_mobile'] = $searchVal;
            $params[':search_ref'] = $searchVal;
            $params[':search_batch'] = $searchVal;
            $params[':search_uname'] = $searchVal;
            $params[':search_umobile'] = $searchVal;
        }

        if ($dateFilter === 'TODAY') {
            $where[] = "DATE(a.created_at) = CURDATE()";
        } elseif ($dateFilter === 'WEEK') {
            $where[] = "a.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        } elseif ($dateFilter === 'MONTH') {
            $where[] = "a.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        }

        if (!$isOperator && $districtId > 0) {
            $where[] = "a.district_id = :district_id";
            $params[':district_id'] = $districtId;
        }

        if (!$isOperator && $blockId > 0) {
            $where[] = "a.block_id = :block_id";
            $params[':block_id'] = $blockId;
        }

        if (!empty($appCode)) {
            $where[] = "(a.applicant_identifier LIKE :app_code_like OR EXISTS (SELECT 1 FROM application_field_values afv WHERE afv.application_id = a.id AND afv.field_name = 'application_code' AND UPPER(afv.field_value) = :app_code_exact))";
            $params[':app_code_like'] = $appCode . '%';
            $params[':app_code_exact'] = $appCode;
        }

        if ($retailerId > 0) {
            $where[] = "a.user_id = :retailer_id";
            $params[':retailer_id'] = $retailerId;
        }

        if (!$isOperator && in_array($assignmentStatus, ['ASSIGNED', 'UNASSIGNED'], true)) {
            if ($assignmentStatus === 'UNASSIGNED') {
                $where[] = "a.assigned_operator_id IS NULL";
            } else {
                $where[] = "a.assigned_operator_id IS NOT NULL";
            }
        }

        $whereClause = implode(' AND ', $where);

        // Count total for pagination on complete authorized dataset
        $countSql = "SELECT COUNT(*) FROM applications a JOIN users u ON a.user_id = u.id WHERE {$whereClause}";
        $countStmt = $db->prepare($countSql);
        $countStmt->execute($params);
        $totalRecords = (int)$countStmt->fetchColumn();

        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = 25;
        $totalPages = max(1, ceil($totalRecords / $limit));
        if ($page > $totalPages) $page = $totalPages;
        $offset = ($page - 1) * $limit;

        // Fetch applications
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
            JOIN users u ON a.user_id = u.id
            JOIN districts d ON a.district_id = d.id
            JOIN blocks b ON a.block_id = b.id
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
            $districts = $db->query("SELECT id, name, name_hi, state FROM districts WHERE id = {$operatorDistrictId}")->fetchAll(PDO::FETCH_ASSOC);
            $blocks = $db->query("SELECT id, name, name_hi FROM blocks WHERE id = {$operatorBlockId}")->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $districts = $db->query("SELECT id, name, name_hi, state FROM districts WHERE is_active = 1 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
            $blocks = [];
            if ($districtId > 0) {
                $bStmt = $db->prepare("SELECT id, name, name_hi FROM blocks WHERE district_id = ? AND is_active = 1 ORDER BY name ASC");
                $bStmt->execute([$districtId]);
                $blocks = $bStmt->fetchAll(PDO::FETCH_ASSOC);
            }
        }

        $retailersQuery = "
            SELECT DISTINCT u.id, u.name, u.mobile, u.center_name
            FROM users u
            JOIN applications a ON a.user_id = u.id
            WHERE a.data_type_id = {$dataTypeId}
        ";
        if ($isOperator) {
            $retailersQuery .= " AND a.district_id = {$operatorDistrictId} AND a.block_id = {$operatorBlockId}";
        }
        $retailersQuery .= " ORDER BY u.name ASC";
        $retailers = $db->query($retailersQuery)->fetchAll(PDO::FETCH_ASSOC);

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

}
