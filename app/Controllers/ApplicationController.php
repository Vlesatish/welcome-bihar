<?php
namespace App\Controllers;

use App\Config\Database;
use App\Services\AuthService;
use App\Services\ApplicationService;
use App\Services\PricingService;
use App\Services\CsvService;
use App\Services\OperatorAuthorizationService;
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

    private function getApplicationCodes(): array
    {
        $appCodes = [
            'BICCO' => 'BICCO (Income Certificate)',
            'BCCCO' => 'BCCCO (Caste Certificate)',
            'BRCCO' => 'BRCCO (Residential Certificate)',
            'BOBC'  => 'BOBC (OBC Certificate)',
            'NCLCO' => 'NCLCO (Non-Creamy Layer)',
            'RTPS'  => 'RTPS General',
            'OTHER' => 'OTHER (Other Citizen Service)'
        ];

        try {
            $db = Database::getConnection();
            $dtfRow = $db->query("SELECT options_json FROM data_type_fields WHERE field_name = 'application_code' AND options_json IS NOT NULL LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            if ($dtfRow && !empty($dtfRow['options_json'])) {
                $decoded = json_decode($dtfRow['options_json'], true);
                if (is_array($decoded)) {
                    foreach ($decoded as $opt) {
                        if (!empty($opt['value'])) {
                            $cKey = strtoupper(trim($opt['value']));
                            if (!isset($appCodes[$cKey])) {
                                $appCodes[$cKey] = $cKey . ' (' . ($opt['label_en'] ?? $cKey) . ')';
                            }
                        }
                    }
                }
            }

            $ruleCodes = $db->query("SELECT DISTINCT application_code, rule_label FROM service_rate_rules WHERE application_code IS NOT NULL AND application_code != '' AND application_code != '*'")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($ruleCodes as $rc) {
                $cKey = strtoupper(trim($rc['application_code']));
                if (!isset($appCodes[$cKey])) {
                    $cleanLabel = !empty($rc['rule_label']) ? trim($rc['rule_label']) : $cKey;
                    $cleanLabel = preg_replace('/(\s*-\s*[A-Z0-9_]+)?\s*Rate(\s*Rule)?$/i', '', $cleanLabel);
                    $appCodes[$cKey] = $cKey . ' (' . $cleanLabel . ')';
                }
            }
        } catch (\Throwable $e) {}

        return $appCodes;
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
    public function index(): void
    {
        $user = AuthService::user();
        $userId = $user['id'];
        $role = $user['role'];
        $activeMode = AuthService::activePortalMode();

        if ($role === 'ADMIN' || ($activeMode === 'OPERATOR' && AuthService::canAccessOperatorMode($userId))) {
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
            $where[] = "(d.state = :state_d OR EXISTS (SELECT 1 FROM application_field_values afv WHERE afv.application_id = a.id AND afv.field_name = 'state' AND afv.field_value = :state_fv))";
            $params[':state_d'] = $state;
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
            $where[] = "(a.applicant_identifier LIKE :app_code_like OR a.application_code = :app_code_exact OR EXISTS (SELECT 1 FROM application_field_values afv WHERE afv.application_id = a.id AND afv.field_name = 'application_code' AND UPPER(afv.field_value) = :app_code_fv))";
            $params[':app_code_like'] = $appCode . '%';
            $params[':app_code_exact'] = $appCode;
            $params[':app_code_fv'] = $appCode;
        }

        $dateFilter = trim($_GET['date_filter'] ?? $_GET['dateFilter'] ?? '');
        if ($dateFilter === 'TODAY') {
            $where[] = "DATE(a.created_at) = CURDATE()";
        } elseif ($dateFilter === 'WEEK') {
            $where[] = "a.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        } elseif ($dateFilter === 'MONTH') {
            $where[] = "a.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        }

        $assignedOperatorId = !empty($_GET['assigned_operator_id']) ? (int)$_GET['assigned_operator_id'] : 0;
        if (!empty($assignedOperatorId)) {
            $where[] = "a.assigned_operator_id = :assigned_operator_id";
            $params[':assigned_operator_id'] = $assignedOperatorId;
        }

        if (!empty($search)) {
            $where[] = "(a.app_ref LIKE :search_ref OR a.applicant_name LIKE :search_name OR a.applicant_mobile LIKE :search_mobile OR a.applicant_identifier LIKE :search_number OR a.batch_ref LIKE :search_batch)";
            $searchVal = "%{$search}%";
            $params[':search_ref'] = $searchVal;
            $params[':search_name'] = $searchVal;
            $params[':search_mobile'] = $searchVal;
            $params[':search_number'] = $searchVal;
            $params[':search_batch'] = $searchVal;
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
                   a.district_id, a.block_id, a.application_code, a.assigned_operator_id,
                   u.name as retailer_name, u.name as operator_name,
                   op.name as assigned_operator_name,
                   dt.name_en as dt_name_en, dt.name_hi as dt_name_hi, dt.enable_chat,
                   d.name as district_name, d.name_hi as district_name_hi, d.state as district_state,
                   b.name as block_name, b.name_hi as block_name_hi
            FROM applications a
            JOIN users u ON a.user_id = u.id
            JOIN data_types dt ON a.data_type_id = dt.id
            JOIN districts d ON a.district_id = d.id
            JOIN blocks b ON a.block_id = b.id
            LEFT JOIN users op ON a.assigned_operator_id = op.id
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
                $v = $fv['field_value'];
                if (is_string($v) && str_starts_with($v, 'uploads/')) {
                    $v = '/' . $v;
                }
                $fieldValuesByApp[$fv['application_id']][$fv['field_name']] = $v;
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

        $appCodes = $this->getApplicationCodes();

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

        // Mode-aware scope check
        $activeMode = AuthService::activePortalMode();
        $isStaff = ($role === 'ADMIN') || ($role === 'OPERATOR') || ($activeMode === 'OPERATOR' && AuthService::canAccessOperatorMode($userId));

        if ($isStaff && $role !== 'ADMIN') {
            // Self-processing protection: a dual-role user must NEVER view/process their own application in staff mode
            if ((int)$app['user_id'] === $userId) {
                $_SESSION['flash_error'] = 'You cannot process an application submitted by your own retailer account.';
                header('Location: /admin/applications');
                exit;
            }
            if (!AuthService::canOperatorAccessApplication($userId, $appId)) {
                http_response_code(403);
                $_SESSION['flash_error'] = 'Access denied: You do not have permission to view applications outside your assigned District and Block or not assigned to you.';
                header('Location: /admin/applications');
                exit;
            }
        } elseif ($role === 'DISTRIBUTOR') {
            if ((int)$app['distributor_id'] !== $userId) {
                $_SESSION['flash_error'] = 'Access denied. You can only view applications in your team scope.';
                header('Location: /applications');
                exit;
            }
        } else {
            // Retailer mode: user views only their own submitted applications
            if ((int)$app['user_id'] !== $userId) {
                $_SESSION['flash_error'] = 'Access denied. You can only view your own applications.';
                header('Location: /applications');
                exit;
            }
        }

        // Dynamic field values (join both code fields and legacy fields safely)
        $fvStmt = $db->prepare("
            SELECT afv.field_name, afv.field_value,
                   COALESCE(acf.label, dtf.label_en, afv.field_name) AS label_en,
                   dtf.label_hi,
                   COALESCE(acf.field_type, dtf.field_type, 'text') AS field_type
            FROM application_field_values afv
            LEFT JOIN data_type_fields dtf ON afv.field_id = dtf.id
            LEFT JOIN application_code_fields acf ON afv.code_field_id = acf.id
            WHERE afv.application_id = ?
            ORDER BY COALESCE(acf.sort_order, dtf.sort_order, 999) ASC, afv.id ASC
        ");
        $fvStmt->execute([$appId]);
        $fieldValues = $fvStmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($fieldValues as &$fv) {
            if (!empty($fv['field_value']) && is_string($fv['field_value']) && str_starts_with($fv['field_value'], 'uploads/')) {
                $fv['field_value'] = '/' . $fv['field_value'];
            }
        }
        unset($fv);

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
        $redirect = !empty($_POST['redirect_to']) ? (string)$_POST['redirect_to'] : ('/applications/view?id=' . $appId);

        if (!AuthService::validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $_SESSION['flash_error'] = 'Invalid CSRF token.';
            header('Location: ' . (!empty($_POST['redirect_to']) ? (string)$_POST['redirect_to'] : '/applications'));
            exit;
        }

        $appId = (int)($_POST['application_id'] ?? 0);
        $redirect = !empty($_POST['redirect_to']) ? (string)$_POST['redirect_to'] : ('/applications/view?id=' . $appId);
        $newStatus = trim($_POST['status'] ?? '');
        $remarks = trim($_POST['remarks'] ?? '');
        $adminRemarks = trim($_POST['admin_remarks'] ?? '');
        $rejectionReason = trim($_POST['rejection_reason'] ?? '');

        $user = AuthService::user();
        $userId = (int)$user['id'];
        $isAdmin = AuthService::isAdmin();
        $activeMode = AuthService::activePortalMode();
        $isStaff = $isAdmin || ($user['role'] === 'OPERATOR') || ($activeMode === 'OPERATOR' && AuthService::canAccessOperatorMode($userId));

        if (!$isStaff && $user['role'] !== 'DISTRIBUTOR') {
            $_SESSION['flash_error'] = 'Access denied: Regular users are not authorized to update application status.';
            header('Location: ' . $redirect);
            exit;
        }

        // Strict Self-Processing Protection: Check if application was created by the logged in user
        $db = Database::getConnection();
        $appCreatorStmt = $db->prepare("SELECT user_id FROM applications WHERE id = ? LIMIT 1");
        $appCreatorStmt->execute([$appId]);
        $appCreatorId = (int)$appCreatorStmt->fetchColumn();

        if ($appCreatorId === $userId && !$isAdmin) {
            \App\Services\AuditService::log('SELF_ASSIGNMENT_PREVENTED', 'APPLICATION', (string)$appId, [
                'user_id' => $userId,
                'reason' => 'User attempted to update status of own submitted application'
            ], $userId);
            $_SESSION['flash_error'] = 'You cannot process an application submitted by your own retailer account.';
            header('Location: ' . $redirect);
            exit;
        }

        if ($isStaff && !$isAdmin) {
            if (!AuthService::canOperatorAccessApplication($userId, $appId)) {
                http_response_code(403);
                $_SESSION['flash_error'] = 'Access denied: You cannot update applications outside your assigned District and Block or not assigned to you.';
                header('Location: ' . $redirect);
                exit;
            }
        }

        if ($newStatus === 'REJECTED' && empty(trim($rejectionReason))) {
            $_SESSION['flash_error'] = 'Rejection reason is mandatory. Please provide a clear explanation for rejecting the application.';
            header('Location: ' . $redirect);
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
                header('Location: ' . $redirect);
                exit;
            }

            if ($file['size'] > 10 * 1024 * 1024) {
                $_SESSION['flash_error'] = "Result file size exceeds 10MB limit.";
                header('Location: ' . $redirect);
                exit;
            }

            // Verify binary MIME type
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            $allowedMimes = ['application/pdf', 'image/jpeg', 'image/png'];
            if (!in_array($mime, $allowedMimes, true)) {
                $_SESSION['flash_error'] = "Invalid file binary content type ({$mime}). Only genuine PDF and image files are allowed.";
                header('Location: ' . $redirect);
                exit;
            }

            $resDir = dirname(dirname(__DIR__)) . '/public/uploads/results';
            $safeResDir = 'C:/Users/satis/welcome_bihar_uploads/results';
            if (!is_dir($safeResDir)) @mkdir($safeResDir, 0777, true);
            if (!is_dir($resDir)) @mkdir($resDir, 0755, true);

            $safeFileName = 'res_' . date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
            $saved = false;
            if (@copy($file['tmp_name'], $safeResDir . '/' . $safeFileName)) {
                $saved = true;
            }
            if (@move_uploaded_file($file['tmp_name'], $resDir . '/' . $safeFileName)) {
                $saved = true;
            } elseif ($saved) {
                @copy($safeResDir . '/' . $safeFileName, $resDir . '/' . $safeFileName);
            }
            if ($saved) {
                $resultFilePath = '/uploads/results/' . $safeFileName;
            }
        }

        // Handle Base64 pasted screenshot (Ctrl + V) if provided
        if (empty($resultFilePath) && !empty($_POST['result_file_base64'])) {
            $base64 = trim($_POST['result_file_base64']);
            if (preg_match('/^data:image\/(png|jpeg|jpg);base64,(.+)$/', $base64, $matches)) {
                $ext = $matches[1] === 'jpeg' ? 'jpg' : $matches[1];
                $data = base64_decode($matches[2]);
                if ($data !== false && strlen($data) <= 10 * 1024 * 1024) {
                    $resDir = dirname(dirname(__DIR__)) . '/public/uploads/results';
                    $safeResDir = 'C:/Users/satis/welcome_bihar_uploads/results';
                    if (!is_dir($safeResDir)) @mkdir($safeResDir, 0777, true);
                    if (!is_dir($resDir)) @mkdir($resDir, 0755, true);
                    $safeFileName = 'res_paste_' . date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
                    $saved = false;
                    if (@file_put_contents($safeResDir . '/' . $safeFileName, $data) !== false) {
                        $saved = true;
                    }
                    if (@file_put_contents($resDir . '/' . $safeFileName, $data) !== false) {
                        $saved = true;
                    }
                    if ($saved) {
                        $resultFilePath = '/uploads/results/' . $safeFileName;
                    }
                }
            }
        }

        // Handle Direct URL input if provided
        if (empty($resultFilePath) && !empty($_POST['result_file_url'])) {
            $directUrl = trim((string)$_POST['result_file_url']);
            if (str_starts_with($directUrl, '/uploads/') || filter_var($directUrl, FILTER_VALIDATE_URL)) {
                $resultFilePath = $directUrl;
            }
        }

        $effectiveRole = $isAdmin ? 'ADMIN' : (($isStaff) ? 'OPERATOR' : $user['role']);
        try {
            $result = ApplicationService::updateStatus(
                $appId,
                $newStatus,
                $userId,
                $effectiveRole,
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

        header('Location: ' . $redirect);
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

        $activeMode = AuthService::activePortalMode();
        $isOperatorMode = ($activeMode === 'OPERATOR' && AuthService::canAccessOperatorMode());
        $effectiveRole = $role;
        if ($isOperatorMode) {
            $effectiveRole = 'OPERATOR';
        } elseif ($role === 'USER') {
            $effectiveRole = 'USER';
        }

        if ($effectiveRole === 'USER') {
            $where[] = "a.user_id = :scope_user_id";
            $params[':scope_user_id'] = $userId;
        } elseif ($effectiveRole === 'DISTRIBUTOR') {
            $where[] = "a.distributor_id = :scope_dist_id";
            $params[':scope_dist_id'] = $userId;
        } elseif ($effectiveRole === 'OPERATOR') {
            $where[] = OperatorAuthorizationService::getAuthorizedSqlCondition((int)$userId, 'a');
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
            $where[] = "(d.state = :state_d OR EXISTS (SELECT 1 FROM application_field_values afv WHERE afv.application_id = a.id AND afv.field_name = 'state' AND afv.field_value = :state_fv))";
            $params[':state_d'] = $state;
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
            $where[] = "(a.applicant_identifier LIKE :app_code_like OR a.application_code = :app_code_exact OR EXISTS (SELECT 1 FROM application_field_values afv WHERE afv.application_id = a.id AND afv.field_name = 'application_code' AND UPPER(afv.field_value) = :app_code_fv))";
            $params[':app_code_like'] = $appCode . '%';
            $params[':app_code_exact'] = $appCode;
            $params[':app_code_fv'] = $appCode;
        }
        if (!empty($search)) {
            $where[] = "(a.app_ref LIKE :search_ref OR a.applicant_name LIKE :search_name OR a.applicant_mobile LIKE :search_mobile OR a.applicant_identifier LIKE :search_number)";
            $searchVal = "%{$search}%";
            $params[':search_ref'] = $searchVal;
            $params[':search_name'] = $searchVal;
            $params[':search_mobile'] = $searchVal;
            $params[':search_number'] = $searchVal;
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

    /**
     * POST /admin/applications/reassign
     */
    


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

        $activeMode = AuthService::activePortalMode();
        $isOperatorMode = ($activeMode === 'OPERATOR' && AuthService::canAccessOperatorMode());
        $effectiveRole = $role;
        if ($isOperatorMode) {
            $effectiveRole = 'OPERATOR';
        } elseif ($role === 'USER') {
            $effectiveRole = 'USER';
        }

        // Scope
        if ($effectiveRole === 'USER') {
            $where[] = "a.user_id = :scope_uid";
            $params[':scope_uid'] = $userId;
        } elseif ($effectiveRole === 'DISTRIBUTOR') {
            $where[] = "a.distributor_id = :scope_did";
            $params[':scope_did'] = $userId;
        } elseif ($effectiveRole === 'OPERATOR') {
            $where[] = OperatorAuthorizationService::getAuthorizedSqlCondition((int)$userId, 'a');
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
            $result = \App\Services\ApplicationService::submit($userId, $dataTypeId, $districtId, $blockId, $formData, $_FILES);
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
            $result = \App\Services\ApplicationService::submitBatch($userId, $dataTypeId, $items, $options);
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
        if (!$user) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Unauthenticated session. Please log in.']);
            exit;
        }

        $activeMode = AuthService::activePortalMode();
        $isOperatorMode = ($activeMode === 'OPERATOR' && AuthService::canAccessOperatorMode());
        $effectiveRole = $user['role'] ?? '';
        if ($isOperatorMode) {
            $effectiveRole = 'OPERATOR';
        }

        if (!in_array($effectiveRole, ['ADMIN', 'OPERATOR', 'DISTRIBUTOR'], true)) {
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

        if ($effectiveRole === 'OPERATOR') {
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
            $result = \App\Services\ApplicationService::updateStatus(
                $appId,
                $newStatus,
                $user['id'],
                $effectiveRole,
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

        $activeMode = AuthService::activePortalMode();
        $isOperatorMode = ($activeMode === 'OPERATOR' && AuthService::canAccessOperatorMode());
        $effectiveRole = $user['role'];
        if ($isOperatorMode) {
            $effectiveRole = 'OPERATOR';
        } elseif ($user['role'] === 'USER') {
            $effectiveRole = 'USER';
        }

        // Operator authorization check
        if ($effectiveRole === 'OPERATOR') {
            if ((int)$app['user_id'] === (int)$user['id']) {
                http_response_code(403);
                echo json_encode(['error' => 'You cannot view chats as operator on an application submitted by your own retailer account.']);
                exit;
            }
            if (!AuthService::canOperatorAccessApplication((int)$user['id'], $appId)) {
                http_response_code(403);
                echo json_encode(['error' => 'Forbidden: You do not have permission to view chats for applications outside your assigned District and Block.']);
                exit;
            }
        } elseif ($effectiveRole === 'USER' && (int)$app['user_id'] !== (int)$user['id']) {
            http_response_code(403);
            echo json_encode(['error' => 'Unauthorized: You do not have permission to view chats for this application.']);
            exit;
        }

        $chats = \App\Services\ApplicationService::getChats($appId);
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

        $activeMode = AuthService::activePortalMode();
        $isOperatorMode = ($activeMode === 'OPERATOR' && AuthService::canAccessOperatorMode());
        $effectiveRole = $user['role'];
        if ($isOperatorMode) {
            $effectiveRole = 'OPERATOR';
        } elseif ($user['role'] === 'USER') {
            $effectiveRole = 'USER';
        }

        // Operator authorization check
        if ($effectiveRole === 'OPERATOR') {
            if ((int)$app['user_id'] === (int)$user['id']) {
                http_response_code(403);
                echo json_encode(['error' => 'You cannot process or send chat as operator on an application submitted by your own retailer account.']);
                exit;
            }
            if (!AuthService::canOperatorAccessApplication((int)$user['id'], $appId)) {
                http_response_code(403);
                echo json_encode(['error' => 'Forbidden: You do not have permission to send chat messages for applications outside your assigned District and Block.']);
                exit;
            }
        }

        // Check if chat is enabled
        if ((int)($app['enable_chat'] ?? 1) === 0) {
            http_response_code(403);
            echo json_encode(['error' => 'Chat is disabled for this service by administrator.']);
            exit;
        }

        // Retailer ownership check
        if ($effectiveRole === 'USER' && (int)$app['user_id'] !== (int)$user['id']) {
            http_response_code(403);
            echo json_encode(['error' => 'Unauthorized: You do not own this application.']);
            exit;
        }

        try {
            $chat = \App\Services\ApplicationService::addChat(
                $appId,
                $user['id'],
                $user['name'],
                $effectiveRole,
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

        $targetDir = dirname(__DIR__, 3) . '/public/uploads/documents';
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
    


    /**
     * User: Fetch fresh ServiceOnline CAPTCHA session for manual tracking modal
     * GET /applications/rtps-captcha
     */
    public function getRtpsCaptcha(): void
    {
        if (!AuthService::check()) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit;
        }

        $res = \App\Services\RtpsCertificateService::fetchCaptchaSession();
        header('Content-Type: application/json');
        echo json_encode($res);
        exit;
    }

    /**
     * User: One-Click / Manual Check Status & Auto Attach for an authorized application
     * POST /applications/auto-fetch
     */
    public function autoFetchRtpsCertificate(): void
    {
        if (!AuthService::check()) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Please log in to track applications.']);
            exit;
        }

        $appId = !empty($_POST['application_id']) ? (int)$_POST['application_id'] : 0;
        if ($appId <= 0) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid Application ID.']);
            exit;
        }

        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT id, user_id, status, result_file, applicant_identifier, applicant_name FROM applications WHERE id = ? LIMIT 1");
        $stmt->execute([$appId]);
        $app = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$app) {
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Application record not found.']);
            exit;
        }

        // Strict Ownership Enforcement: Users can only check their own submitted applications
        $currentUserId = (int)AuthService::id();
        if ((int)$app['user_id'] !== $currentUserId && !AuthService::isStaffOrAdmin()) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Access denied: You can only check applications submitted by your account.']);
            exit;
        }

        if (!empty($app['result_file']) && $app['status'] === 'COMPLETED') {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'is_completed' => true,
                'result_file' => $app['result_file'],
                'message' => 'Certificate already downloaded and attached!'
            ]);
            exit;
        }

        // Release session lock so client can poll or browse concurrently
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $options = [
            'actor_user_id' => $currentUserId,
            'actor_role' => AuthService::role(),
            'actor_name' => AuthService::user()['name'] ?? 'User',
            'track_using' => $_POST['track_using'] ?? null,
            'apply_date' => $_POST['apply_date'] ?? null,
            'captcha_answer' => $_POST['captcha_answer'] ?? null,
            'captcha_session_id' => $_POST['captcha_session_id'] ?? null,
            'father_name' => $_POST['father_name'] ?? null,
            'mother_name' => $_POST['mother_name'] ?? null,
            'force' => !empty($_POST['force'])
        ];

        try {
            $res = \App\Services\RtpsCertificateService::checkAndProcessApplication($appId, $options);
            header('Content-Type: application/json');
            echo json_encode($res);
        } catch (\Throwable $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Tracking error: ' . $e->getMessage()]);
        }
        exit;
    }

    /**
     * User: Poll application status for live modal updates
     * GET /applications/poll-status?id=123
     */
    public function pollApplicationStatus(): void
    {
        if (!AuthService::check()) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit;
        }

        $appId = !empty($_GET['id']) ? (int)$_GET['id'] : 0;
        if ($appId <= 0) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid ID']);
            exit;
        }

        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT id, user_id, status, result_file, rejection_reason, admin_remarks, remarks FROM applications WHERE id = ? LIMIT 1");
        $stmt->execute([$appId]);
        $app = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$app) {
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Not found']);
            exit;
        }

        if ((int)$app['user_id'] !== (int)AuthService::id() && !AuthService::isStaffOrAdmin()) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            exit;
        }

        $qStmt = $db->prepare("SELECT status, last_status_message, last_checked_at FROM rtps_certificate_queue WHERE application_id = ? LIMIT 1");
        $qStmt->execute([$appId]);
        $q = $qStmt->fetch(PDO::FETCH_ASSOC);

        $qStatus = $q['status'] ?? 'NONE';
        $qMsg = $q['last_status_message'] ?? '';
        $qChecked = $q['last_checked_at'] ?? null;
        $isRej = ($app['status'] === 'REJECTED' || stripos($qStatus, 'FAILED') !== false || stripos($qMsg, 'reject') !== false);

        header('Content-Type: application/json');
        echo json_encode([
            'is_completed' => ($app['status'] === 'COMPLETED' || $app['status'] === 'APPROVED' || !empty($app['result_file'])),
            'is_rejected' => $isRej,
            'status' => $app['status'],
            'is_checking' => ($qStatus === 'CHECKING'),
            'last_check_status' => $qMsg,
            'rejection_reason' => $app['rejection_reason'] ?? $qMsg,
            'portal_status' => $isRej ? 'Rejected' : ($qStatus === 'ATTACHED' ? 'Delivered' : 'Under Process'),
            'checked_at' => $qChecked,
            'has_file' => !empty($app['result_file']),
            'result_file' => $app['result_file']
        ]);
        exit;
    }
}
