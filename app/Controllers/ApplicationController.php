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
    use \App\Controllers\Traits\ApplicationAdminTrait;
    use \App\Controllers\Traits\ApplicationApiTrait;

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
                $where[] = "a.assigned_operator_id = :op_user_id AND a.district_id = :op_dist AND a.block_id = :op_blk";
                $params[':op_user_id'] = $userId;
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
    
}
