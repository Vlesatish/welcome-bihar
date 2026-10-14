<?php
namespace App\Controllers;

use App\Config\Database;
use App\Services\AuthService;
use App\Services\ApplicationService;
use App\Services\PricingService;
use App\Services\CsvService;
use Exception;
use PDO;

class ApplicationController
{
    public function __construct()
    {
        if (!AuthService::check()) {
            if (str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/api/')) {
                header('Content-Type: application/json; charset=utf-8');
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Authentication required']);
                exit;
            }
            header('Location: /login');
            exit;
        }
    }

    public function wizard(): void
    {
        $userId = AuthService::id();
        $db = Database::getConnection();
        $user = AuthService::user();

        // 1. Single active service enforcement: strictly srv_block_app
        $dtStmt = $db->prepare("
            SELECT id, code, name_en, name_hi, category, processing_time, badge,
                   description_en, description_hi, notice, announcement, fee_amount, icon
            FROM data_types
            WHERE code = 'srv_block_app' AND is_active = 1
            LIMIT 1
        ");
        $dtStmt->execute();
        $activeDataType = $dtStmt->fetch(PDO::FETCH_ASSOC);

        if (!$activeDataType) {
            // Fallback to ID 4 or srv_block_app
            $dtStmt = $db->prepare("SELECT * FROM data_types WHERE code = 'srv_block_app' OR id = 4 LIMIT 1");
            $dtStmt->execute();
            $activeDataType = $dtStmt->fetch(PDO::FETCH_ASSOC);
        }

        $selectedDataType = $activeDataType ? (int)$activeDataType['id'] : 4;
        $dataTypes = $activeDataType ? [$activeDataType] : [];

        // 2. Fetch only districts having at least one LIVE block with an active block-specific rate for srv_block_app
        $districtsStmt = $db->prepare("
            SELECT DISTINCT d.id, d.name, d.name_hi, d.code, d.state
            FROM districts d
            JOIN blocks b ON d.id = b.district_id AND b.is_active = 1
            JOIN service_block_availability sba ON sba.block_id = b.id AND sba.district_id = d.id
            JOIN service_rate_rules srr ON srr.data_type_id = sba.data_type_id
                AND srr.district_id = d.id
                AND srr.block_id = b.id
                AND srr.is_active = 1
                AND srr.fee_amount > 0
                AND srr.application_code IS NOT NULL
                AND srr.application_code != ''
                AND srr.application_code != '*'
            WHERE d.is_active = 1
              AND sba.data_type_id = ?
              AND sba.status = 'LIVE'
            ORDER BY d.name ASC
        ");
        $districtsStmt->execute([$selectedDataType]);
        $districts = $districtsStmt->fetchAll(PDO::FETCH_ASSOC);

        $selectedDistrict = (int)($_GET['district_id'] ?? 0);
        $selectedBlock = (int)($_GET['block_id'] ?? 0);

        // Pre-fill if copy_id passed: recheck current LIVE status
        $copiedData = null;
        $copyUnavailableNotice = null;
        if (!empty($_GET['copy_id'])) {
            $copyId = (int)$_GET['copy_id'];
            $cStmt = $db->prepare("
                SELECT a.*, d.state as district_state
                FROM applications a
                LEFT JOIN districts d ON a.district_id = d.id
                WHERE a.id = ? AND (a.user_id = ? OR ? = 'ADMIN')
                LIMIT 1
            ");
            $cStmt->execute([$copyId, $userId, $user['role']]);
            $cApp = $cStmt->fetch(PDO::FETCH_ASSOC);
            if ($cApp) {
                $fRows = $db->query("SELECT field_name, field_value FROM application_field_values WHERE application_id = {$copyId}")->fetchAll(PDO::FETCH_KEY_PAIR);

                $origDistrictId = (int)$cApp['district_id'];
                $origBlockId = (int)$cApp['block_id'];

                // Recheck current LIVE status of the previous block
                $liveChk = $db->prepare("
                    SELECT status FROM service_block_availability
                    WHERE data_type_id = ? AND district_id = ? AND block_id = ?
                    LIMIT 1
                ");
                $liveChk->execute([$selectedDataType, $origDistrictId, $origBlockId]);
                $isBlockLive = ($liveChk->fetchColumn() === 'LIVE');

                $copiedData = [
                    'state' => $cApp['district_state'] ?: 'Bihar',
                    'district_id' => $origDistrictId,
                    'block_id' => $isBlockLive ? $origBlockId : null,
                    'applicant_name' => $cApp['applicant_name'],
                    'application_number' => $cApp['applicant_identifier'],
                    'application_code' => $fRows['application_code'] ?? 'BICCO',
                    'extra_fields' => $fRows
                ];

                if ($isBlockLive) {
                    $selectedDistrict = $origDistrictId;
                    $selectedBlock = $origBlockId;
                } else {
                    $selectedDistrict = $origDistrictId;
                    $selectedBlock = 0;
                    $copyUnavailableNotice = 'This block is no longer accepting new applications. Select another live block.';
                }
            }
        }

        // Retailer profile location must not override or force unconfigured districts
        $validDistrictIds = array_column($districts, 'id');
        if (!in_array($selectedDistrict, $validDistrictIds, true)) {
            $selectedDistrict = 0;
            $selectedBlock = 0;
        }

        $blocks = [];
        if ($selectedDistrict > 0) {
            $bStmt = $db->prepare("
                SELECT DISTINCT b.id, b.name, b.name_hi, b.code
                FROM blocks b
                JOIN service_block_availability sba ON sba.block_id = b.id AND sba.district_id = b.district_id
                JOIN service_rate_rules srr ON srr.data_type_id = sba.data_type_id
                    AND srr.district_id = b.district_id
                    AND srr.block_id = b.id
                    AND srr.is_active = 1
                    AND srr.fee_amount > 0
                    AND srr.application_code IS NOT NULL
                    AND srr.application_code != ''
                    AND srr.application_code != '*'
                WHERE b.district_id = ?
                  AND b.is_active = 1
                  AND sba.data_type_id = ?
                  AND sba.status = 'LIVE'
                ORDER BY b.name ASC
            ");
            $bStmt->execute([$selectedDistrict, $selectedDataType]);
            $blocks = $bStmt->fetchAll(PDO::FETCH_ASSOC);

            $validBlockIds = array_column($blocks, 'id');
            if (!in_array($selectedBlock, $validBlockIds, true)) {
                $selectedBlock = 0;
            }
        }


        $fields = [];
        $pricingInfo = null;

        if ($activeDataType) {
            $fStmt = $db->prepare("SELECT * FROM data_type_fields WHERE data_type_id = ? ORDER BY sort_order ASC, id ASC");
            $fStmt->execute([$activeDataType['id']]);
            $fields = $fStmt->fetchAll(PDO::FETCH_ASSOC);

            // Compute real-time price based on district/block rules only if block & code selected
            if ($selectedDistrict > 0 && $selectedBlock > 0 && !empty($_GET['application_code'])) {
                try {
                    $pricingInfo = PricingService::calculatePrice($activeDataType['id'], $selectedDistrict, $selectedBlock, $_GET);
                } catch (Exception $e) {
                    $pricingInfo = null;
                }
            }
        }

        $walletBalance = \App\Services\WalletService::getBalance($userId);

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
        $db = Database::getConnection();

        // Strictly enforce srv_block_app
        $activeDataType = $db->query("SELECT id FROM data_types WHERE code = 'srv_block_app' AND is_active = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        $dataTypeId = $activeDataType ? (int)$activeDataType['id'] : 4;

        $districtId = (int)($_POST['district_id'] ?? 0);
        $blockId = (int)($_POST['block_id'] ?? 0);

        // Pre-submission price verification (re-check if price changed between confirmation modal and commit)
        $confirmedFee = isset($_POST['confirmed_fee']) ? (float)$_POST['confirmed_fee'] : null;
        $currentPricing = PricingService::calculatePrice($dataTypeId, $districtId, $blockId, $_POST);
        $serverFee = (float)$currentPricing['final_fee'];

        if ($confirmedFee !== null && abs($confirmedFee - $serverFee) > 0.01) {
            $_SESSION['flash_error'] = "Notice: Service rate for this location has updated to ₹" . number_format($serverFee, 2) . " ({$currentPricing['rate_note']}). Please review and re-confirm.";
            $redirectUrl = "/applications/new?district_id={$districtId}&block_id={$blockId}";
            header('Location: ' . $redirectUrl);
            exit;
        }

        try {
            $result = ApplicationService::submit($userId, $dataTypeId, $districtId, $blockId, $_POST, $_FILES);
            $_SESSION['flash_success'] = $result['message'];
            header('Location: /applications/view?id=' . $result['application_id']);
            exit;
        } catch (Exception $e) {
            $_SESSION['flash_error'] = $e->getMessage();
            $redirectUrl = "/applications/new?district_id={$districtId}&block_id={$blockId}";
            header('Location: ' . $redirectUrl);
            exit;
        }
    }

    public function calculateFee(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $db = Database::getConnection();
        $dtStmt = $db->query("SELECT id FROM data_types WHERE code = 'srv_block_app' AND is_active = 1 LIMIT 1");
        $defaultDtId = (int)($dtStmt->fetchColumn() ?: 4);

        // Parse JSON or form post
        $rawInput = file_get_contents('php://input');
        $jsonData = json_decode($rawInput, true) ?: [];
        $params = array_merge($_GET, $_POST, $jsonData);

        $dataTypeId = (int)($params['data_type_id'] ?? $defaultDtId);
        if ($dataTypeId <= 0) $dataTypeId = $defaultDtId;

        $districtId = (int)($params['district_id'] ?? 0);
        $blockId = (int)($params['block_id'] ?? 0);

        // Resolve by name if ID was not supplied (e.g. from ecybercafe API calls)
        $districtName = trim((string)($params['district'] ?? $params['district_name'] ?? ''));
        if ($districtId <= 0 && !empty($districtName)) {
            $dStmt = $db->prepare("SELECT id FROM districts WHERE LOWER(TRIM(name)) = LOWER(TRIM(?)) LIMIT 1");
            $dStmt->execute([$districtName]);
            $districtId = (int)($dStmt->fetchColumn() ?: 0);
        }

        $blockName = trim((string)($params['block'] ?? $params['block_name'] ?? ''));
        if ($blockId <= 0 && !empty($blockName)) {
            $bSql = "SELECT id FROM blocks WHERE LOWER(TRIM(name)) = LOWER(TRIM(?))" . ($districtId > 0 ? " AND district_id = {$districtId}" : "") . " LIMIT 1";
            $bStmt = $db->prepare($bSql);
            $bStmt->execute([$blockName]);
            $blockId = (int)($bStmt->fetchColumn() ?: 0);
        }

        // Support aliases: app_prefix / appPrefix -> application_code, app_number / appNumber -> application_number
        if (empty($params['application_code'])) {
            $params['application_code'] = $params['app_prefix'] ?? $params['appPrefix'] ?? '';
        }
        if (empty($params['application_number'])) {
            $params['application_number'] = $params['app_number'] ?? $params['appNumber'] ?? '';
        }

        // Price endpoint security: calculate prices only for LIVE blocks
        if ($districtId > 0 && $blockId > 0) {
            $sbaStmt = $db->prepare("
                SELECT status FROM service_block_availability
                WHERE data_type_id = ? AND district_id = ? AND block_id = ?
                LIMIT 1
            ");
            $sbaStmt->execute([$dataTypeId, $districtId, $blockId]);
            $sbaStatus = $sbaStmt->fetchColumn();

            if ($sbaStatus !== 'LIVE') {
                http_response_code(422);
                echo json_encode([
                    'success' => false,
                    'error' => 'Service is temporarily unavailable for the selected block.',
                    'message' => 'Service is temporarily unavailable for the selected block.'
                ]);
                exit;
            }
        }

        try {
            $pricing = PricingService::calculatePrice($dataTypeId, $districtId, $blockId, $params);
            $userId = AuthService::id();
            $walletBalance = $userId ? \App\Services\WalletService::getBalance($userId) : 0.00;

            echo json_encode([
                'success' => true,
                'price' => $pricing['final_fee'],
                'base_fee' => $pricing['base_fee'],
                'final_fee' => $pricing['final_fee'],
                'applied_rules' => $pricing['applied_rules'],
                'is_complete' => $pricing['is_complete'],
                'rate_status' => $pricing['rate_status'],
                'rate_note' => $pricing['rate_note'],
                'matchedNote' => $pricing['rate_note'],
                'wallet_balance' => $walletBalance,
                'can_afford' => ($walletBalance >= $pricing['final_fee'])
            ]);
        } catch (Exception $e) {
            http_response_code(422);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
                'message' => $e->getMessage()
            ]);
        }
        exit;
    }


    /**
     * Admin Application Management (Labour Card style: Tabs, Compact rows, Start Processing, Upload & Approve, Rejection with refund)
     */
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
            $appCodes = [
                'BICCO' => 'BICCO (Income Certificate)',
                'BCCCO' => 'BCCCO (Caste Certificate)',
                'BRCCO' => 'BRCCO (Residential Certificate)',
                'BOBC'  => 'BOBC (OBC Certificate)',
                'NCLCO' => 'NCLCO (Non-Creamy Layer)',
                'RTPS'  => 'RTPS General',
                'OTHER' => 'OTHER (Other Citizen Service)'
            ];
            $page = 1;
            $totalPages = 1;
            $totalRecords = 0;
            require dirname(__DIR__) . '/Views/admin/applications.php';
            return;
        }

        $opWhere = $isOperator ? " AND district_id = {$operatorDistrictId} AND block_id = {$operatorBlockId}" : "";

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

        if ($isOperator) {
            $districtId = $operatorDistrictId;
            $blockId = $operatorBlockId;
        }

        $where = ["a.data_type_id = :data_type_id"];
        $params = [':data_type_id' => $dataTypeId];

        if ($isOperator) {
            $where[] = "a.district_id = :op_dist AND a.block_id = :op_block";
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
            $where[] = "(a.applicant_identifier LIKE :search OR a.applicant_name LIKE :search OR a.applicant_mobile LIKE :search OR a.app_ref LIKE :search OR a.batch_ref LIKE :search OR u.name LIKE :search OR u.mobile LIKE :search)";
            $params[':search'] = "%{$search}%";
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
            $where[] = "(a.applicant_identifier LIKE :app_code_like OR EXISTS (SELECT 1 FROM application_field_values afv WHERE afv.application_id = a.id AND afv.field_name = 'application_code' AND UPPER(afv.field_value) = :app_code))";
            $params[':app_code_like'] = $appCode . '%';
            $params[':app_code'] = $appCode;
        }

        if ($retailerId > 0) {
            $where[] = "a.user_id = :retailer_id";
            $params[':retailer_id'] = $retailerId;
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
                   a.assigned_operator_id, a.created_at, a.updated_at,
                   u.id as retailer_id, u.name as retailer_name, u.mobile as retailer_mobile, u.center_name,
                   d.name as district_name, d.name_hi as district_name_hi,
                   b.name as block_name, b.name_hi as block_name_hi
            FROM applications a
            JOIN users u ON a.user_id = u.id
            JOIN districts d ON a.district_id = d.id
            JOIN blocks b ON a.block_id = b.id
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
                SELECT afv.application_id, afv.field_name, afv.field_value, dtf.label_en
                FROM application_field_values afv
                LEFT JOIN data_type_fields dtf ON afv.field_id = dtf.id
                WHERE afv.application_id IN ({$inClause})
            ")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($fvRows as $fv) {
                $fieldValuesByApp[$fv['application_id']][$fv['field_name']] = [
                    'label' => $fv['label_en'] ?: $fv['field_name'],
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
            $app['application_code'] = $app['fields']['application_code']['value'] ?? '';
            if (empty($app['application_code']) && preg_match('#^([A-Z]{3,6})/#', $app['applicant_identifier'], $cm)) {
                $app['application_code'] = $cm[1];
            }
            if (empty($app['application_code'])) {
                $app['application_code'] = 'BICCO';
            }
        }
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

        $appCodes = [
            'BICCO' => 'BICCO (Income Certificate)',
            'BCCCO' => 'BCCCO (Caste Certificate)',
            'BRCCO' => 'BRCCO (Residential Certificate)',
            'BOBC'  => 'BOBC (OBC Certificate)',
            'NCLCO' => 'NCLCO (Non-Creamy Layer)',
            'RTPS'  => 'RTPS General',
            'OTHER' => 'OTHER (Other Citizen Service)'
        ];

        require dirname(__DIR__) . '/Views/admin/applications.php';
    }

    public function index(): void
    {
        $user = AuthService::user();
        $userId = $user['id'];
        $role = $user['role'];

        if ($role === 'ADMIN' || $role === 'OPERATOR') {
            $this->adminApplications();
            return;
        }

        $db = Database::getConnection();

        $status = $_GET['status'] ?? '';
        $dataTypeId = !empty($_GET['data_type_id']) ? (int)$_GET['data_type_id'] : 0;
        $state = trim($_GET['state'] ?? '');
        $districtId = !empty($_GET['district_id']) ? (int)$_GET['district_id'] : 0;
        $blockId = !empty($_GET['block_id']) ? (int)$_GET['block_id'] : 0;
        $appCode = strtoupper(trim($_GET['app_code'] ?? ''));
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
            if ($status === 'IN_PROCESS') {
                $where[] = "a.status IN ('IN_PROCESS', 'UNDER_REVIEW')";
            } elseif ($status === 'COMPLETED') {
                $where[] = "a.status IN ('COMPLETED', 'APPROVED')";
            } else {
                $where[] = "a.status = :status";
                $params[':status'] = $status;
            }
        }

        if (!empty($dataTypeId)) {
            $where[] = "a.data_type_id = :data_type_id";
            $params[':data_type_id'] = $dataTypeId;
        }

        if (!empty($state)) {
            $where[] = "(d.state = :state OR EXISTS (SELECT 1 FROM application_field_values afv WHERE afv.application_id = a.id AND afv.field_name = 'state' AND afv.field_value = :state_fv))";
            $params[':state'] = $state;
            $params[':state_fv'] = $state;
        }

        if (!empty($districtId)) {
            $where[] = "a.district_id = :district_id";
            $params[':district_id'] = $districtId;
        }

        if (!empty($blockId)) {
            $where[] = "a.block_id = :block_id";
            $params[':block_id'] = $blockId;
        }

        if (!empty($appCode)) {
            $where[] = "(a.applicant_identifier LIKE :app_code_like OR EXISTS (SELECT 1 FROM application_field_values afv WHERE afv.application_id = a.id AND afv.field_name = 'application_code' AND UPPER(afv.field_value) = :app_code))";
            $params[':app_code_like'] = $appCode . '%';
            $params[':app_code'] = $appCode;
        }

        $dateFilter = trim($_GET['date_filter'] ?? $_GET['dateFilter'] ?? '');
        if ($dateFilter === 'TODAY') {
            $where[] = "DATE(a.created_at) = CURDATE()";
        } elseif ($dateFilter === 'WEEK') {
            $where[] = "a.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        } elseif ($dateFilter === 'MONTH') {
            $where[] = "a.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        }

        if (!empty($search)) {
            $where[] = "(a.app_ref LIKE :search OR a.applicant_name LIKE :search OR a.applicant_mobile LIKE :search OR a.applicant_identifier LIKE :search OR a.batch_ref LIKE :search)";
            $params[':search'] = "%{$search}%";
        }

        $whereClause = implode(' AND ', $where);

        // Count total
        $cntStmt = $db->prepare("SELECT COUNT(*) FROM applications a JOIN districts d ON a.district_id = d.id WHERE {$whereClause}");
        $cntStmt->execute($params);
        $totalRecords = (int)$cntStmt->fetchColumn();
        $totalPages = ceil($totalRecords / $limit);

        // Fetch applications
        $sql = "
            SELECT a.id, a.app_ref, a.batch_ref, a.applicant_name, a.applicant_mobile, a.applicant_identifier,
                   a.status, a.fee_debited, a.commission_credited, a.created_at,
                   a.result_file, a.is_refunded, a.admin_remarks, a.remarks,
                   u.name as operator_name,
                   dt.name_en as dt_name_en, dt.name_hi as dt_name_hi, dt.enable_chat,
                   d.name as district_name, d.name_hi as district_name_hi, d.state as district_state,
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
        $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch dynamic field values for each application
        $appIds = array_column($applications, 'id');
        $fieldValuesByApp = [];
        if (!empty($appIds)) {
            $inClause = implode(',', array_map('intval', $appIds));
            $fvRows = $db->query("
                SELECT afv.application_id, afv.field_name, afv.field_value, dtf.label_en
                FROM application_field_values afv
                LEFT JOIN data_type_fields dtf ON afv.field_id = dtf.id
                WHERE afv.application_id IN ({$inClause})
            ")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($fvRows as $fv) {
                $fieldValuesByApp[$fv['application_id']][$fv['field_name']] = $fv['field_value'];
            }
        }
        foreach ($applications as &$app) {
            $fData = $fieldValuesByApp[$app['id']] ?? [];
            if (!isset($fData['applicant_name'])) $fData['applicant_name'] = $app['applicant_name'];
            if (!isset($fData['mobile'])) $fData['mobile'] = $app['applicant_mobile'];
            if (!isset($fData['application_number'])) $fData['application_number'] = $app['applicant_identifier'];
            if (!isset($fData['district_name'])) $fData['district_name'] = $app['district_name'];
            if (!isset($fData['block_name'])) $fData['block_name'] = $app['block_name'];
            $app['formData'] = $fData;
            $app['requestNumber'] = (string)$app['id'];
        }
        unset($app);

        $states = ['Bihar', 'Uttar Pradesh', 'Jharkhand', 'West Bengal', 'Delhi'];
        $dataTypes = $db->query("SELECT id, name_en, name_hi FROM data_types WHERE is_active = 1")->fetchAll(PDO::FETCH_ASSOC);
        $districts = $db->query("SELECT id, name, name_hi, state FROM districts WHERE is_active = 1 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

        $blocks = [];
        if ($districtId > 0) {
            $bStmt = $db->prepare("SELECT id, name, name_hi FROM blocks WHERE district_id = ? AND is_active = 1 ORDER BY name ASC");
            $bStmt->execute([$districtId]);
            $blocks = $bStmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $appCodes = [
            'BICCO' => 'BICCO (Income Certificate)',
            'BCCCO' => 'BCCCO (Caste Certificate)',
            'BRCCO' => 'BRCCO (Residential Certificate)',
            'BOBC'  => 'BOBC (OBC Certificate)',
            'NCLCO' => 'NCLCO (Non-Creamy Layer)',
            'RTPS'  => 'RTPS General',
            'OTHER' => 'OTHER (Other Citizen Service)'
        ];

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
        $app = $stmt->fetch(PDO::FETCH_ASSOC);

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

        if ($role === 'OPERATOR') {
            if (!AuthService::canOperatorAccessApplication($userId, $appId)) {
                http_response_code(403);
                $_SESSION['flash_error'] = 'Access denied: You do not have permission to view applications outside your assigned District and Block.';
                header('Location: /admin/applications');
                exit;
            }
        }

        // Dynamic field values
        $fvStmt = $db->prepare("
            SELECT afv.field_name, afv.field_value,
                   dtf.label_en, dtf.label_hi, dtf.field_type
            FROM application_field_values afv
            JOIN data_type_fields dtf ON afv.field_id = dtf.id
            WHERE afv.application_id = ?
            ORDER BY dtf.sort_order ASC, dtf.id ASC
        ");
        $fvStmt->execute([$appId]);
        $fieldValues = $fvStmt->fetchAll(PDO::FETCH_ASSOC);

        // Status history trail
        $histStmt = $db->prepare("
            SELECT * FROM application_status_history
            WHERE application_id = ?
            ORDER BY id ASC
        ");
        $histStmt->execute([$appId]);
        $history = $histStmt->fetchAll(PDO::FETCH_ASSOC);

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
        $adminRemarks = trim($_POST['admin_remarks'] ?? '');
        $rejectionReason = trim($_POST['rejection_reason'] ?? '');

        $user = AuthService::user();

        if (!in_array($user['role'], ['ADMIN', 'OPERATOR', 'DISTRIBUTOR'], true)) {
            $_SESSION['flash_error'] = 'Access denied: Regular users are not authorized to update application status.';
            header('Location: /applications/view?id=' . $appId);
            exit;
        }

        if ($user['role'] === 'OPERATOR') {
            if (!AuthService::canOperatorAccessApplication((int)$user['id'], $appId)) {
                http_response_code(403);
                $_SESSION['flash_error'] = 'Access denied: You cannot update applications outside your assigned District and Block.';
                header('Location: /admin/applications');
                exit;
            }
        }

        if ($newStatus === 'REJECTED' && empty(trim($rejectionReason))) {
            $_SESSION['flash_error'] = 'Rejection reason is mandatory. Please provide a clear explanation for rejecting the application.';
            header('Location: /applications/view?id=' . $appId);
            exit;
        }

        // Handle Result Document / Certificate upload if provided
        $resultFilePath = null;
        if (!empty($_FILES['result_file']['name']) && $_FILES['result_file']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['result_file'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowedExts = ['pdf', 'jpg', 'jpeg', 'png'];

            if (!in_array($ext, $allowedExts, true)) {
                $_SESSION['flash_error'] = "Invalid result document format '.{$ext}'. Allowed formats: " . implode(', ', $allowedExts);
                header('Location: /applications/view?id=' . $appId);
                exit;
            }

            if ($file['size'] > 10 * 1024 * 1024) {
                $_SESSION['flash_error'] = "Result file size exceeds 10MB limit.";
                header('Location: /applications/view?id=' . $appId);
                exit;
            }

            // Verify binary MIME type
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            $allowedMimes = ['application/pdf', 'image/jpeg', 'image/png'];
            if (!in_array($mime, $allowedMimes, true)) {
                $_SESSION['flash_error'] = "Invalid file binary content type ({$mime}). Only genuine PDF and image files are allowed.";
                header('Location: /applications/view?id=' . $appId);
                exit;
            }

            $resDir = dirname(dirname(__DIR__)) . '/public/uploads/results';
            if (!is_dir($resDir)) {
                mkdir($resDir, 0755, true);
            }

            $safeFileName = 'res_' . date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
            if (move_uploaded_file($file['tmp_name'], $resDir . '/' . $safeFileName)) {
                $resultFilePath = '/uploads/results/' . $safeFileName;
            }
        }

        try {
            $result = ApplicationService::updateStatus(
                $appId,
                $newStatus,
                $user['id'],
                $user['role'],
                $user['name'],
                $remarks,
                $rejectionReason,
                $adminRemarks,
                $resultFilePath
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
        $state = trim($_GET['state'] ?? '');
        $districtId = !empty($_GET['district_id']) ? (int)$_GET['district_id'] : 0;
        $blockId = !empty($_GET['block_id']) ? (int)$_GET['block_id'] : 0;
        $appCode = strtoupper(trim($_GET['app_code'] ?? ''));
        $search = trim($_GET['search'] ?? '');

        $where = ["1=1"];
        $params = [];

        if ($role === 'USER') {
            $where[] = "a.user_id = :scope_user_id";
            $params[':scope_user_id'] = $userId;
        } elseif ($role === 'DISTRIBUTOR') {
            $where[] = "a.distributor_id = :scope_dist_id";
            $params[':scope_dist_id'] = $userId;
        } elseif ($role === 'OPERATOR') {
            $opDist = (int)($user['assigned_district_id'] ?? 0);
            $opBlk = (int)($user['assigned_block_id'] ?? 0);
            if ($opDist <= 0 || $opBlk <= 0) {
                $where[] = "1 = 0";
            } else {
                $where[] = "a.district_id = :op_dist AND a.block_id = :op_blk";
                $params[':op_dist'] = $opDist;
                $params[':op_blk'] = $opBlk;
            }
        }

        if (!empty($status)) {
            if ($status === 'IN_PROCESS') {
                $where[] = "a.status IN ('IN_PROCESS', 'UNDER_REVIEW')";
            } elseif ($status === 'COMPLETED') {
                $where[] = "a.status IN ('COMPLETED', 'APPROVED')";
            } else {
                $where[] = "a.status = :status";
                $params[':status'] = $status;
            }
        }
        if (!empty($dataTypeId)) {
            $where[] = "a.data_type_id = :data_type_id";
            $params[':data_type_id'] = $dataTypeId;
        }
        if (!empty($state)) {
            $where[] = "(d.state = :state OR EXISTS (SELECT 1 FROM application_field_values afv WHERE afv.application_id = a.id AND afv.field_name = 'state' AND afv.field_value = :state_fv))";
            $params[':state'] = $state;
            $params[':state_fv'] = $state;
        }
        if (!empty($districtId)) {
            $where[] = "a.district_id = :district_id";
            $params[':district_id'] = $districtId;
        }
        if (!empty($blockId)) {
            $where[] = "a.block_id = :block_id";
            $params[':block_id'] = $blockId;
        }
        if (!empty($appCode)) {
            $where[] = "(a.applicant_identifier LIKE :app_code_like OR EXISTS (SELECT 1 FROM application_field_values afv WHERE afv.application_id = a.id AND afv.field_name = 'application_code' AND UPPER(afv.field_value) = :app_code))";
            $params[':app_code_like'] = $appCode . '%';
            $params[':app_code'] = $appCode;
        }
        if (!empty($search)) {
            $where[] = "(a.app_ref LIKE :search OR a.applicant_name LIKE :search OR a.applicant_mobile LIKE :search OR a.applicant_identifier LIKE :search)";
            $params[':search'] = "%{$search}%";
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
        CsvService::streamCsv('welcome_bihar_applications_' . date('Ymd_His') . '.csv', $headers, $rows);
    }

    /**
     * GET /api/requests - Fetch service requests formatted for the UI
     */
    public function apiGetRequests(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $user = AuthService::user();
        if (!$user) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthenticated']);
            exit;
        }

        $db = Database::getConnection();
        $userId = (int)$user['id'];
        $role = $user['role'];

        $where = ["1=1"];
        $params = [];

        // Scope
        if ($role === 'USER') {
            $where[] = "a.user_id = :scope_uid";
            $params[':scope_uid'] = $userId;
        } elseif ($role === 'DISTRIBUTOR') {
            $where[] = "a.distributor_id = :scope_did";
            $params[':scope_did'] = $userId;
        } elseif ($role === 'OPERATOR') {
            $opDist = (int)($user['assigned_district_id'] ?? 0);
            $opBlk = (int)($user['assigned_block_id'] ?? 0);
            if ($opDist <= 0 || $opBlk <= 0) {
                $where[] = "1 = 0";
            } else {
                $where[] = "a.district_id = :op_dist AND a.block_id = :op_blk";
                $params[':op_dist'] = $opDist;
                $params[':op_blk'] = $opBlk;
            }
        }

        // Filters
        $status = trim($_GET['status'] ?? '');
        if (!empty($status) && $status !== 'ALL') {
            if ($status === 'IN_PROCESS') {
                $where[] = "(a.status = 'IN_PROCESS' OR a.status = 'UNDER_REVIEW')";
            } elseif ($status === 'COMPLETED') {
                $where[] = "(a.status = 'COMPLETED' OR a.status = 'APPROVED')";
            } else {
                $where[] = "a.status = :filter_status";
                $params[':filter_status'] = $status;
            }
        }

        $search = trim($_GET['search'] ?? '');
        if (!empty($search)) {
            $where[] = "(a.app_ref LIKE :q OR a.applicant_name LIKE :q OR a.applicant_mobile LIKE :q OR a.applicant_identifier LIKE :q OR CAST(a.id AS CHAR) LIKE :q)";
            $params[':q'] = "%{$search}%";
        }

        $dateFilter = trim($_GET['date_filter'] ?? $_GET['dateFilter'] ?? 'ALL');
        if ($dateFilter === 'TODAY') {
            $where[] = "DATE(a.created_at) = CURDATE()";
        } elseif ($dateFilter === 'WEEK') {
            $where[] = "a.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        } elseif ($dateFilter === 'MONTH') {
            $where[] = "a.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        }

        $whereClause = implode(' AND ', $where);
        $sql = "
            SELECT a.id, a.app_ref, a.user_id, a.applicant_name, a.applicant_mobile, a.applicant_identifier,
                   a.fee_debited, a.commission_credited, a.status, a.admin_remarks, a.remarks,
                   a.result_file, a.is_refunded, a.is_commission_paid, a.assigned_operator_id,
                   a.created_at, a.updated_at,
                   u.name as retailer_name, u.center_name, u.mobile as retailer_mobile,
                   op.name as operator_name,
                   d.name as district_name,
                   b.name as block_name,
                   dt.name_en as dt_name_en
            FROM applications a
            JOIN users u ON a.user_id = u.id
            LEFT JOIN users op ON a.assigned_operator_id = op.id
            LEFT JOIN districts d ON a.district_id = d.id
            LEFT JOIN blocks b ON a.block_id = b.id
            JOIN data_types dt ON a.data_type_id = dt.id
            WHERE {$whereClause}
            ORDER BY a.id DESC
            LIMIT 150
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rawRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $appIds = array_column($rawRows, 'id');
        $fieldValuesByApp = [];
        if (!empty($appIds)) {
            $inClause = implode(',', array_map('intval', $appIds));
            $fvRows = $db->query("
                SELECT afv.application_id, afv.field_name, afv.field_value, dtf.label_en
                FROM application_field_values afv
                LEFT JOIN data_type_fields dtf ON afv.field_id = dtf.id
                WHERE afv.application_id IN ({$inClause})
            ")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($fvRows as $fv) {
                $fieldValuesByApp[$fv['application_id']][$fv['field_name']] = $fv['field_value'];
            }
        }

        $results = [];
        foreach ($rawRows as $r) {
            $fData = $fieldValuesByApp[$r['id']] ?? [];
            if (!isset($fData['applicant_name'])) $fData['applicant_name'] = $r['applicant_name'];
            if (!isset($fData['mobile'])) $fData['mobile'] = $r['applicant_mobile'];
            if (!isset($fData['application_number'])) $fData['application_number'] = $r['applicant_identifier'];
            if (!isset($fData['district_name'])) $fData['district_name'] = $r['district_name'];
            if (!isset($fData['block_name'])) $fData['block_name'] = $r['block_name'];

            $statusDisplay = $r['status'];
            if ($r['status'] === 'UNDER_REVIEW') $statusDisplay = 'IN_PROCESS';
            elseif ($r['status'] === 'APPROVED') $statusDisplay = 'COMPLETED';

            $results[] = [
                'id' => (string)$r['id'],
                'requestNumber' => (string)$r['id'],
                'app_ref' => $r['app_ref'],
                'retailerId' => (string)$r['user_id'],
                'retailerName' => $r['retailer_name'] . ($r['center_name'] ? " ({$r['center_name']})" : ''),
                'retailerMobile' => $r['retailer_mobile'],
                'serviceId' => 'srv_block_app',
                'serviceTitle' => $r['dt_name_en'] ?: 'Block & District Application Pass Service',
                'status' => $statusDisplay,
                'raw_status' => $r['status'],
                'price' => (float)$r['fee_debited'],
                'fee_debited' => (float)$r['fee_debited'],
                'createdAt' => date('c', strtotime($r['created_at'])),
                'created_at' => $r['created_at'],
                'formData' => $fData,
                'adminRemarks' => $r['admin_remarks'] ?: $r['remarks'],
                'outputAttachmentUrl' => $r['result_file'],
                'isRefunded' => (bool)$r['is_refunded'],
                'isCommissionPaid' => (bool)$r['is_commission_paid']
            ];
        }

        echo json_encode($results);
        exit;
    }

    /**
     * POST /api/requests - In-place AJAX submit request
     */
    public function apiSubmitRequest(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $user = AuthService::user();
        if (!$user) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Please log in to submit requests.']);
            exit;
        }

        $userId = (int)$user['id'];
        $db = Database::getConnection();

        // Parse JSON or POST payload
        $rawInput = file_get_contents('php://input');
        $jsonData = json_decode($rawInput, true);

        // If batch payload submitted to /api/requests, dispatch to apiSubmitBatch
        if (($jsonData && (!empty($jsonData['items']) || !empty($jsonData['batch']))) || !empty($_POST['items']) || !empty($_POST['batch'])) {
            $this->apiSubmitBatch();
            return;
        }

        $formData = [];
        if ($jsonData && isset($jsonData['formData'])) {
            $formData = $jsonData['formData'];
        } else {
            $formData = array_merge($_POST, $_GET);
        }

        // Enforce active service
        $activeDataType = $db->query("SELECT id, is_active FROM data_types WHERE code = 'srv_block_app' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if (!$activeDataType) {
            $activeDataType = $db->query("SELECT id, is_active FROM data_types WHERE id = 4 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        }
        if (!$activeDataType || (int)$activeDataType['is_active'] === 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Selected service is currently inactive or disabled by administrator.']);
            exit;
        }
        $dataTypeId = (int)$activeDataType['id'];

        // Resolve District ID
        $districtId = (int)($formData['district_id'] ?? 0);
        $districtName = trim((string)($formData['district'] ?? $formData['district_name'] ?? ''));
        if ($districtId <= 0 && !empty($districtName)) {
            $dStmt = $db->prepare("SELECT id FROM districts WHERE LOWER(TRIM(name)) = LOWER(TRIM(?)) LIMIT 1");
            $dStmt->execute([$districtName]);
            $districtId = (int)($dStmt->fetchColumn() ?: 0);
        }

        // Resolve Block ID
        $blockId = (int)($formData['block_id'] ?? 0);
        $blockName = trim((string)($formData['block'] ?? $formData['block_name'] ?? ''));
        if ($blockId <= 0 && !empty($blockName)) {
            $bSql = "SELECT id FROM blocks WHERE LOWER(TRIM(name)) = LOWER(TRIM(?))" . ($districtId > 0 ? " AND district_id = {$districtId}" : "") . " LIMIT 1";
            $bStmt = $db->prepare($bSql);
            $bStmt->execute([$blockName]);
            $blockId = (int)($bStmt->fetchColumn() ?: 0);
        }

        if ($districtId <= 0 || $blockId <= 0) {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'Please select a valid State, District, and Block before submitting.']);
            exit;
        }

        // Dynamic price calculation
        $currentPricing = PricingService::calculatePrice($dataTypeId, $districtId, $blockId, $formData);
        $serverFee = (float)$currentPricing['final_fee'];

        // Validate displayed price (re-acceptance if rate changed)
        $displayedPrice = isset($formData['displayed_price']) ? (float)$formData['displayed_price'] : (isset($formData['confirmed_fee']) ? (float)$formData['confirmed_fee'] : null);
        if ($displayedPrice !== null && abs($displayedPrice - $serverFee) > 0.01) {
            http_response_code(409);
            echo json_encode([
                'success' => false,
                'price_changed' => true,
                'new_price' => $serverFee,
                'error' => "Notice: Service rate for this location has updated to ₹" . number_format($serverFee, 2) . " ({$currentPricing['rate_note']}). Please review and re-confirm."
            ]);
            exit;
        }

        // Check wallet balance
        $currentWallet = \App\Services\WalletService::getBalance($userId);
        if ($currentWallet < $serverFee) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => "Insufficient Wallet Balance! Required: ₹" . number_format($serverFee, 2) . ", Available: ₹" . number_format($currentWallet, 2) . ". Please recharge."
            ]);
            exit;
        }

        try {
            $result = ApplicationService::submit($userId, $dataTypeId, $districtId, $blockId, $formData, $_FILES);
            $newBalance = \App\Services\WalletService::getBalance($userId);

            $reqObject = [
                'id' => (string)$result['application_id'],
                'requestNumber' => (string)$result['application_id'],
                'app_ref' => $result['app_ref'],
                'serviceId' => 'srv_block_app',
                'serviceTitle' => 'Block & District Application Pass Service',
                'status' => 'PENDING',
                'raw_status' => 'PENDING',
                'price' => (float)$result['price'],
                'fee_debited' => (float)$result['price'],
                'createdAt' => date('c'),
                'created_at' => date('Y-m-d H:i:s'),
                'formData' => $result['formData'],
                'adminRemarks' => null,
                'outputAttachmentUrl' => null,
                'isRefunded' => false,
                'isCommissionPaid' => false
            ];

            echo json_encode([
                'success' => true,
                'request' => $reqObject,
                'remainingWalletBalance' => $newBalance,
                'message' => "Request #{$result['app_ref']} submitted successfully! Fee ₹" . number_format($serverFee, 2) . " deducted."
            ]);
            exit;
        } catch (Exception $e) {
            http_response_code(400);
            $newBalance = \App\Services\WalletService::getBalance($userId);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
                'remainingWalletBalance' => $newBalance
            ]);
            exit;
        }
    }

    /**
     * POST /api/requests/batch - Atomic batch submission for multiple applications
     */
    public function apiSubmitBatch(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $user = AuthService::user();
        if (!$user) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Please log in to submit requests.']);
            exit;
        }

        $userId = (int)$user['id'];
        $db = Database::getConnection();

        $rawInput = file_get_contents('php://input');
        $jsonData = json_decode($rawInput, true) ?: [];

        $items = $jsonData['items'] ?? $jsonData['batch'] ?? ($_POST['items'] ?? $_POST['batch'] ?? []);
        if (is_string($items)) {
            $items = json_decode($items, true) ?: [];
        }

        if (empty($items) || !is_array($items)) {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'Batch list is empty. Please add at least one application.']);
            exit;
        }

        // Active service
        $activeDataType = $db->query("SELECT id, is_active FROM data_types WHERE code = 'srv_block_app' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if (!$activeDataType) {
            $activeDataType = $db->query("SELECT id, is_active FROM data_types WHERE id = 4 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        }
        if (!$activeDataType || (int)$activeDataType['is_active'] === 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Selected service is currently inactive or disabled by administrator.']);
            exit;
        }
        $dataTypeId = (int)$activeDataType['id'];

        $options = [
            'accept_price_change' => !empty($jsonData['accept_price_change']) || !empty($_POST['accept_price_change'])
        ];

        try {
            $result = ApplicationService::submitBatch($userId, $dataTypeId, $items, $options);
            if (isset($result['price_changed']) && $result['price_changed'] === true) {
                http_response_code(409);
                echo json_encode($result);
                exit;
            }

            echo json_encode($result);
            exit;
        } catch (\Exception $e) {
            http_response_code(400);
            $currentWallet = \App\Services\WalletService::getBalance($userId);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
                'remainingWalletBalance' => $currentWallet
            ]);
            exit;
        }
    }

    /**
     * POST /api/requests/update-status (and /api/requests/:id/status)
     */
    public function apiUpdateStatus(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $user = AuthService::user();
        if (!$user || !in_array($user['role'], ['ADMIN', 'OPERATOR', 'DISTRIBUTOR'], true)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Unauthorized action.']);
            exit;
        }

        $rawInput = file_get_contents('php://input');
        $jsonData = json_decode($rawInput, true) ?: [];

        $appId = (int)($_POST['application_id'] ?? $_POST['id'] ?? $jsonData['id'] ?? $jsonData['application_id'] ?? ($_GET['id'] ?? 0));
        $newStatus = trim((string)($_POST['status'] ?? $jsonData['status'] ?? ''));
        $adminRemarks = trim((string)($_POST['adminRemarks'] ?? $_POST['admin_remarks'] ?? $jsonData['adminRemarks'] ?? $jsonData['admin_remarks'] ?? ''));
        $rejectionReason = trim((string)($_POST['rejectionReason'] ?? $_POST['rejection_reason'] ?? $jsonData['rejectionReason'] ?? $jsonData['rejection_reason'] ?? ''));
        $outputUrl = trim((string)($_POST['outputAttachmentUrl'] ?? $jsonData['outputAttachmentUrl'] ?? ''));

        // Handle uploaded file if present
        if (!empty($_FILES['result_file']['name']) && $_FILES['result_file']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['result_file'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowedExts = ['pdf', 'jpg', 'jpeg', 'png'];

            if (!in_array($ext, $allowedExts, true) || $file['size'] > 15 * 1024 * 1024) {
                http_response_code(422);
                echo json_encode(['success' => false, 'error' => 'Invalid file format or file exceeds 15MB limit.']);
                exit;
            }

            // Verify binary MIME type
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            $allowedMimes = ['application/pdf', 'image/jpeg', 'image/png'];
            if (!in_array($mime, $allowedMimes, true)) {
                http_response_code(422);
                echo json_encode(['success' => false, 'error' => "Invalid file content ({$mime}). Only genuine PDF and images allowed."]);
                exit;
            }

            $resDir = dirname(dirname(__DIR__)) . '/public/uploads/results';
            if (!is_dir($resDir)) mkdir($resDir, 0755, true);
            $safeName = 'res_' . date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
            if (move_uploaded_file($file['tmp_name'], $resDir . '/' . $safeName)) {
                $outputUrl = '/uploads/results/' . $safeName;
            }
        }

        if ($user['role'] === 'OPERATOR') {
            if (!AuthService::canOperatorAccessApplication((int)$user['id'], $appId)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Forbidden: You do not have permission to access or update applications outside your assigned District and Block.']);
                exit;
            }
        }

        if ($newStatus === 'REJECTED' && empty(trim($rejectionReason))) {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'Rejection reason is mandatory. Please provide a clear explanation for rejecting the application.']);
            exit;
        }

        if (($newStatus === 'APPROVED' || $newStatus === 'COMPLETED') && empty($outputUrl)) {
            $db = Database::getConnection();
            $checkStmt = $db->prepare("SELECT result_file FROM applications WHERE id = ?");
            $checkStmt->execute([$appId]);
            $outputUrl = $checkStmt->fetchColumn() ?: null;
        }

        try {
            $result = ApplicationService::updateStatus(
                $appId,
                $newStatus,
                $user['id'],
                $user['role'],
                $user['name'],
                $adminRemarks,
                $rejectionReason,
                $adminRemarks,
                $outputUrl ?: null
            );

            echo json_encode([
                'success' => true,
                'message' => $result['message'],
                'status' => $newStatus,
                'outputAttachmentUrl' => $outputUrl ?: null
            ]);
            exit;
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
    }


    /**
     * GET /api/requests/chat
     */
    public function apiGetChat(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $user = AuthService::user();
        if (!$user) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthenticated']);
            exit;
        }

        $appId = (int)($_GET['application_id'] ?? $_GET['id'] ?? 0);
        if ($appId <= 0) {
            echo json_encode(['messages' => []]);
            exit;
        }

        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT a.id, a.user_id, a.assigned_operator_id, dt.enable_chat FROM applications a JOIN data_types dt ON a.data_type_id = dt.id WHERE a.id = ?");
        $stmt->execute([$appId]);
        $app = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$app) {
            http_response_code(404);
            echo json_encode(['error' => 'Application not found.']);
            exit;
        }

        // Retailer ownership check
        if ($user['role'] === 'USER' && (int)$app['user_id'] !== (int)$user['id']) {
            http_response_code(403);
            echo json_encode(['error' => 'Unauthorized: You do not have permission to view chats for this application.']);
            exit;
        }

        // Operator authorization check
        if ($user['role'] === 'OPERATOR' && !AuthService::canOperatorAccessApplication((int)$user['id'], $appId)) {
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden: You do not have permission to view chats for applications outside your assigned District and Block.']);
            exit;
        }

        $chats = ApplicationService::getChats($appId);
        echo json_encode(['messages' => $chats]);
        exit;
    }

    /**
     * POST /api/requests/chat
     */
    public function apiSendChat(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $user = AuthService::user();
        if (!$user) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthenticated']);
            exit;
        }

        $rawInput = file_get_contents('php://input');
        $jsonData = json_decode($rawInput, true) ?: [];

        $appId = (int)($_POST['application_id'] ?? $jsonData['application_id'] ?? ($_GET['id'] ?? 0));
        $message = trim((string)($_POST['message'] ?? $jsonData['message'] ?? ''));
        $attachmentUrl = trim((string)($_POST['attachment_url'] ?? $jsonData['attachment_url'] ?? ''));

        if ($appId <= 0 || empty($message)) {
            http_response_code(422);
            echo json_encode(['error' => 'Message and application ID are required.']);
            exit;
        }

        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT a.id, a.user_id, a.assigned_operator_id, dt.enable_chat FROM applications a JOIN data_types dt ON a.data_type_id = dt.id WHERE a.id = ?");
        $stmt->execute([$appId]);
        $app = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$app) {
            http_response_code(404);
            echo json_encode(['error' => 'Application not found.']);
            exit;
        }

