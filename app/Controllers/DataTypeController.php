<?php
namespace App\Controllers;

use App\Config\Database;
use App\Services\AuthService;
use App\Services\AuditService;
use Exception;
use PDO;

class DataTypeController
{
    public function __construct()
    {
        if (!AuthService::isAdmin()) {
            header('Location: /login');
            exit;
        }
    }

    public function index(): void
    {
        $db = Database::getConnection();

        // 1. Fetch primary srv_block_app service
        $activeServiceStmt = $db->query("
            SELECT dt.*,
                   (SELECT COUNT(*) FROM data_type_fields WHERE data_type_id = dt.id) as field_count,
                   (SELECT COUNT(*) FROM applications WHERE data_type_id = dt.id) as app_count,
                   (SELECT COUNT(*) FROM service_rate_rules WHERE data_type_id = dt.id AND is_active = 1) as rule_count
            FROM data_types dt
            WHERE dt.code = 'srv_block_app'
            LIMIT 1
        ");
        $activeService = $activeServiceStmt->fetch(PDO::FETCH_ASSOC);

        // 2. Fetch archived / deactivated other services (for historical reference only)
        $archivedServices = $db->query("
            SELECT dt.*,
                   (SELECT COUNT(*) FROM data_type_fields WHERE data_type_id = dt.id) as field_count,
                   (SELECT COUNT(*) FROM applications WHERE data_type_id = dt.id) as app_count,
                   (SELECT COUNT(*) FROM service_rate_rules WHERE data_type_id = dt.id AND is_active = 1) as rule_count
            FROM data_types dt
            WHERE dt.code != 'srv_block_app'
            ORDER BY dt.id ASC
        ")->fetchAll(PDO::FETCH_ASSOC);

        // All data types list for backwards compatibility
        $dataTypes = $activeService ? array_merge([$activeService], $archivedServices) : $archivedServices;

        require dirname(__DIR__) . '/Views/admin/data_types.php';
    }

    public function saveDataType(): void
    {
        if (!AuthService::validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $_SESSION['flash_error'] = 'Invalid CSRF token.';
            header('Location: /admin/data-types');
            exit;
        }

        $id = (int)($_POST['id'] ?? 0);
        $nameEn = trim($_POST['name_en'] ?? '');
        $nameHi = trim($_POST['name_hi'] ?? '') ?: $nameEn;
        $category = trim($_POST['category'] ?? 'District & Block Services') ?: 'District & Block Services';
        $processingTime = trim($_POST['processing_time'] ?? '10-30 MIN') ?: '10-30 MIN';
        $badge = trim($_POST['badge'] ?? '');
        $descEn = trim($_POST['description_en'] ?? '');
        $descHi = trim($_POST['description_hi'] ?? '') ?: $descEn;
        $notice = trim($_POST['notice'] ?? '');
        $announcement = trim($_POST['announcement'] ?? '');
        $icon = trim($_POST['icon'] ?? 'bi-building') ?: 'bi-building';
        $fee = (float)($_POST['fee_amount'] ?? 50.00);
        $comm = (isset($_POST['distributor_commission']) && $_POST['distributor_commission'] !== '') ? max(0.0, (float)$_POST['distributor_commission']) : 0.00;
        $dupField = trim($_POST['duplicate_check_field'] ?? 'application_number') ?: 'application_number';
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $sortOrder = (int)($_POST['sort_order'] ?? 1);

        $db = Database::getConnection();

        try {
            if (empty($nameEn)) {
                throw new Exception("Service Name (English) is required.");
            }

            // Strictly manage srv_block_app only (prevent duplicate or unrelated new services)
            $existingSrvId = (int)$db->query("SELECT id FROM data_types WHERE code = 'srv_block_app' LIMIT 1")->fetchColumn();
            $targetId = ($id > 0) ? $id : $existingSrvId;

            $enableChat = isset($_POST['enable_chat']) ? 1 : 0;
            $warningImage = trim($_POST['warning_image'] ?? '');

            if ($targetId > 0) {
                $stmt = $db->prepare("
                    UPDATE data_types
                    SET name_en = ?, name_hi = ?, category = ?, processing_time = ?, badge = ?,
                        description_en = ?, description_hi = ?, notice = ?, announcement = ?, warning_image = ?,
                        icon = ?, fee_amount = ?, distributor_commission = ?, duplicate_check_field = ?,
                        enable_chat = ?, is_active = ?, sort_order = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([
                    $nameEn, $nameHi, $category, $processingTime, $badge,
                    $descEn, $descHi, $notice, $announcement, $warningImage ?: null,
                    $icon, $fee, $comm, $dupField,
                    $enableChat, $isActive, $sortOrder, $targetId
                ]);
                AuditService::log('DATA_TYPE_UPDATED', 'DATA_TYPE', (string)$targetId, ['name' => $nameEn]);
                $_SESSION['flash_success'] = 'Block & District Application Pass Service settings updated successfully.';
            } else {
                $stmt = $db->prepare("
                    INSERT INTO data_types (
                        code, name_en, name_hi, category, processing_time, badge,
                        description_en, description_hi, notice, announcement, warning_image,
                        icon, fee_amount, distributor_commission, duplicate_check_field,
                        enable_chat, is_active, sort_order, created_at
                    ) VALUES (
                        'srv_block_app', ?, ?, ?, ?, ?,
                        ?, ?, ?, ?, ?,
                        ?, ?, ?, ?,
                        ?, ?, ?, NOW()
                    )
                ");
                $stmt->execute([
                    $nameEn, $nameHi, $category, $processingTime, $badge,
                    $descEn, $descHi, $notice, $announcement, $warningImage ?: null,
                    $icon, $fee, $comm, $dupField,
                    $enableChat, $isActive, $sortOrder
                ]);
                AuditService::log('DATA_TYPE_CREATED', 'DATA_TYPE', (string)$db->lastInsertId(), ['name' => $nameEn]);
                $_SESSION['flash_success'] = 'Block & District Application Pass Service created successfully.';
            }
        } catch (Exception $e) {
            $_SESSION['flash_error'] = 'Error: ' . $e->getMessage();
        }

        header('Location: /admin/data-types');
        exit;
    }

    public function toggleStatus(): void
    {
        if (!AuthService::validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $_SESSION['flash_error'] = 'Invalid CSRF token.';
            header('Location: /admin/data-types');
            exit;
        }

        $id = (int)($_POST['id'] ?? 0);
        $db = Database::getConnection();

        $stmt = $db->prepare("SELECT id, name_en, is_active FROM data_types WHERE id = ?");
        $stmt->execute([$id]);
        $dt = $stmt->fetch();

        if ($dt) {
            $newStatus = $dt['is_active'] ? 0 : 1;
            $db->prepare("UPDATE data_types SET is_active = ?, updated_at = NOW() WHERE id = ?")->execute([$newStatus, $id]);
            AuditService::log('DATA_TYPE_STATUS_TOGGLED', 'DATA_TYPE', (string)$id, ['name' => $dt['name_en'], 'new_status' => $newStatus]);
            $_SESSION['flash_success'] = "Service '{$dt['name_en']}' is now " . ($newStatus ? 'Active' : 'Inactive') . ".";
        }

        header('Location: /admin/data-types');
        exit;
    }

    public function reorderPriority(): void
    {
        if (!AuthService::validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $_SESSION['flash_error'] = 'Invalid CSRF token.';
            header('Location: /admin/data-types');
            exit;
        }

        $id = (int)($_POST['id'] ?? 0);
        $direction = $_POST['direction'] ?? 'up'; // 'up' or 'down'

        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT id, sort_order FROM data_types WHERE id = ?");
        $stmt->execute([$id]);
        $current = $stmt->fetch();

        if ($current) {
            $currentOrder = (int)$current['sort_order'];
            if ($direction === 'up') {
                $newOrder = max(0, $currentOrder - 1);
            } else {
                $newOrder = $currentOrder + 1;
            }

            $db->prepare("UPDATE data_types SET sort_order = ?, updated_at = NOW() WHERE id = ?")->execute([$newOrder, $id]);
            $_SESSION['flash_success'] = 'Service priority updated.';
        }

        header('Location: /admin/data-types');
        exit;
    }

    public function saveNotice(): void
    {
        if (!AuthService::validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $_SESSION['flash_error'] = 'Invalid CSRF token.';
            header('Location: /admin/data-types');
            exit;
        }

        $id = (int)($_POST['id'] ?? 0);
        $notice = trim($_POST['notice'] ?? '');
        $announcement = trim($_POST['announcement'] ?? '');
        $warningImage = trim($_POST['warning_image'] ?? '');

        // Handle uploaded warning image
        if (!empty($_FILES['warning_image_file']['name']) && $_FILES['warning_image_file']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['warning_image_file'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                $upDir = dirname(dirname(__DIR__)) . '/public/uploads/notices';
                if (!is_dir($upDir)) mkdir($upDir, 0755, true);
                $safeName = 'notice_' . date('Ymd_His') . '.' . $ext;
                if (move_uploaded_file($file['tmp_name'], $upDir . '/' . $safeName)) {
                    $warningImage = '/uploads/notices/' . $safeName;
                }
            }
        }

        $db = Database::getConnection();
        $stmt = $db->prepare("UPDATE data_types SET notice = ?, announcement = ?, warning_image = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$notice ?: null, $announcement ?: null, $warningImage ?: null, $id]);

        AuditService::log('DATA_TYPE_NOTICE_UPDATED', 'DATA_TYPE', (string)$id, ['notice' => $notice, 'announcement' => $announcement]);
        $_SESSION['flash_success'] = 'Notice banner & announcement updated.';

        header('Location: /admin/data-types');
        exit;
    }

    public function deleteDataType(): void
    {
        if (!AuthService::validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $_SESSION['flash_error'] = 'Invalid CSRF token.';
            header('Location: /admin/data-types');
            exit;
        }

        $id = (int)($_POST['id'] ?? 0);
        $db = Database::getConnection();

        // Check if applications exist for this data type
        $cntStmt = $db->prepare("SELECT COUNT(*) FROM applications WHERE data_type_id = ?");
        $cntStmt->execute([$id]);
        $appCount = (int)$cntStmt->fetchColumn();

        if ($appCount > 0) {
            $_SESSION['flash_error'] = "Cannot delete service: {$appCount} submitted applications are linked to it. Please deactivate the service instead.";
            header('Location: /admin/data-types');
            exit;
        }

        $stmt = $db->prepare("SELECT name_en FROM data_types WHERE id = ?");
        $stmt->execute([$id]);
        $name = $stmt->fetchColumn();

        $db->prepare("DELETE FROM data_types WHERE id = ?")->execute([$id]);
        AuditService::log('DATA_TYPE_DELETED', 'DATA_TYPE', (string)$id, ['name' => $name]);
        $_SESSION['flash_success'] = "Service '{$name}' deleted successfully.";

        header('Location: /admin/data-types');
        exit;
    }

    // Rate Rules Management for a Service
    public function rateRules(): void
    {
        if (!AuthService::isAdmin()) {
            $_SESSION['flash_error'] = 'Access denied: Admin privileges required.';
            header('Location: /login');
            exit;
        }

        $db = Database::getConnection();
        $dataTypeId = (int)($_GET['id'] ?? 0);
        if ($dataTypeId <= 0) {
            $dtIdStmt = $db->query("SELECT id FROM data_types WHERE code = 'srv_block_app' LIMIT 1");
            $dataTypeId = (int)($dtIdStmt->fetchColumn() ?: 4);
        }

        $stmt = $db->prepare("SELECT * FROM data_types WHERE id = ?");
        $stmt->execute([$dataTypeId]);
        $dataType = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$dataType) {
            $dataType = $db->query("SELECT * FROM data_types ORDER BY sort_order ASC, id ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            $dataTypeId = (int)($dataType['id'] ?? 4);
        }

        // Fetch configured fallback defaults
        $fallbackStandard = \App\Services\PricingService::getSettingValue($db, 'fallback_rate_standard', 100.00);
        $fallbackOther = \App\Services\PricingService::getSettingValue($db, 'fallback_rate_other', 130.00);

        // Filtering parameters
        $filterDistrict = !empty($_GET['filter_district']) ? (int)$_GET['filter_district'] : null;
        $filterBlock = !empty($_GET['filter_block']) ? (int)$_GET['filter_block'] : null;
        $filterCode = trim($_GET['filter_code'] ?? '');
        $filterStatus = trim($_GET['filter_status'] ?? '');
        $filterSearch = trim($_GET['filter_search'] ?? '');

        // Pre-selection parameters for newly created block/district
        $preselectedDistrict = (int)($_GET['selected_district'] ?? ($filterDistrict ?: 0));
        $preselectedBlock = (int)($_GET['selected_block'] ?? ($filterBlock ?: 0));

        $whereClauses = ["r.data_type_id = ?"];
        $queryParams = [$dataTypeId];

        if ($filterDistrict !== null && $filterDistrict > 0) {
            $whereClauses[] = "r.district_id = ?";
            $queryParams[] = $filterDistrict;
        }

        if ($filterBlock !== null && $filterBlock > 0) {
            $whereClauses[] = "r.block_id = ?";
            $queryParams[] = $filterBlock;
        }

        if ($filterCode !== '' && $filterCode !== 'ALL') {
            $whereClauses[] = "r.application_code = ?";
            $queryParams[] = $filterCode;
        }

        if ($filterStatus === 'active') {
            $whereClauses[] = "r.is_active = 1";
        } elseif ($filterStatus === 'disabled') {
            $whereClauses[] = "r.is_active = 0";
        }

        if ($filterSearch !== '') {
            $whereClauses[] = "(r.rule_label LIKE ? OR d.name LIKE ? OR b.name LIKE ? OR r.application_code LIKE ?)";
            $term = "%{$filterSearch}%";
            $queryParams[] = $term;
            $queryParams[] = $term;
            $queryParams[] = $term;
            $queryParams[] = $term;
        }

        $whereSql = implode(" AND ", $whereClauses);

        $rStmt = $db->prepare("
            SELECT r.*, d.name as district_name, d.state as district_state, b.name as block_name
            FROM service_rate_rules r
            LEFT JOIN districts d ON r.district_id = d.id
            LEFT JOIN blocks b ON r.block_id = b.id
            WHERE {$whereSql}
            ORDER BY 
                (
                    (CASE WHEN r.district_id IS NOT NULL THEN 20 ELSE 0 END) +
                    (CASE WHEN r.block_id IS NOT NULL THEN 10 ELSE 0 END) +
                    (CASE WHEN r.application_code IS NOT NULL AND r.application_code != '' THEN 5 ELSE 0 END)
                ) DESC,
                r.id DESC
        ");
        $rStmt->execute($queryParams);
        $rules = $rStmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch all active districts with state
        $districts = $db->query("SELECT id, name, name_hi, code, state FROM districts WHERE is_active = 1 ORDER BY state ASC, name ASC")->fetchAll(PDO::FETCH_ASSOC);

        // Distinct states
        $states = $db->query("SELECT DISTINCT state FROM districts WHERE state IS NOT NULL AND state != '' ORDER BY state ASC")->fetchAll(PDO::FETCH_COLUMN);
        if (empty($states)) {
            $states = ['Bihar'];
        }

        // Preload blocks for preselected district if any
        $preloadedBlocks = [];
        if ($preselectedDistrict > 0) {
            $bStmt = $db->prepare("SELECT id, name, name_hi, code FROM blocks WHERE district_id = ? AND is_active = 1 ORDER BY name ASC");
            $bStmt->execute([$preselectedDistrict]);
            $preloadedBlocks = $bStmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $fields = $db->prepare("SELECT id, field_name, label_en FROM data_type_fields WHERE data_type_id = ? ORDER BY sort_order ASC");
        $fields->execute([$dataTypeId]);
        $dataTypeFields = $fields->fetchAll(PDO::FETCH_ASSOC);

        // Predefined application codes for srv_block_app or standard government services
        $appCodes = [
            'BICCO' => 'Income Certificate (BICCO)',
            'BCCCO' => 'Caste Certificate (BCCCO)',
            'BRCCO' => 'Residential Certificate (BRCCO)',
            'BOBC' => 'OBC / Backward Class Certificate (BOBC)',
            'NCLCO' => 'Non-Creamy Layer (NCLCO)',
            'RTPS' => 'RTPS General Certificate',
            'OTHER' => 'Other Citizen Service',
            'JAAT' => 'Caste Certificate (BICC/JAAT)',
            'NIWAS' => 'Residence Certificate (BIRC/NIWAS)',
            'AAY' => 'Income Certificate (BIIC/AAY)',
            'EWS' => 'EWS Certificate (BIEW)',
            'NON_CREAMY' => 'Non-Creamy Layer (BINC)',
            'BOCW' => 'BOCW Labour Card',
            'EPDS' => 'Ration Card / EPDS',
            'MUTATION' => 'Dakhil Kharij / Mutation',
            'LPC' => 'Land Possession Certificate (LPC)',
            'CHAR_CERT' => 'Character Certificate'
        ];

        require dirname(__DIR__) . '/Views/admin/service_rate_rules.php';
    }

    /**
     * Dedicated method: Add new State, District, and/or Block directly from Service Pricing Screen (Separate Workflow)
     */
    public function addLocationBlock(): void
    {
        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
            || (isset($_POST['is_ajax']) && $_POST['is_ajax'] === '1')
            || (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json'));

        if (!AuthService::isAdmin()) {
            if ($isAjax) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Admin authorization required.']);
                exit;
            }
            $_SESSION['flash_error'] = 'Unauthorized.';
            header('Location: /login');
            exit;
        }

        if (!AuthService::validateCsrfToken($_POST['csrf_token'] ?? '')) {
            if ($isAjax) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid CSRF token.']);
                exit;
            }
            $_SESSION['flash_error'] = 'Invalid CSRF token.';
            header('Location: /admin/data-types');
            exit;
        }

        $dataTypeId = (int)($_POST['data_type_id'] ?? 0);
        $state = trim($_POST['state'] ?? 'Bihar') ?: 'Bihar';
        $districtId = (int)($_POST['district_id'] ?? 0);
        $newDistrictName = trim($_POST['new_district_name'] ?? '');
        $newDistrictNameHi = trim($_POST['new_district_name_hi'] ?? '') ?: $newDistrictName;
        $newDistrictCode = strtoupper(trim($_POST['new_district_code'] ?? ''));

        $blockName = trim($_POST['block_name'] ?? '');
        $blockNameHi = trim($_POST['block_name_hi'] ?? '') ?: $blockName;
        $blockCode = strtoupper(trim($_POST['block_code'] ?? ''));
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        $db = Database::getConnection();

        try {
            if (empty($blockName) || strlen($blockName) < 2) {
                throw new Exception("Block Name is required (minimum 2 characters).");
            }

            $db->beginTransaction();

            // Handle new district creation if requested
            if ($districtId <= 0) {
                if (empty($newDistrictName)) {
                    throw new Exception("Please select an existing district or enter a name for the new district.");
                }

                // Check if district already exists in this state (do not incorrectly merge districts across different states)
                $chkDist = $db->prepare("SELECT id, name FROM districts WHERE state = ? AND LOWER(TRIM(name)) = LOWER(TRIM(?)) LIMIT 1");
                $chkDist->execute([$state, $newDistrictName]);
                $existingDist = $chkDist->fetch(PDO::FETCH_ASSOC);

                if ($existingDist) {
                    $districtId = (int)$existingDist['id'];
                } else {
                    if (empty($newDistrictCode)) {
                        $newDistrictCode = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $newDistrictName));
                        $baseDistCode = $newDistrictCode;
                        $suffix = 1;
                        while ($db->query("SELECT COUNT(*) FROM districts WHERE code = " . $db->quote($newDistrictCode))->fetchColumn() > 0) {
                            $newDistrictCode = $baseDistCode . '_' . $suffix++;
                        }
                    }

                    $insDist = $db->prepare("INSERT INTO districts (state, name, name_hi, code, is_active, created_at) VALUES (?, ?, ?, ?, 1, NOW())");
                    $insDist->execute([$state, $newDistrictName, $newDistrictNameHi, $newDistrictCode]);
                    $districtId = (int)$db->lastInsertId();

                    AuditService::log('DISTRICT_CREATED', 'DISTRICT', (string)$districtId, [
                        'name' => $newDistrictName,
                        'state' => $state,
                        'source' => 'service_pricing_screen'
                    ]);
                }
            }

            // Check duplicate block under this district (case-insensitive)
            $chkBlock = $db->prepare("SELECT id, name, is_active FROM blocks WHERE district_id = ? AND LOWER(TRIM(name)) = LOWER(TRIM(?)) LIMIT 1");
            $chkBlock->execute([$districtId, $blockName]);
            $existingBlock = $chkBlock->fetch(PDO::FETCH_ASSOC);

            if ($existingBlock) {
                throw new Exception("Duplicate Block: A block named '{$existingBlock['name']}' already exists in this district (Block ID #{$existingBlock['id']}).");
            }

            // Generate block code if empty
            if (empty($blockCode)) {
                $blockCode = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $blockName));
                if (empty($blockCode)) {
                    $blockCode = 'BLK_' . time();
                }
            }

            // Ensure code uniqueness in district
            $baseCode = $blockCode;
            $codeSuffix = 1;
            while ($db->query("SELECT COUNT(*) FROM blocks WHERE district_id = {$districtId} AND code = " . $db->quote($blockCode))->fetchColumn() > 0) {
                $blockCode = $baseCode . '_' . $codeSuffix++;
            }

            // Insert new block
            $insBlock = $db->prepare("
                INSERT INTO blocks (district_id, name, name_hi, code, is_active, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $insBlock->execute([$districtId, $blockName, $blockNameHi, $blockCode, $isActive]);
            $blockId = (int)$db->lastInsertId();

            AuditService::log('LOCATION_BLOCK_CREATED', 'BLOCK', (string)$blockId, [
                'name' => $blockName,
                'district_id' => $districtId,
                'state' => $state,
                'service_id' => $dataTypeId,
                'source' => 'service_pricing_screen'
            ]);

            $db->commit();

            // Fetch district name for response
            $distName = $db->query("SELECT name FROM districts WHERE id = {$districtId}")->fetchColumn() ?: '';
            $workflowAction = trim($_POST['workflow_action'] ?? 'save_block_only');

            if ($isAjax) {
                echo json_encode([
                    'success' => true,
                    'message' => "Block '{$blockName}' added successfully and linked to District '{$distName}'.",
                    'workflow_action' => $workflowAction,
                    'block' => [
                        'id' => $blockId,
                        'name' => $blockName,
                        'name_hi' => $blockNameHi,
                        'code' => $blockCode,
                        'district_id' => $districtId,
                        'district_name' => $distName,
                        'state' => $state,
                        'is_active' => $isActive
                    ]
                ]);
                exit;
            }

            $_SESSION['flash_success'] = "Block '{$blockName}' added successfully and linked to District '{$distName}'.";
            if ($workflowAction === 'save_and_configure_rate') {
                header("Location: /admin/block-rates?selected_district={$districtId}&selected_block={$blockId}&open_modal=rateRuleModal#rateRuleModal");
            } else {
                header("Location: /admin/block-rates?filter_district={$districtId}&filter_block={$blockId}");
            }
            exit;

        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            if ($isAjax) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                exit;
            }

            $_SESSION['flash_error'] = 'Error: ' . $e->getMessage();
            header("Location: /admin/data-types/rate-rules?id={$dataTypeId}");
            exit;
        }
    }

    public function saveRateRule(): void
    {
        if (!AuthService::isAdmin()) {
            $_SESSION['flash_error'] = 'Access denied: Admin privileges required.';
            header('Location: /login');
            exit;
        }

        if (!AuthService::validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $_SESSION['flash_error'] = 'Invalid CSRF token.';
            header('Location: /admin/data-types');
            exit;
        }

        $id = (int)($_POST['id'] ?? 0);
        $dataTypeId = (int)($_POST['data_type_id'] ?? 0);
        $state = trim($_POST['state'] ?? 'Bihar') ?: 'Bihar';
        $districtId = !empty($_POST['district_id']) ? (int)$_POST['district_id'] : null;
        $blockId = !empty($_POST['block_id']) ? (int)$_POST['block_id'] : null;
        $condField = trim($_POST['condition_field'] ?? '') ?: null;
        $condValue = trim($_POST['condition_value'] ?? '') ?: null;
        $adjType = in_array($_POST['adjustment_type'] ?? '', ['FIXED', 'ADD', 'SUBTRACT'], true) ? $_POST['adjustment_type'] : 'FIXED';
        $feeAmount = (float)($_POST['fee_amount'] ?? 0.00);
        $distComm = (isset($_POST['distributor_commission']) && trim((string)$_POST['distributor_commission']) !== '')
            ? (float)$_POST['distributor_commission']
            : null;
        $ruleLabel = trim($_POST['rule_label'] ?? '') ?: 'Location & Application Rate';
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        // Combined Workflow check: Admin opted to create a new block directly in this rate rule
        $createNewBlock = (isset($_POST['create_new_block']) && $_POST['create_new_block'] === '1');
        $newBlockName = trim($_POST['new_block_name'] ?? '');
        $newBlockNameHi = trim($_POST['new_block_name_hi'] ?? '') ?: $newBlockName;
        $newBlockCode = strtoupper(trim($_POST['new_block_code'] ?? ''));

        // Multiple Application Codes support
        $isMultiCode = (isset($_POST['rule_mode']) && $_POST['rule_mode'] === 'multi')
            || (!empty($_POST['multi_app_codes']) && is_array($_POST['multi_app_codes']));

        $db = Database::getConnection();

        try {
            $db->beginTransaction();

            // 1. If combined workflow is used, create the new block first
            if ($createNewBlock) {
                if (empty($newBlockName) || strlen($newBlockName) < 2) {
                    throw new Exception("Block Name is required (minimum 2 characters) when creating a new block.");
                }

                if (!$districtId || $districtId <= 0) {
                    // Check if new district name is given
                    $newDistName = trim($_POST['new_district_name'] ?? '');
                    if (!empty($newDistName)) {
                        $chkDist = $db->prepare("SELECT id FROM districts WHERE LOWER(TRIM(name)) = LOWER(TRIM(?)) LIMIT 1");
                        $chkDist->execute([$newDistName]);
                        $existingD = $chkDist->fetch(PDO::FETCH_ASSOC);
                        if ($existingD) {
                            $districtId = (int)$existingD['id'];
                        } else {
                            $distCode = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $newDistName));
                            $db->prepare("INSERT INTO districts (state, name, name_hi, code, is_active, created_at) VALUES (?, ?, ?, ?, 1, NOW())")
                               ->execute([$state, $newDistName, $newDistName, $distCode]);
                            $districtId = (int)$db->lastInsertId();
                            AuditService::log('DISTRICT_CREATED', 'DISTRICT', (string)$districtId, ['name' => $newDistName, 'state' => $state]);
                        }
                    } else {
                        throw new Exception("Please select a District for the new block.");
                    }
                }

                // Check duplicate block under this district
                $chkBlock = $db->prepare("SELECT id, name FROM blocks WHERE district_id = ? AND LOWER(TRIM(name)) = LOWER(TRIM(?)) LIMIT 1");
                $chkBlock->execute([$districtId, $newBlockName]);
                $existingBlock = $chkBlock->fetch(PDO::FETCH_ASSOC);

                if ($existingBlock) {
                    throw new Exception("Duplicate Block: A block named '{$existingBlock['name']}' already exists in this district (Block ID #{$existingBlock['id']}). Please select it from the dropdown instead.");
                }

                if (empty($newBlockCode)) {
                    $newBlockCode = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $newBlockName));
                    if (empty($newBlockCode)) $newBlockCode = 'BLK_' . time();
                }

                $insB = $db->prepare("INSERT INTO blocks (district_id, name, name_hi, code, is_active, created_at) VALUES (?, ?, ?, ?, 1, NOW())");
                $insB->execute([$districtId, $newBlockName, $newBlockNameHi, $newBlockCode]);
                $blockId = (int)$db->lastInsertId();

                AuditService::log('LOCATION_BLOCK_CREATED', 'BLOCK', (string)$blockId, [
                    'name' => $newBlockName,
                    'district_id' => $districtId,
                    'state' => $state,
                    'service_id' => $dataTypeId,
                    'combined_workflow' => true
                ]);
            }

            // 2. Insert or update rate rule(s)
            if ($isMultiCode && $id === 0) {
                // Batch insert for multiple application codes
                $selectedCodes = $_POST['multi_app_codes'] ?? [];
                if (empty($selectedCodes) || !is_array($selectedCodes)) {
                    throw new Exception("Please select at least one Application Code for multiple code pricing.");
                }

                $createdCount = 0;
                $updatedCount = 0;

                foreach ($selectedCodes as $code) {
                    $code = strtoupper(trim((string)$code));
                    if (empty($code)) continue;

                    // Per-code fee override or default fee
                    $codeFee = isset($_POST['fee_amount_' . $code]) && is_numeric($_POST['fee_amount_' . $code])
                        ? (float)$_POST['fee_amount_' . $code]
                        : $feeAmount;

                    $codeComm = isset($_POST['comm_' . $code]) && is_numeric($_POST['comm_' . $code])
                        ? (float)$_POST['comm_' . $code]
                        : $distComm;

                    $codeLabel = !empty($_POST['rule_label'])
                        ? trim($_POST['rule_label']) . " - {$code}"
                        : "{$code} Rate Rule";

                    // Check conflicting rule for this code
                    $chkParams = [$dataTypeId];
                    $chkWhere = ["data_type_id = ?"];

                    if (!empty($state)) {
                        $chkWhere[] = "state = ?";
                        $chkParams[] = $state;
                    } else {
                        $chkWhere[] = "(state IS NULL OR state = '')";
                    }

                    if ($districtId !== null && $districtId > 0) {
                        $chkWhere[] = "district_id = ?";
                        $chkParams[] = $districtId;
                    } else {
                        $chkWhere[] = "district_id IS NULL";
                    }

                    if ($blockId !== null && $blockId > 0) {
                        $chkWhere[] = "block_id = ?";
                        $chkParams[] = $blockId;
                    } else {
                        $chkWhere[] = "block_id IS NULL";
                    }

                    $chkWhere[] = "application_code = ?";
                    $chkParams[] = $code;

                    $conflictStmt = $db->prepare("SELECT id, fee_amount, rule_label FROM service_rate_rules WHERE " . implode(" AND ", $chkWhere) . " LIMIT 1");
                    $conflictStmt->execute($chkParams);
                    $existingRule = $conflictStmt->fetch(PDO::FETCH_ASSOC);

                    $allowOverwrite = isset($_POST['allow_overwrite']) && ($_POST['allow_overwrite'] === '1' || $_POST['allow_overwrite'] === 'true');

                    if ($existingRule) {
                        if ($allowOverwrite) {
                            $upd = $db->prepare("
                                UPDATE service_rate_rules
                                SET fee_amount = ?, distributor_commission = ?, rule_label = ?, adjustment_type = ?, is_active = ?, updated_at = NOW()
                                WHERE id = ?
                            ");
                            $upd->execute([$codeFee, $codeComm, $codeLabel, $adjType, $isActive, $existingRule['id']]);
                            AuditService::log('RATE_RULE_UPDATED', 'RATE_RULE', (string)$existingRule['id'], ['code' => $code, 'data_type_id' => $dataTypeId]);
                            $updatedCount++;
                        } else {
                            $skippedExisting[] = "{$code} (Existing Rate: ₹" . number_format((float)$existingRule['fee_amount'], 2) . ")";
                        }
                    } else {
                        // Insert new rule
                        $ins = $db->prepare("
                            INSERT INTO service_rate_rules (
                                data_type_id, state, district_id, block_id, application_code,
                                condition_field, condition_value, adjustment_type, fee_amount,
                                distributor_commission, rule_label, is_active, created_at
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                        ");
                        $ins->execute([
                            $dataTypeId, $state, $districtId, $blockId, $code,
                            $condField, $condValue, $adjType, $codeFee,
                            $codeComm, $codeLabel, $isActive
                        ]);
                        AuditService::log('RATE_RULE_CREATED', 'RATE_RULE', (string)$db->lastInsertId(), ['code' => $code, 'data_type_id' => $dataTypeId]);
                        $createdCount++;
                    }
                }

                // Create block service availability record if missing (default to LIVE)
                if ($districtId !== null && $districtId > 0 && $blockId !== null && $blockId > 0) {
                    $chkSba = $db->prepare("SELECT id, status FROM service_block_availability WHERE data_type_id = ? AND district_id = ? AND block_id = ? LIMIT 1");
                    $chkSba->execute([$dataTypeId, $districtId, $blockId]);
                    if (!$chkSba->fetch()) {
                        $insSba = $db->prepare("
                            INSERT INTO service_block_availability (
                                data_type_id, district_id, block_id, status, activated_at, activated_by, created_at, updated_at
                            ) VALUES (?, ?, ?, 'LIVE', NOW(), ?, NOW(), NOW())
                        ");
                        $insSba->execute([$dataTypeId, $districtId, $blockId, AuthService::id() ?: 1]);
                        AuditService::log('BLOCK_SERVICE_ACTIVATED', 'BLOCK_SERVICE', (string)$blockId, [
                            'data_type_id' => $dataTypeId,
                            'district_id' => $districtId,
                            'block_id' => $blockId,
                            'status' => 'LIVE',
                            'reason' => 'Auto-created on first block rate addition'
                        ]);
                    }
                }

                $db->commit();
                $flashMsg = "Saved {$createdCount} new rate rule(s).";
                if ($updatedCount > 0) {
                    $flashMsg .= " Updated {$updatedCount} existing rule(s).";
                }
                if (!empty($skippedExisting)) {
                    $flashMsg .= " Existing rates preserved without overwrite: " . implode(', ', $skippedExisting) . ". Check 'Overwrite Existing Rates' if you wish to modify them.";
                }
                $_SESSION['flash_success'] = $flashMsg;

            } else {
                // Single code rule or wildcard rule
                $appCode = trim($_POST['application_code'] ?? '') ?: null;

                // Explicit prepared conflict check
                $chkParams = [$dataTypeId];
                $chkWhere = ["data_type_id = ?"];

                if (!empty($state)) {
                    $chkWhere[] = "state = ?";
                    $chkParams[] = $state;
                } else {
                    $chkWhere[] = "(state IS NULL OR state = '')";
                }

                if ($districtId !== null && $districtId > 0) {
                    $chkWhere[] = "district_id = ?";
                    $chkParams[] = $districtId;
                } else {
                    $chkWhere[] = "district_id IS NULL";
                }

                if ($blockId !== null && $blockId > 0) {
                    $chkWhere[] = "block_id = ?";
                    $chkParams[] = $blockId;
                } else {
                    $chkWhere[] = "block_id IS NULL";
                }

                if ($appCode !== null && $appCode !== '') {
                    $chkWhere[] = "application_code = ?";
                    $chkParams[] = $appCode;
                } else {
                    $chkWhere[] = "(application_code IS NULL OR application_code = '')";
                }

                if ($condField !== null && $condField !== '') {
                    $chkWhere[] = "condition_field = ? AND condition_value = ?";
                    $chkParams[] = $condField;
                    $chkParams[] = $condValue;
                } else {
                    $chkWhere[] = "condition_field IS NULL";
                }

                if ($id > 0) {
                    $chkWhere[] = "id != ?";
                    $chkParams[] = $id;
                }

                $conflictStmt = $db->prepare("SELECT id, rule_label, fee_amount FROM service_rate_rules WHERE " . implode(" AND ", $chkWhere) . " LIMIT 1");
                $conflictStmt->execute($chkParams);
                $conflict = $conflictStmt->fetch(PDO::FETCH_ASSOC);

                if ($conflict) {
                    throw new Exception("Conflict error: A rate rule ('{$conflict['rule_label']}') already exists with fee ₹" . number_format((float)$conflict['fee_amount'], 2) . " for this location and code (Rule ID #{$conflict['id']}). Please edit that rule instead of creating a conflicting duplicate.");
                }

                if ($id > 0) {
                    $updStmt = $db->prepare("
                        UPDATE service_rate_rules
                        SET state = ?, district_id = ?, block_id = ?, application_code = ?,
                            condition_field = ?, condition_value = ?, adjustment_type = ?,
                            fee_amount = ?, distributor_commission = ?, rule_label = ?,
                            is_active = ?, updated_at = NOW()
                        WHERE id = ? AND data_type_id = ?
                    ");
                    $updStmt->execute([
                        $state, $districtId, $blockId, $appCode,
                        $condField, $condValue, $adjType,
                        $feeAmount, $distComm, $ruleLabel,
                        $isActive, $id, $dataTypeId
                    ]);
                    AuditService::log('RATE_RULE_UPDATED', 'RATE_RULE', (string)$id, ['label' => $ruleLabel, 'data_type_id' => $dataTypeId]);
                    $_SESSION['flash_success'] = 'Rate rule updated successfully.';
                } else {
                    $stmt = $db->prepare("
                        INSERT INTO service_rate_rules (
                            data_type_id, state, district_id, block_id, application_code,
                            condition_field, condition_value, adjustment_type, fee_amount,
                            distributor_commission, rule_label, is_active, created_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([
                        $dataTypeId, $state, $districtId, $blockId, $appCode,
                        $condField, $condValue, $adjType, $feeAmount,
                        $distComm, $ruleLabel, $isActive
                    ]);

                    AuditService::log('RATE_RULE_CREATED', 'RATE_RULE', (string)$db->lastInsertId(), ['label' => $ruleLabel, 'data_type_id' => $dataTypeId]);
                    $_SESSION['flash_success'] = 'Rate rule created successfully.';
                }

                // Create block service availability record if missing (default to LIVE)
                if ($districtId !== null && $districtId > 0 && $blockId !== null && $blockId > 0) {
                    $chkSba = $db->prepare("SELECT id, status FROM service_block_availability WHERE data_type_id = ? AND district_id = ? AND block_id = ? LIMIT 1");
                    $chkSba->execute([$dataTypeId, $districtId, $blockId]);
                    if (!$chkSba->fetch()) {
                        $insSba = $db->prepare("
                            INSERT INTO service_block_availability (
                                data_type_id, district_id, block_id, status, activated_at, activated_by, created_at, updated_at
                            ) VALUES (?, ?, ?, 'LIVE', NOW(), ?, NOW(), NOW())
                        ");
                        $insSba->execute([$dataTypeId, $districtId, $blockId, AuthService::id() ?: 1]);
                        AuditService::log('BLOCK_SERVICE_ACTIVATED', 'BLOCK_SERVICE', (string)$blockId, [
                            'data_type_id' => $dataTypeId,
                            'district_id' => $districtId,
                            'block_id' => $blockId,
                            'status' => 'LIVE',
                            'reason' => 'Auto-created on first block rate addition'
                        ]);
                    }
                }

                $db->commit();
            }

        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $_SESSION['flash_error'] = 'Error: ' . $e->getMessage();
        }

        $redirectQuery = http_build_query([
            'id' => $dataTypeId,
            'selected_district' => $districtId,
            'selected_block' => $blockId
        ]);
        header('Location: /admin/data-types/rate-rules?' . $redirectQuery);
        exit;
    }

    public function toggleRateRule(): void
    {
        if (!AuthService::isAdmin()) {
            $_SESSION['flash_error'] = 'Access denied: Admin privileges required.';
            header('Location: /login');
            exit;
        }

        if (!AuthService::validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $_SESSION['flash_error'] = 'Invalid CSRF token.';
            header('Location: /admin/data-types');
            exit;
        }

        $ruleId = (int)($_POST['rule_id'] ?? 0);
        $dataTypeId = (int)($_POST['data_type_id'] ?? 0);

        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT id, is_active, rule_label FROM service_rate_rules WHERE id = ? AND data_type_id = ?");
        $stmt->execute([$ruleId, $dataTypeId]);
        $rule = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($rule) {
            $newStatus = $rule['is_active'] ? 0 : 1;
            $db->prepare("UPDATE service_rate_rules SET is_active = ?, updated_at = NOW() WHERE id = ?")->execute([$newStatus, $ruleId]);
            AuditService::log('RATE_RULE_TOGGLED', 'RATE_RULE', (string)$ruleId, ['new_status' => $newStatus, 'data_type_id' => $dataTypeId]);
            $_SESSION['flash_success'] = "Rate rule '{$rule['rule_label']}' is now " . ($newStatus ? 'Active' : 'Disabled') . ".";
        }

        header('Location: /admin/data-types/rate-rules?id=' . $dataTypeId);
        exit;
    }

    public function deleteRateRule(): void
    {
        if (!AuthService::isAdmin()) {
            $_SESSION['flash_error'] = 'Access denied: Admin privileges required.';
            header('Location: /login');
            exit;
        }

        if (!AuthService::validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $_SESSION['flash_error'] = 'Invalid CSRF token.';
            header('Location: /admin/data-types');
            exit;
        }

        $ruleId = (int)($_POST['rule_id'] ?? 0);
        $dataTypeId = (int)($_POST['data_type_id'] ?? 0);

        $db = Database::getConnection();
        $db->prepare("DELETE FROM service_rate_rules WHERE id = ? AND data_type_id = ?")->execute([$ruleId, $dataTypeId]);

        AuditService::log('RATE_RULE_DELETED', 'RATE_RULE', (string)$ruleId, ['data_type_id' => $dataTypeId]);
        $_SESSION['flash_success'] = 'Rate rule deleted.';

        header('Location: /admin/data-types/rate-rules?id=' . $dataTypeId);
        exit;
    }

    /**
     * Save admin-configurable standard fallback rates in settings
     */
    public function saveFallbackRates(): void
    {
        if (!AuthService::isAdmin()) {
            $_SESSION['flash_error'] = 'Access denied: Admin privileges required.';
            header('Location: /login');
            exit;
        }

        if (!AuthService::validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $_SESSION['flash_error'] = 'Invalid CSRF token.';
            header('Location: /admin/block-rates');
            exit;
        }

        $standardFee = max(0.00, (float)($_POST['fallback_rate_standard'] ?? 100.00));
        $otherFee = max(0.00, (float)($_POST['fallback_rate_other'] ?? 130.00));

        $db = Database::getConnection();

        $stmt = $db->prepare("
            INSERT INTO settings (setting_key, setting_value, description)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()
        ");
        $stmt->execute(['fallback_rate_standard', number_format($standardFee, 2, '.', ''), 'Standard Fallback Rate for BICCO, BCCCO, BRCCO']);
        $stmt->execute(['fallback_rate_other', number_format($otherFee, 2, '.', ''), 'Fallback Rate for BOBC, NCLCO, OTHER']);

        AuditService::log('FALLBACK_RATES_UPDATED', 'SETTINGS', 'fallback_rates', [
            'standard' => $standardFee,
            'other' => $otherFee
        ]);

        $_SESSION['flash_success'] = "Standard fallback rates updated successfully (BICCO/BCCCO/BRCCO: ₹" . number_format($standardFee, 2) . ", BOBC/NCLCO/OTHER: ₹" . number_format($otherFee, 2) . ").";
        header('Location: /admin/block-rates');
        exit;
    }

    /**
     * Fast inline quick rate update via AJAX from Block Rates table
     */
    public function quickUpdateRate(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        if (!AuthService::isAdmin()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit;
        }

        if (!AuthService::validateCsrfToken($_POST['csrf_token'] ?? '')) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
            exit;
        }

        $ruleId = (int)($_POST['rule_id'] ?? 0);
        $newPrice = (float)($_POST['price'] ?? 0.00);

        if ($ruleId <= 0 || $newPrice < 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid rule ID or rate price']);
            exit;
        }

        $db = Database::getConnection();
        $stmt = $db->prepare("UPDATE service_rate_rules SET fee_amount = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$newPrice, $ruleId]);

        AuditService::log('RATE_RULE_QUICK_UPDATED', 'RATE_RULE', (string)$ruleId, ['new_price' => $newPrice]);

        echo json_encode([
            'success' => true,
            'message' => 'Custom rate updated to ₹' . number_format($newPrice, 2),
            'rule_id' => $ruleId,
            'new_price' => $newPrice
        ]);
        exit;
    }

    // Dynamic Fields Management
    public function fields(): void
    {
        $dataTypeId = (int)($_GET['id'] ?? 0);
        $db = Database::getConnection();

        $stmt = $db->prepare("SELECT * FROM data_types WHERE id = ?");
        $stmt->execute([$dataTypeId]);
        $dataType = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$dataType) {
            $_SESSION['flash_error'] = 'Service not found.';
            header('Location: /admin/data-types');
            exit;
        }

        $fStmt = $db->prepare("SELECT * FROM data_type_fields WHERE data_type_id = ? ORDER BY sort_order ASC, id ASC");
        $fStmt->execute([$dataTypeId]);
        $fields = $fStmt->fetchAll(PDO::FETCH_ASSOC);

        require dirname(__DIR__) . '/Views/admin/data_type_fields.php';
    }

    public function saveField(): void
    {
        if (!AuthService::validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $_SESSION['flash_error'] = 'Invalid CSRF token.';
            header('Location: /admin/data-types');
            exit;
        }

        $id = (int)($_POST['id'] ?? 0);
        $dataTypeId = (int)($_POST['data_type_id'] ?? 0);
        $fieldName = strtolower(trim(preg_replace('/[^a-zA-Z0-9_]/', '', $_POST['field_name'] ?? '')));
        $labelEn = trim($_POST['label_en'] ?? '');
        $labelHi = trim($_POST['label_hi'] ?? '') ?: $labelEn;
        $fieldType = trim($_POST['field_type'] ?? 'text');
        $placeholderEn = trim($_POST['placeholder_en'] ?? '');
        $placeholderHi = trim($_POST['placeholder_hi'] ?? '') ?: $placeholderEn;
        $helpTextEn = trim($_POST['help_text_en'] ?? '');
        $helpTextHi = trim($_POST['help_text_hi'] ?? '') ?: $helpTextEn;
        $optionsRaw = trim($_POST['options_raw'] ?? '');
        $fileTypes = trim($_POST['file_types'] ?? 'pdf,jpg,jpeg,png');
        $maxSizeMb = max(1, (int)($_POST['max_size_mb'] ?? 2));
        $isRequired = isset($_POST['is_required']) ? 1 : 0;
        $isUnique = isset($_POST['is_unique']) ? 1 : 0;
        $validationPattern = trim($_POST['validation_pattern'] ?? '');
        $sortOrder = (int)($_POST['sort_order'] ?? 0);

        // Parse options if provided
        $optionsJson = null;
        if (!empty($optionsRaw)) {
            $parsedOptions = [];
            $lines = explode("\n", str_replace("\r", "", $optionsRaw));
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) continue;
                $parts = explode('|', $line);
                $val = trim($parts[0]);
                $lblEn = trim($parts[1] ?? $val);
                $lblHi = trim($parts[2] ?? $lblEn);
                $parsedOptions[] = ['value' => $val, 'label_en' => $lblEn, 'label_hi' => $lblHi];
            }
            if (!empty($parsedOptions)) {
                $optionsJson = json_encode($parsedOptions, JSON_UNESCAPED_UNICODE);
            }
        }

        $db = Database::getConnection();

        try {
            // 1. Verify parent Data Type exists
            $dtStmt = $db->prepare("SELECT id FROM data_types WHERE id = ?");
            $dtStmt->execute([$dataTypeId]);
            if (!$dtStmt->fetch()) {
                throw new Exception("Parent Service not found.");
            }

            // 2. Validate field inputs
            if (empty($fieldName) || !preg_match('/^[a-z0-9_]{2,64}$/', $fieldName)) {
                throw new Exception("Field name must be 2-64 lowercase alphanumeric and underscore characters (e.g. 'aadhaar_no').");
            }
            if (empty($labelEn)) {
                throw new Exception("Field label (English) is required.");
            }

            // 3. Validate allowed field type
            $allowedTypes = ['text', 'number', 'date', 'select', 'textarea', 'email', 'tel', 'file'];
            if (!in_array($fieldType, $allowedTypes, true)) {
                throw new Exception("Invalid field type '{$fieldType}'. Allowed types: " . implode(', ', $allowedTypes));
            }

            // 4. Validate regex pattern syntax if provided
            if (!empty($validationPattern)) {
                if (@preg_match('/' . $validationPattern . '/', '') === false) {
                    throw new Exception("Invalid regular expression syntax in validation pattern.");
                }
            }

            // 5. Select field validation
            if ($fieldType === 'select' && empty($optionsJson)) {
                throw new Exception("Select fields require at least one configured option.");
            }

            if ($id > 0) {
                $ownStmt = $db->prepare("SELECT id FROM data_type_fields WHERE id = ? AND data_type_id = ?");
                $ownStmt->execute([$id, $dataTypeId]);
                if (!$ownStmt->fetch()) {
                    throw new Exception("Field ownership verification failed.");
                }

                $stmt = $db->prepare("
                    UPDATE data_type_fields
                    SET field_name = ?, label_en = ?, label_hi = ?, field_type = ?,
                        placeholder_en = ?, placeholder_hi = ?, help_text_en = ?, help_text_hi = ?,
                        options_json = ?, file_types = ?, max_size_mb = ?,
                        is_required = ?, is_unique = ?, validation_pattern = ?,
                        sort_order = ?, updated_at = NOW()
                    WHERE id = ? AND data_type_id = ?
                ");
                $stmt->execute([
                    $fieldName, $labelEn, $labelHi, $fieldType,
                    $placeholderEn, $placeholderHi, $helpTextEn, $helpTextHi,
                    $optionsJson, $fileTypes, $maxSizeMb,
                    $isRequired, $isUnique, $validationPattern,
                    $sortOrder, $id, $dataTypeId
                ]);
                AuditService::log('FIELD_UPDATED', 'FIELD', (string)$id, ['field_name' => $fieldName, 'data_type_id' => $dataTypeId]);
                $_SESSION['flash_success'] = 'Field updated successfully.';
            } else {
                $stmt = $db->prepare("
                    INSERT INTO data_type_fields (
                        data_type_id, field_name, label_en, label_hi, field_type,
                        placeholder_en, placeholder_hi, help_text_en, help_text_hi,
                        options_json, file_types, max_size_mb,
                        is_required, is_unique, validation_pattern,
                        sort_order, created_at
                    ) VALUES (
                        ?, ?, ?, ?, ?,
                        ?, ?, ?, ?,
                        ?, ?, ?,
                        ?, ?, ?,
                        ?, NOW()
                    )
                ");
                $stmt->execute([
                    $dataTypeId, $fieldName, $labelEn, $labelHi, $fieldType,
                    $placeholderEn, $placeholderHi, $helpTextEn, $helpTextHi,
                    $optionsJson, $fileTypes, $maxSizeMb,
                    $isRequired, $isUnique, $validationPattern,
                    $sortOrder
                ]);
                AuditService::log('FIELD_CREATED', 'FIELD', (string)$db->lastInsertId(), ['field_name' => $fieldName, 'data_type_id' => $dataTypeId]);
                $_SESSION['flash_success'] = 'Field added successfully.';
            }
        } catch (Exception $e) {
            $_SESSION['flash_error'] = 'Error: ' . $e->getMessage();
        }

        header('Location: /admin/data-types/fields?id=' . $dataTypeId);
        exit;
    }

    public function deleteField(): void
    {
        if (!AuthService::validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $_SESSION['flash_error'] = 'Invalid CSRF token.';
            header('Location: /admin/data-types');
            exit;
        }

        $fieldId = (int)($_POST['field_id'] ?? 0);
        $dataTypeId = (int)($_POST['data_type_id'] ?? 0);

        $db = Database::getConnection();

        $ownStmt = $db->prepare("SELECT id FROM data_type_fields WHERE id = ? AND data_type_id = ?");
        $ownStmt->execute([$fieldId, $dataTypeId]);
        if (!$ownStmt->fetch()) {
            $_SESSION['flash_error'] = 'Field does not belong to this service.';
            header('Location: /admin/data-types/fields?id=' . $dataTypeId);
            exit;
        }

        $db->prepare("DELETE FROM data_type_fields WHERE id = ? AND data_type_id = ?")->execute([$fieldId, $dataTypeId]);

        AuditService::log('FIELD_DELETED', 'FIELD', (string)$fieldId, ['data_type_id' => $dataTypeId]);
        $_SESSION['flash_success'] = 'Field deleted.';

        header('Location: /admin/data-types/fields?id=' . $dataTypeId);
        exit;
    }
}