        // Operator authorization check
        if ($user['role'] === 'OPERATOR' && !AuthService::canOperatorAccessApplication((int)$user['id'], $appId)) {
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden: You do not have permission to send chat messages for applications outside your assigned District and Block.']);
            exit;
        }

        // Check if chat is enabled
        if ((int)($app['enable_chat'] ?? 1) === 0) {
            http_response_code(403);
            echo json_encode(['error' => 'Chat is disabled for this service by administrator.']);
            exit;
        }

        // Retailer ownership check
        if ($user['role'] === 'USER' && (int)$app['user_id'] !== (int)$user['id']) {
            http_response_code(403);
            echo json_encode(['error' => 'Unauthorized: You do not own this application.']);
            exit;
        }

        try {
            $chat = ApplicationService::addChat(
                $appId,
                $user['id'],
                $user['name'],
                $user['role'],
                $message,
                $attachmentUrl ?: null
            );
            echo json_encode(['success' => true, 'message' => $chat]);
            exit;
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
            exit;
        }
    }

    /**
     * POST /api/requests/upload - File upload endpoint for photos/documents
     */
    public function apiUploadFile(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        if (!AuthService::check()) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthenticated']);
            exit;
        }

        $file = $_FILES['file'] ?? $_FILES['attachment'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            http_response_code(400);
            echo json_encode(['error' => 'No valid file uploaded.']);
            exit;
        }

        $origName = $file['name'];
        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        $allowedExts = ['pdf', 'jpg', 'jpeg', 'png', 'webp'];

        if (!in_array($ext, $allowedExts, true)) {
            http_response_code(422);
            echo json_encode(['error' => "Invalid file extension '.{$ext}'. Allowed: " . implode(', ', $allowedExts)]);
            exit;
        }

        // Validate MIME type against file content
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        $allowedMimes = [
            'pdf' => ['application/pdf'],
            'jpg' => ['image/jpeg', 'image/pjpeg'],
            'jpeg' => ['image/jpeg', 'image/pjpeg'],
            'png' => ['image/png'],
            'webp' => ['image/webp']
        ];

        if (!isset($allowedMimes[$ext]) || !in_array($mime, $allowedMimes[$ext], true)) {
            http_response_code(422);
            echo json_encode(['error' => "Security error: File MIME type '{$mime}' does not match extension '.{$ext}'."]);
            exit;
        }

        if ($file['size'] > 25 * 1024 * 1024) {
            http_response_code(422);
            echo json_encode(['error' => 'File size exceeds 25MB limit.']);
            exit;
        }

        $targetDir = dirname(dirname(__DIR__)) . '/public/uploads/documents';
        if (!is_dir($targetDir)) mkdir($targetDir, 0755, true);

        $safeName = 'doc_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        if (move_uploaded_file($file['tmp_name'], $targetDir . '/' . $safeName)) {
            $url = '/uploads/documents/' . $safeName;
            $sizeStr = ($file['size'] > 1024 * 1024)
                ? round($file['size'] / (1024 * 1024), 2) . ' MB'
                : round($file['size'] / 1024, 1) . ' KB';

            echo json_encode([
                'success' => true,
                'url' => $url,
                'filename' => $origName,
                'size' => $sizeStr
            ]);
            exit;
        }

        http_response_code(500);
        echo json_encode(['error' => 'Failed to save file on server.']);
        exit;
    }

    /**
     * GET /api/block-rates - Fetch active block rates
     */
    public function getBlockRates(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $db = Database::getConnection();
        $rates = $db->query("
            SELECT r.id, r.district_id, r.block_id, r.application_code, r.fee_amount as rate, r.is_active,
                   d.name as district_name, b.name as block_name
            FROM service_rate_rules r
            JOIN districts d ON r.district_id = d.id
            JOIN blocks b ON r.block_id = b.id
            WHERE r.is_active = 1
            ORDER BY d.name ASC, b.name ASC
        ")->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode($rates);
        exit;
    }
}

