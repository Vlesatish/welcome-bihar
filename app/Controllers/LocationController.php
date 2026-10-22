<?php
namespace App\Controllers;

use App\Config\Database;
use App\Services\AuthService;
use App\Services\CsvService;
use App\Services\AuditService;
use Exception;
use PDO;

class LocationController
{
    /**
     * AJAX endpoint: Fetch blocks for a given district
     */
    public function getBlocks(): void
    {
        header('Content-Type: application/json');

        $districtId = (int)($_GET['district_id'] ?? 0);

        // If user is authenticated in OPERATOR mode or role is OPERATOR, restrict to their assigned blocks
        if (AuthService::check() && (AuthService::activePortalMode() === 'OPERATOR' || (AuthService::user()['role'] ?? '') === 'OPERATOR')) {
            $user = AuthService::user();
            $blocks = AuthService::getOperatorAssignedBlocks((int)$user['id'], $districtId > 0 ? $districtId : null);
            echo json_encode($blocks);
            exit;
        }

        if ($districtId <= 0) {
            echo json_encode([]);
            exit;
        }

        $db = Database::getConnection();

        // For registration (unauthenticated), USER, ADMIN, and DISTRIBUTOR: return all active blocks for the district
        $stmt = $db->prepare("SELECT id, name, name_hi, code FROM blocks WHERE district_id = ? AND is_active = 1 ORDER BY name ASC");
        $stmt->execute([$districtId]);
        $blocks = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode($blocks);
    }

    /**
     * AJAX endpoint: Fetch districts having at least one LIVE block for srv_block_app
     * GET /api/service-districts?service=srv_block_app
     */
    public function getServiceDistricts(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        // Strictly enforce allowed service
        $serviceCode = trim($_GET['service'] ?? 'srv_block_app');
        if ($serviceCode !== 'srv_block_app') {
            http_response_code(400);
            echo json_encode(['error' => 'Unsupported service. Only srv_block_app is supported.']);
            exit;
        }

        $db = Database::getConnection();

        // Check if service itself is active
        $dtStmt = $db->prepare("SELECT id, is_active FROM data_types WHERE code = 'srv_block_app' LIMIT 1");
        $dtStmt->execute();
        $dt = $dtStmt->fetch(PDO::FETCH_ASSOC);
        if (!$dt || (int)$dt['is_active'] !== 1) {
            echo json_encode([]);
            exit;
        }
        $dataTypeId = (int)$dt['id'];

        $stmt = $db->prepare("
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
        $stmt->execute([$dataTypeId]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }

    /**
     * AJAX endpoint: Fetch only LIVE blocks for a given district and srv_block_app that have active block rates
     * GET /api/service-blocks?district_id={id}&service=srv_block_app
     */
    public function getServiceBlocks(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        // Strictly enforce allowed service
        $serviceCode = trim($_GET['service'] ?? 'srv_block_app');
        if ($serviceCode !== 'srv_block_app') {
            http_response_code(400);
            echo json_encode(['error' => 'Unsupported service. Only srv_block_app is supported.']);
            exit;
        }

        $districtId = (int)($_GET['district_id'] ?? 0);
        if ($districtId <= 0) {
            echo json_encode([]);
            exit;
        }

        $db = Database::getConnection();

        // Check if district is active
        $dStmt = $db->prepare("SELECT id, is_active FROM districts WHERE id = ? LIMIT 1");
        $dStmt->execute([$districtId]);
        $district = $dStmt->fetch(PDO::FETCH_ASSOC);
        if (!$district || (int)$district['is_active'] !== 1) {
            echo json_encode([]);
            exit;
        }

        // Check if service itself is active
        $dtStmt = $db->prepare("SELECT id, is_active FROM data_types WHERE code = 'srv_block_app' LIMIT 1");
        $dtStmt->execute();
        $dt = $dtStmt->fetch(PDO::FETCH_ASSOC);
        if (!$dt || (int)$dt['is_active'] !== 1) {
            echo json_encode([]);
            exit;
        }
        $dataTypeId = (int)$dt['id'];

        $stmt = $db->prepare("
            SELECT DISTINCT b.id, b.name, b.name_hi, b.code, b.district_id
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
        $stmt->execute([$districtId, $dataTypeId]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }

    /**
     * AJAX endpoint: Fetch active configured application codes and exact rates for a LIVE block
     * GET /api/service-application-codes?district_id={id}&block_id={id}&service=srv_block_app
     */
    public function getServiceApplicationCodes(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $districtId = (int)($_GET['district_id'] ?? 0);
        $blockId = (int)($_GET['block_id'] ?? 0);
        $serviceCode = trim($_GET['service'] ?? 'srv_block_app');

        if ($districtId <= 0 || $blockId <= 0 || $serviceCode !== 'srv_block_app') {
            echo json_encode([]);
            exit;
        }

        $db = Database::getConnection();

        // 1. Verify service exists and is active
        $dtStmt = $db->prepare("SELECT id, is_active FROM data_types WHERE code = 'srv_block_app' LIMIT 1");
        $dtStmt->execute();
        $dt = $dtStmt->fetch(PDO::FETCH_ASSOC);
        if (!$dt || (int)$dt['is_active'] !== 1) {
            echo json_encode([]);
            exit;
        }
        $dataTypeId = (int)$dt['id'];

        // 2. Verify district is active
        $dStmt = $db->prepare("SELECT id FROM districts WHERE id = ? AND is_active = 1 LIMIT 1");
        $dStmt->execute([$districtId]);
        if (!$dStmt->fetch()) {
            echo json_encode([]);
            exit;
        }

        // 3. Verify block is active and belongs to district
        $bStmt = $db->prepare("SELECT id FROM blocks WHERE id = ? AND district_id = ? AND is_active = 1 LIMIT 1");
        $bStmt->execute([$blockId, $districtId]);
        if (!$bStmt->fetch()) {
            echo json_encode([]);
            exit;
        }

        // 4. Verify block service availability is LIVE
        $sbaStmt = $db->prepare("SELECT status FROM service_block_availability WHERE data_type_id = ? AND district_id = ? AND block_id = ? LIMIT 1");
        $sbaStmt->execute([$dataTypeId, $districtId, $blockId]);
        if ($sbaStmt->fetchColumn() !== 'LIVE') {
            echo json_encode([]);
            exit;
        }

        // 5. Fetch configured active rates for this exact block
        $rStmt = $db->prepare("
            SELECT application_code, fee_amount, rule_label
            FROM service_rate_rules
            WHERE data_type_id = ?
              AND district_id = ?
              AND block_id = ?
              AND is_active = 1
              AND fee_amount > 0
              AND application_code IS NOT NULL
              AND application_code != ''
              AND application_code != '*'
            ORDER BY fee_amount ASC, application_code ASC
        ");
        $rStmt->execute([$dataTypeId, $districtId, $blockId]);
        $rows = $rStmt->fetchAll(PDO::FETCH_ASSOC);

        $appCodeNames = [
            'BICCO' => 'Income Certificate (BICCO)',
            'BCCCO' => 'Caste Certificate (BCCCO)',
            'BRCCO' => 'Residential Certificate (BRCCO)',
            'BOBC'  => 'OBC / Backward Class (BOBC)',
            'NCLCO' => 'Non-Creamy Layer (NCLCO)',
            'RTPS'  => 'RTPS General Service',
            'OTHER' => 'Other Citizen Service',
            'JAAT'  => 'Caste Certificate (JAAT)',
            'NIWAS' => 'Residence Certificate (NIWAS)',
            'AAY'   => 'Income Certificate (AAY)',
            'EWS'   => 'EWS Certificate (EWS)',
            'NON_CREAMY' => 'Non-Creamy Layer (BINC)',
            'BOCW'  => 'BOCW Labour Card',
            'EPDS'  => 'Ration Card / EPDS',
            'MUTATION' => 'Dakhil Kharij / Mutation',
            'LPC'   => 'Land Possession Certificate (LPC)',
            'CHAR_CERT' => 'Character Certificate'
        ];

        $results = [];
        $seen = [];
        foreach ($rows as $r) {
            $code = strtoupper(trim($r['application_code']));
            if (empty($code) || isset($seen[$code])) {
                continue;
            }
            $seen[$code] = true;
            $fee = (float)$r['fee_amount'];
            $label = $appCodeNames[$code] ?? (!empty($r['rule_label']) ? $r['rule_label'] : $code);
            $results[] = [
                'code' => $code,
                'label' => $label,
                'rate' => $fee,
                'rate_formatted' => '₹' . number_format($fee, 2),
                'display' => $code . ' (' . $label . ') - ₹' . number_format($fee, 2)
            ];
        }

        echo json_encode($results);
        exit;
    }


    /**
     * AJAX endpoint: Fetch districts optionally filtered by state
     */
    public function getDistricts(): void
    {
        header('Content-Type: application/json');

        if (!AuthService::check()) {
            http_response_code(401);
            echo json_encode(['error' => 'Authentication required']);
            exit;
        }

        $state = trim($_GET['state'] ?? '');
        $db = Database::getConnection();

        if (!empty($state) && $state !== '*') {
            $stmt = $db->prepare("SELECT id, name, name_hi, code, state FROM districts WHERE state = ? AND is_active = 1 ORDER BY name ASC");
            $stmt->execute([$state]);
        } else {
            $stmt = $db->query("SELECT id, name, name_hi, code, state FROM districts WHERE is_active = 1 ORDER BY name ASC");
        }

        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }

    /**
     * Admin view for Districts & Blocks management
     */
    public function index(): void
    {
        if (!AuthService::isAdmin()) {
            header('Location: /user/dashboard');
            exit;
        }

        $db = Database::getConnection();
        $selectedDistrictId = (int)($_GET['district_id'] ?? 0);

        // Fetch all 38 districts with block count
        $districts = $db->query("
            SELECT d.*, COUNT(b.id) as block_count
            FROM districts d
            LEFT JOIN blocks b ON d.id = b.district_id
            GROUP BY d.id
            ORDER BY d.name ASC
        ")->fetchAll();

        $blocks = [];
        $currentDistrict = null;
        if ($selectedDistrictId > 0) {
            $stmt = $db->prepare("SELECT * FROM districts WHERE id = ?");
            $stmt->execute([$selectedDistrictId]);
            $currentDistrict = $stmt->fetch();

            $bStmt = $db->prepare("SELECT * FROM blocks WHERE district_id = ? ORDER BY name ASC");
            $bStmt->execute([$selectedDistrictId]);
            $blocks = $bStmt->fetchAll();
        }

        require dirname(__DIR__) . '/Views/admin/locations.php';
    }

    public function saveDistrict(): void
    {
        if (!AuthService::isAdmin() || !AuthService::validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $_SESSION['flash_error'] = 'Unauthorized.';
            header('Location: /admin/locations');
            exit;
        }

        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $nameHi = trim($_POST['name_hi'] ?? '') ?: $name;
        $code = strtoupper(trim($_POST['code'] ?? ''));
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        $db = Database::getConnection();
        try {
            if ($id > 0) {
                $stmt = $db->prepare("UPDATE districts SET name = ?, name_hi = ?, code = ?, is_active = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$name, $nameHi, $code, $isActive, $id]);
                AuditService::log('DISTRICT_UPDATED', 'DISTRICT', (string)$id, ['name' => $name]);
                $_SESSION['flash_success'] = 'District updated successfully.';
            } else {
                $stmt = $db->prepare("INSERT INTO districts (name, name_hi, code, is_active, created_at) VALUES (?, ?, ?, ?, NOW())");
                $stmt->execute([$name, $nameHi, $code, $isActive]);
                AuditService::log('DISTRICT_CREATED', 'DISTRICT', (string)$db->lastInsertId(), ['name' => $name]);
                $_SESSION['flash_success'] = 'District created successfully.';
            }
        } catch (Exception $e) {
            $_SESSION['flash_error'] = 'Error: ' . $e->getMessage();
        }

        header('Location: /admin/locations');
        exit;
    }

    public function saveBlock(): void
    {
        if (!AuthService::isAdmin() || !AuthService::validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $_SESSION['flash_error'] = 'Unauthorized.';
            header('Location: /admin/locations');
            exit;
        }

        $id = (int)($_POST['id'] ?? 0);
        $districtId = (int)($_POST['district_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $nameHi = trim($_POST['name_hi'] ?? '') ?: $name;
        $code = trim($_POST['code'] ?? '');
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        $db = Database::getConnection();
        try {
            if ($id > 0) {
                $stmt = $db->prepare("UPDATE blocks SET name = ?, name_hi = ?, code = ?, is_active = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$name, $nameHi, $code, $isActive, $id]);
                AuditService::log('BLOCK_UPDATED', 'BLOCK', (string)$id, ['name' => $name]);
                $_SESSION['flash_success'] = 'Block updated successfully.';
            } else {
                $stmt = $db->prepare("INSERT INTO blocks (district_id, name, name_hi, code, is_active, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                $stmt->execute([$districtId, $name, $nameHi, $code, $isActive]);
                AuditService::log('BLOCK_CREATED', 'BLOCK', (string)$db->lastInsertId(), ['name' => $name]);
                $_SESSION['flash_success'] = 'Block created successfully.';
            }
        } catch (Exception $e) {
            $_SESSION['flash_error'] = 'Error: ' . $e->getMessage();
        }

        header('Location: /admin/locations?district_id=' . $districtId);
        exit;
    }

    public function exportCsv(): void
    {
        if (!AuthService::isAdmin()) {
            header('Location: /login');
            exit;
        }

        $db = Database::getConnection();
        $sql = "
            SELECT d.name as district_name, d.name_hi as district_name_hi, d.code as district_code,
                   b.name as block_name, b.name_hi as block_name_hi, b.code as block_code,
                   b.is_active
            FROM blocks b
            JOIN districts d ON b.district_id = d.id
            ORDER BY d.name ASC, b.name ASC
        ";
        $rows = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);

        $headers = ['District Name', 'District Alt Name', 'District Code', 'Block Name', 'Block Alt Name', 'Block Code', 'Is Active'];
        CsvService::streamCsv('bihar_districts_blocks_' . date('Ymd') . '.csv', $headers, $rows);
    }

    public function importCsv(): void
    {
        if (!AuthService::isAdmin() || !AuthService::validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $_SESSION['flash_error'] = 'Unauthorized.';
            header('Location: /admin/locations');
            exit;
        }

        if (!isset($_FILES['csv_file']) || !is_array($_FILES['csv_file'])) {
            $_SESSION['flash_error'] = 'No CSV file uploaded.';
            header('Location: /admin/locations');
            exit;
        }

        $file = $_FILES['csv_file'];

        // 1. Validate upload error code
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $uploadErrors = [
                UPLOAD_ERR_INI_SIZE   => 'The uploaded file exceeds the server upload_max_filesize limit.',
                UPLOAD_ERR_FORM_SIZE  => 'The uploaded file exceeds the form MAX_FILE_SIZE limit.',
                UPLOAD_ERR_PARTIAL    => 'The file was only partially uploaded.',
                UPLOAD_ERR_NO_FILE    => 'Please select a valid CSV file to upload.',
                UPLOAD_ERR_NO_TMP_DIR => 'Server missing a temporary folder for uploads.',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write uploaded file to disk.',
                UPLOAD_ERR_EXTENSION  => 'A server extension stopped the file upload.'
            ];
            $_SESSION['flash_error'] = $uploadErrors[$file['error']] ?? ('Upload failed with error code ' . $file['error']);
            header('Location: /admin/locations');
            exit;
        }

        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            $_SESSION['flash_error'] = 'Invalid upload: uploaded temporary file was not found.';
            header('Location: /admin/locations');
            exit;
        }

        // 2. Validate file extension
        $origName = $file['name'] ?? '';
        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        if ($ext !== 'csv') {
            $_SESSION['flash_error'] = 'Invalid file extension. Only .csv files are permitted.';
            header('Location: /admin/locations');
            exit;
        }

        // 3. Validate configured size limit
        $maxMb = (int)($_ENV['MAX_UPLOAD_SIZE_MB'] ?? \App\Config\Config::get('MAX_UPLOAD_SIZE_MB', 5));
        if ($maxMb <= 0) {
            $maxMb = 5;
        }
        $maxBytes = $maxMb * 1024 * 1024;
        if (($file['size'] ?? 0) > $maxBytes) {
            $_SESSION['flash_error'] = "File size exceeds the configured limit of {$maxMb} MB.";
            header('Location: /admin/locations');
            exit;
        }

        // 4. Validate MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? finfo_file($finfo, $file['tmp_name']) : '';
        if ($finfo) {
            finfo_close($finfo);
        }

        $allowedMimes = [
            'text/csv',
            'text/plain',
            'text/x-csv',
            'application/csv',
            'application/x-csv',
            'text/comma-separated-values',
            'text/x-comma-separated-values',
            'application/vnd.ms-excel'
        ];

        if (!in_array(strtolower((string)$mime), $allowedMimes, true)) {
            $_SESSION['flash_error'] = 'Invalid file type (' . htmlspecialchars((string)$mime) . '). Only authentic CSV files are allowed.';
            header('Location: /admin/locations');
            exit;
        }

        try {
            $res = CsvService::importLocations($file['tmp_name']);
            AuditService::log('LOCATIONS_CSV_IMPORTED', 'LOCATION', '0', $res);
            $_SESSION['flash_success'] = "CSV imported successfully: {$res['districts_count']} districts and {$res['blocks_count']} blocks updated.";
        } catch (Exception $e) {
            $_SESSION['flash_error'] = 'CSV import error: ' . $e->getMessage();
        }

        header('Location: /admin/locations');
        exit;
    }

    /**
     * Admin Service Availability page
     */
    public function serviceAvailabilityIndex(): void
    {
        if (!AuthService::isAdmin()) {
            $_SESSION['flash_error'] = 'Administrative privilege required.';
            header('Location: /user/dashboard');
            exit;
        }

        $db = Database::getConnection();

        // 1. Fetch active service for srv_block_app
        $dtStmt = $db->query("SELECT id, code, name_en, fee_amount, is_active FROM data_types WHERE code = 'srv_block_app' LIMIT 1");
        $service = $dtStmt->fetch(PDO::FETCH_ASSOC);
        $serviceId = $service ? (int)$service['id'] : 4;
        $baseFee = $service ? (float)$service['fee_amount'] : 50.00;

        // 2. Fetch only districts having at least one rate-configured block
        $districts = $db->query("
            SELECT DISTINCT d.id, d.name, d.code, d.state
            FROM districts d
            JOIN blocks b ON d.id = b.district_id AND b.is_active = 1
            JOIN service_rate_rules srr ON srr.data_type_id = {$serviceId}
                AND srr.district_id = d.id
                AND srr.block_id = b.id
                AND srr.is_active = 1
                AND srr.fee_amount > 0
                AND srr.application_code IS NOT NULL
                AND srr.application_code != ''
                AND srr.application_code != '*'
            WHERE d.is_active = 1
            ORDER BY d.name ASC
        ")->fetchAll(PDO::FETCH_ASSOC);

        // 3. Global summary metrics for rate-configured blocks only
        $allBlocksSummary = $db->query("
            SELECT
                b.id as block_id,
                COALESCE(sba.status, 'INACTIVE') as service_status,
                (SELECT COUNT(DISTINCT oba.operator_user_id) FROM operator_block_assignments oba JOIN users u ON oba.operator_user_id = u.id WHERE oba.district_id = b.district_id AND oba.block_id = b.id AND oba.is_active = 1 AND u.status = 'ACTIVE' AND (u.role = 'OPERATOR' OR EXISTS (SELECT 1 FROM user_role_assignments ura WHERE ura.user_id = u.id AND ura.role_code = 'OPERATOR' AND ura.is_active = 1))) as op_count,
                (SELECT COUNT(*) FROM service_rate_rules srr WHERE srr.data_type_id = {$serviceId} AND srr.block_id = b.id AND srr.is_active = 1 AND srr.fee_amount > 0 AND srr.application_code IS NOT NULL AND srr.application_code != '' AND srr.application_code != '*') as rate_count
            FROM blocks b
            JOIN districts d ON b.district_id = d.id
            LEFT JOIN service_block_availability sba ON sba.block_id = b.id AND sba.district_id = d.id AND sba.data_type_id = {$serviceId}
            WHERE b.is_active = 1 AND d.is_active = 1
              AND EXISTS (
                  SELECT 1 FROM service_rate_rules srr
                  WHERE srr.data_type_id = {$serviceId}
                    AND srr.district_id = d.id
                    AND srr.block_id = b.id
                    AND srr.is_active = 1
                    AND srr.fee_amount > 0
                    AND srr.application_code IS NOT NULL
                    AND srr.application_code != ''
                    AND srr.application_code != '*'
              )
        ")->fetchAll(PDO::FETCH_ASSOC);

        $summary = [
            'total_blocks' => count($allBlocksSummary),
            'live_blocks' => 0,
            'inactive_blocks' => 0,
            'live_missing_operator' => 0,
            'live_missing_rate' => 0
        ];

        foreach ($allBlocksSummary as $item) {
            $isLive = ($item['service_status'] === 'LIVE');
            if ($isLive) {
                $summary['live_blocks']++;
                if ((int)$item['op_count'] === 0) {
                    $summary['live_missing_operator']++;
                }
                if ((int)$item['rate_count'] === 0) {
                    $summary['live_missing_rate']++;
                }
            } else {
                $summary['inactive_blocks']++;
            }
        }

        // 4. Parse filter parameters
        $filterDistrictId = (int)($_GET['district_id'] ?? 0);
        $filterStatus = strtoupper(trim($_GET['status'] ?? 'ALL'));
        if (!in_array($filterStatus, ['ALL', 'LIVE', 'INACTIVE'], true)) {
            $filterStatus = 'ALL';
        }
        $filterSearch = trim($_GET['search'] ?? '');
        $filterOperator = strtoupper(trim($_GET['operator_filter'] ?? 'ALL'));
        if (!in_array($filterOperator, ['ALL', 'HAS_OPERATOR', 'MISSING_OPERATOR'], true)) {
            $filterOperator = 'ALL';
        }
        $filterRate = strtoupper(trim($_GET['rate_filter'] ?? 'ALL'));
        if (!in_array($filterRate, ['ALL', 'HAS_RATE', 'MISSING_RATE'], true)) {
            $filterRate = 'ALL';
        }

        // 5. Query blocks list with readiness data (ONLY blocks having active block-specific rate)
        $sql = "
            SELECT
                b.id as block_id,
                b.name as block_name,
                b.code as block_code,
                b.is_active as block_is_active,
                d.id as district_id,
                d.name as district_name,
                d.code as district_code,
                COALESCE(sba.status, 'INACTIVE') as service_status,
                sba.activated_at,
                sba.activated_by,
                sba.deactivated_at,
                sba.deactivated_by,
                sba.internal_note,
                u_act.name as activated_by_name,
                u_deact.name as deactivated_by_name,
                (SELECT COUNT(DISTINCT oba.operator_user_id) FROM operator_block_assignments oba JOIN users u ON oba.operator_user_id = u.id WHERE oba.district_id = d.id AND oba.block_id = b.id AND oba.is_active = 1 AND u.status = 'ACTIVE' AND (u.role = 'OPERATOR' OR EXISTS (SELECT 1 FROM user_role_assignments ura WHERE ura.user_id = u.id AND ura.role_code = 'OPERATOR' AND ura.is_active = 1))) as operator_count,
                (SELECT COUNT(DISTINCT oba.operator_user_id) FROM operator_block_assignments oba JOIN users u ON oba.operator_user_id = u.id WHERE oba.district_id = d.id AND oba.block_id = b.id AND oba.is_active = 1 AND u.status = 'ACTIVE' AND (u.role = 'OPERATOR' OR EXISTS (SELECT 1 FROM user_role_assignments ura WHERE ura.user_id = u.id AND ura.role_code = 'OPERATOR' AND ura.is_active = 1)) AND u.mobile IS NOT NULL AND u.mobile != '' AND LENGTH(u.mobile) >= 10) as whatsapp_operator_count,
                (SELECT COUNT(*) FROM service_rate_rules srr WHERE srr.data_type_id = {$serviceId} AND srr.block_id = b.id AND srr.is_active = 1 AND srr.fee_amount > 0 AND srr.application_code IS NOT NULL AND srr.application_code != '' AND srr.application_code != '*') as block_rate_count,
                (SELECT fee_amount FROM service_rate_rules srr WHERE srr.data_type_id = {$serviceId} AND srr.block_id = b.id AND srr.is_active = 1 AND srr.fee_amount > 0 AND srr.application_code IS NOT NULL AND srr.application_code != '' AND srr.application_code != '*' ORDER BY fee_amount ASC LIMIT 1) as sample_block_fee,
                (
                    SELECT GROUP_CONCAT(CONCAT(srr.application_code, ': ₹', ROUND(srr.fee_amount)) ORDER BY srr.fee_amount ASC SEPARATOR ', ')
                    FROM service_rate_rules srr
                    WHERE srr.data_type_id = {$serviceId}
                      AND srr.district_id = d.id
                      AND srr.block_id = b.id
                      AND srr.is_active = 1
                      AND srr.fee_amount > 0
                      AND srr.application_code IS NOT NULL
                      AND srr.application_code != ''
                      AND srr.application_code != '*'
                ) as configured_rates_summary,
                (
                    SELECT GROUP_CONCAT(DISTINCT srr.application_code ORDER BY srr.application_code ASC SEPARATOR ', ')
                    FROM service_rate_rules srr
                    WHERE srr.data_type_id = {$serviceId}
                      AND srr.district_id = d.id
                      AND srr.block_id = b.id
                      AND srr.is_active = 1
                      AND srr.fee_amount > 0
                      AND srr.application_code IS NOT NULL
                      AND srr.application_code != ''
                      AND srr.application_code != '*'
                ) as available_application_codes
            FROM blocks b
            JOIN districts d ON b.district_id = d.id
            LEFT JOIN service_block_availability sba ON sba.block_id = b.id AND sba.district_id = d.id AND sba.data_type_id = {$serviceId}
            LEFT JOIN users u_act ON sba.activated_by = u_act.id
            LEFT JOIN users u_deact ON sba.deactivated_by = u_deact.id
            WHERE b.is_active = 1 AND d.is_active = 1
              AND EXISTS (
                  SELECT 1 FROM service_rate_rules srr
                  WHERE srr.data_type_id = {$serviceId}
                    AND srr.district_id = d.id
                    AND srr.block_id = b.id
                    AND srr.is_active = 1
                    AND srr.fee_amount > 0
                    AND srr.application_code IS NOT NULL
                    AND srr.application_code != ''
                    AND srr.application_code != '*'
              )
        ";

        $params = [];
        if ($filterDistrictId > 0) {
            $sql .= " AND b.district_id = ?";
            $params[] = $filterDistrictId;
        }
        if ($filterStatus === 'LIVE') {
            $sql .= " AND sba.status = 'LIVE'";
        } elseif ($filterStatus === 'INACTIVE') {
            $sql .= " AND (sba.status IS NULL OR sba.status = 'INACTIVE')";
        }
        if (!empty($filterSearch)) {
            $sql .= " AND (b.name LIKE ? OR d.name LIKE ? OR b.code LIKE ?)";
            $searchTerm = '%' . $filterSearch . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        $sql .= " ORDER BY d.name ASC, b.name ASC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $allRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Apply PHP-level filters for operator and rate if requested
        $blocksList = [];
        foreach ($allRows as $row) {
            if ($filterOperator === 'HAS_OPERATOR' && (int)$row['operator_count'] === 0) {
                continue;
            }
            if ($filterOperator === 'MISSING_OPERATOR' && (int)$row['operator_count'] > 0) {
                continue;
            }
            if ($filterRate === 'HAS_RATE' && (int)$row['block_rate_count'] === 0) {
                continue;
            }
            if ($filterRate === 'MISSING_RATE' && (int)$row['block_rate_count'] > 0) {
                continue;
            }
            $blocksList[] = $row;
        }

        // Available operators for quick activation assignment
        $operators = $db->query("
            SELECT DISTINCT u.id, u.name, u.mobile
            FROM users u
            WHERE u.status = 'ACTIVE'
              AND (u.role = 'OPERATOR' OR EXISTS (SELECT 1 FROM user_role_assignments ura WHERE ura.user_id = u.id AND ura.role_code = 'OPERATOR' AND ura.is_active = 1))
            ORDER BY u.name ASC
        ")->fetchAll(PDO::FETCH_ASSOC);

        require dirname(__DIR__) . '/Views/admin/service_availability.php';
    }

    /**
     * POST endpoint: Activate or Deactivate block service availability
     */
    public function toggleServiceAvailability(): void
    {
        $wantsJson = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
            || (str_starts_with($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json'));

        if (!AuthService::isAdmin()) {
            if ($wantsJson) {
                http_response_code(403);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Administrative privilege required.']);
                exit;
            }
            $_SESSION['flash_error'] = 'Administrative privilege required.';
            header('Location: /login');
            exit;
        }

        if (!AuthService::validateCsrfToken($_POST['csrf_token'] ?? '')) {
            if ($wantsJson) {
                http_response_code(403);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'CSRF verification failed.']);
                exit;
            }
            $_SESSION['flash_error'] = 'Invalid security token. Please refresh and try again.';
            header('Location: /admin/service-availability');
            exit;
        }

        $districtId = (int)($_POST['district_id'] ?? 0);
        $blockId = (int)($_POST['block_id'] ?? 0);
        $targetStatus = strtoupper(trim($_POST['status'] ?? ''));
        $internalNote = trim($_POST['internal_note'] ?? '');

        if ($districtId <= 0 || $blockId <= 0 || !in_array($targetStatus, ['LIVE', 'INACTIVE'], true)) {
            $msg = 'Invalid district, block, or target status provided.';
            if ($wantsJson) {
                http_response_code(400);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => $msg]);
                exit;
            }
            $_SESSION['flash_error'] = $msg;
            header('Location: /admin/service-availability');
            exit;
        }

        $db = Database::getConnection();

        // 1. Validate District exists and is active
        $dStmt = $db->prepare("SELECT id, name, is_active FROM districts WHERE id = ? LIMIT 1");
        $dStmt->execute([$districtId]);
        $district = $dStmt->fetch(PDO::FETCH_ASSOC);
        if (!$district || (int)$district['is_active'] !== 1) {
            $msg = 'District does not exist or is inactive.';
            if ($wantsJson) {
                http_response_code(422);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => $msg]);
                exit;
            }
            $_SESSION['flash_error'] = $msg;
            header('Location: /admin/service-availability');
            exit;
        }

        // 2. Validate Block exists, is active, and belongs to that district
        $bStmt = $db->prepare("SELECT id, name, is_active FROM blocks WHERE id = ? AND district_id = ? LIMIT 1");
        $bStmt->execute([$blockId, $districtId]);
        $block = $bStmt->fetch(PDO::FETCH_ASSOC);
        if (!$block || (int)$block['is_active'] !== 1) {
            $msg = 'Block does not exist, is inactive, or does not belong to the selected district.';
            if ($wantsJson) {
                http_response_code(422);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => $msg]);
                exit;
            }
            $_SESSION['flash_error'] = $msg;
            header('Location: /admin/service-availability');
            exit;
        }

        // 3. Validate srv_block_app exists and is active
        $dtStmt = $db->prepare("SELECT id, code, is_active FROM data_types WHERE code = 'srv_block_app' LIMIT 1");
        $dtStmt->execute();
        $dt = $dtStmt->fetch(PDO::FETCH_ASSOC);
        if (!$dt || (int)$dt['is_active'] !== 1) {
            $msg = 'Service srv_block_app does not exist or is inactive.';
            if ($wantsJson) {
                http_response_code(422);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => $msg]);
                exit;
            }
            $_SESSION['flash_error'] = $msg;
            header('Location: /admin/service-availability');
            exit;
        }
        $dataTypeId = (int)$dt['id'];
        $adminUserId = AuthService::id();

        // 4. Fetch current availability record
        $curStmt = $db->prepare("SELECT * FROM service_block_availability WHERE data_type_id = ? AND district_id = ? AND block_id = ? LIMIT 1");
        $curStmt->execute([$dataTypeId, $districtId, $blockId]);
        $currentRecord = $curStmt->fetch(PDO::FETCH_ASSOC);
        $previousStatus = $currentRecord ? $currentRecord['status'] : 'INACTIVE';

        // Check readiness info (operator count & rate rule count)
        $opCount = (int)$db->query("
            SELECT COUNT(DISTINCT oba.operator_user_id)
            FROM operator_block_assignments oba
            JOIN users u ON oba.operator_user_id = u.id
            WHERE oba.district_id = {$districtId} AND oba.block_id = {$blockId} AND oba.is_active = 1
              AND u.status = 'ACTIVE'
              AND (u.role = 'OPERATOR' OR EXISTS (SELECT 1 FROM user_role_assignments ura WHERE ura.user_id = u.id AND ura.role_code = 'OPERATOR' AND ura.is_active = 1))
        ")->fetchColumn();
        $rateCount = (int)$db->query("SELECT COUNT(*) FROM service_rate_rules WHERE data_type_id = {$dataTypeId} AND block_id = {$blockId} AND is_active = 1")->fetchColumn();

        $warnings = [];
        if ($targetStatus === 'LIVE') {
            if ($opCount === 0) {
                $warnings[] = "Notice: No active Block Operator is currently assigned to {$block['name']}.";
            }
            if ($rateCount === 0) {
                $warnings[] = "Notice: No block-specific rate rule is configured for {$block['name']}; standard base rate will apply.";
            }

            if ($currentRecord) {
                $updStmt = $db->prepare("
                    UPDATE service_block_availability
                    SET status = 'LIVE',
                        activated_at = NOW(),
                        activated_by = ?,
                        deactivated_at = NULL,
                        deactivated_by = NULL,
                        internal_note = COALESCE(?, internal_note),
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $updStmt->execute([$adminUserId, $internalNote ?: null, $currentRecord['id']]);
            } else {
                $insStmt = $db->prepare("
                    INSERT INTO service_block_availability
                    (data_type_id, district_id, block_id, status, activated_at, activated_by, internal_note, created_at, updated_at)
                    VALUES (?, ?, ?, 'LIVE', NOW(), ?, ?, NOW(), NOW())
                ");
                $insStmt->execute([$dataTypeId, $districtId, $blockId, $adminUserId, $internalNote ?: null]);
            }

            // Audit log: BLOCK_SERVICE_ACTIVATED
            AuditService::log(
                'BLOCK_SERVICE_ACTIVATED',
                'BLOCK_SERVICE',
                (string)$blockId,
                [
                    'service_id' => $dataTypeId,
                    'district_id' => $districtId,
                    'block_id' => $blockId,
                    'previous_status' => $previousStatus,
                    'new_status' => 'LIVE',
                    'admin_user_id' => $adminUserId,
                    'timestamp' => date('Y-m-d H:i:s')
                ]
            );

            $successMsg = "Block '{$block['name']}' in '{$district['name']}' is now LIVE for applications." . (!empty($warnings) ? ' ' . implode(' ', $warnings) : '');
        } else {
            // Deactivation flow
            if ($currentRecord) {
                $updStmt = $db->prepare("
                    UPDATE service_block_availability
                    SET status = 'INACTIVE',
                        deactivated_at = NOW(),
                        deactivated_by = ?,
                        internal_note = COALESCE(?, internal_note),
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $updStmt->execute([$adminUserId, $internalNote ?: null, $currentRecord['id']]);
            } else {
                $insStmt = $db->prepare("
                    INSERT INTO service_block_availability
                    (data_type_id, district_id, block_id, status, deactivated_at, deactivated_by, internal_note, created_at, updated_at)
                    VALUES (?, ?, ?, 'INACTIVE', NOW(), ?, ?, NOW(), NOW())
                ");
                $insStmt->execute([$dataTypeId, $districtId, $blockId, $adminUserId, $internalNote ?: null]);
            }

            // Audit log: BLOCK_SERVICE_DEACTIVATED
            AuditService::log(
                'BLOCK_SERVICE_DEACTIVATED',
                'BLOCK_SERVICE',
                (string)$blockId,
                [
                    'service_id' => $dataTypeId,
                    'district_id' => $districtId,
                    'block_id' => $blockId,
                    'previous_status' => $previousStatus,
                    'new_status' => 'INACTIVE',
                    'admin_user_id' => $adminUserId,
                    'timestamp' => date('Y-m-d H:i:s')
                ]
            );

            $successMsg = "Block '{$block['name']}' in '{$district['name']}' has been DEACTIVATED. New application submissions have been stopped.";
        }

        if ($wantsJson) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => $successMsg,
                'status' => $targetStatus,
                'block_id' => $blockId,
                'district_id' => $districtId,
                'warnings' => $warnings
            ]);
            exit;
        }

        $_SESSION['flash_success'] = $successMsg;
        header('Location: /admin/service-availability?district_id=' . $districtId);
        exit;
    }

    /**
     * POST endpoint: Admin Quick Activation Flow
     * 1. Select district.
     * 2. Select or create block.
     * 3. Set block-specific service rates for required application codes.
     * 4. Assign a Block Operator if available.
     * 5. Set Service Status to LIVE.
     * 6. Save.
     */
    public function quickActivate(): void
    {
        if (!AuthService::isAdmin()) {
            $_SESSION['flash_error'] = 'Administrative privilege required.';
            header('Location: /user/dashboard');
            exit;
        }

        if (!AuthService::validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $_SESSION['flash_error'] = 'CSRF verification failed.';
            header('Location: /admin/service-availability');
            exit;
        }

        $districtId = (int)($_POST['district_id'] ?? 0);
        $blockId = (int)($_POST['block_id'] ?? 0);
        $newBlockName = trim($_POST['new_block_name'] ?? '');
        $serviceRate = isset($_POST['service_rate']) && $_POST['service_rate'] !== '' ? (float)$_POST['service_rate'] : null;
        $appCode = strtoupper(trim($_POST['application_code'] ?? ''));
        $operatorId = (int)($_POST['operator_id'] ?? 0);
        $serviceStatus = strtoupper(trim($_POST['service_status'] ?? 'LIVE'));
        $internalNote = trim($_POST['internal_note'] ?? '');

        if ($districtId <= 0) {
            $_SESSION['flash_error'] = 'Please select a valid district.';
            header('Location: /admin/service-availability');
            exit;
        }

        $db = Database::getConnection();

        // 1. Validate district
        $dStmt = $db->prepare("SELECT id, name, state, is_active FROM districts WHERE id = ? LIMIT 1");
        $dStmt->execute([$districtId]);
        $district = $dStmt->fetch(PDO::FETCH_ASSOC);
        if (!$district || (int)$district['is_active'] !== 1) {
            $_SESSION['flash_error'] = 'Selected district does not exist or is inactive.';
            header('Location: /admin/service-availability');
            exit;
        }

        // 2. Select or create block
        if ($blockId <= 0 && !empty($newBlockName)) {
            // Check if block already exists with this name in this district
            $bCheck = $db->prepare("SELECT id FROM blocks WHERE district_id = ? AND LOWER(TRIM(name)) = LOWER(TRIM(?)) LIMIT 1");
            $bCheck->execute([$districtId, $newBlockName]);
            $existingBlockId = $bCheck->fetchColumn();

            if ($existingBlockId) {
                $blockId = (int)$existingBlockId;
                $db->prepare("UPDATE blocks SET is_active = 1 WHERE id = ?")->execute([$blockId]);
            } else {
                $genPrefix = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $newBlockName), 0, 4));
                $genRand = strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
                $bCode = $genPrefix . '-' . $genRand;
                $bIns = $db->prepare("INSERT INTO blocks (district_id, name, name_hi, code, is_active, created_at) VALUES (?, ?, ?, ?, 1, NOW())");
                $bIns->execute([$districtId, $newBlockName, $newBlockName, $bCode]);
                $blockId = (int)$db->lastInsertId();
                AuditService::log('BLOCK_CREATED', 'BLOCK', (string)$blockId, ['name' => $newBlockName, 'district_id' => $districtId]);
            }
        }

        if ($blockId <= 0) {
            $_SESSION['flash_error'] = 'Please select an existing block or enter a new block name.';
            header('Location: /admin/service-availability?district_id=' . $districtId);
            exit;
        }

        $bStmt = $db->prepare("SELECT id, name, is_active FROM blocks WHERE id = ? AND district_id = ? LIMIT 1");
        $bStmt->execute([$blockId, $districtId]);
        $block = $bStmt->fetch(PDO::FETCH_ASSOC);
        if (!$block) {
            $_SESSION['flash_error'] = 'Invalid block selected.';
            header('Location: /admin/service-availability?district_id=' . $districtId);
            exit;
        }

        // 3. Service ID
        $dtStmt = $db->prepare("SELECT id, is_active FROM data_types WHERE code = 'srv_block_app' LIMIT 1");
        $dtStmt->execute();
        $dt = $dtStmt->fetch(PDO::FETCH_ASSOC);
        if (!$dt || (int)$dt['is_active'] !== 1) {
            $_SESSION['flash_error'] = 'Service srv_block_app is currently inactive.';
            header('Location: /admin/service-availability');
            exit;
        }
        $dataTypeId = (int)$dt['id'];

        // 4. Set block-specific service rate if provided
        if ($serviceRate !== null && $serviceRate >= 0) {
            $ruleCode = (!empty($appCode) && $appCode !== 'ALL' && $appCode !== '*') ? $appCode : null;
            // Check if existing rule exists
            $rChk = $db->prepare("
                SELECT id FROM service_rate_rules
                WHERE data_type_id = ? AND district_id = ? AND block_id = ?
                  AND ((application_code IS NULL AND ? IS NULL) OR application_code = ?)
                LIMIT 1
            ");
            $rChk->execute([$dataTypeId, $districtId, $blockId, $ruleCode, $ruleCode]);
            $existingRuleId = $rChk->fetchColumn();

            if ($existingRuleId) {
                $db->prepare("UPDATE service_rate_rules SET fee_amount = ?, is_active = 1, updated_at = NOW() WHERE id = ?")
                   ->execute([$serviceRate, $existingRuleId]);
            } else {
                $ruleLabel = "{$block['name']} (" . ($ruleCode ?: 'All Codes') . ") Rate";
                $db->prepare("
                    INSERT INTO service_rate_rules (data_type_id, state, district_id, block_id, application_code, adjustment_type, fee_amount, distributor_commission, rule_label, is_active, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, 'FIXED', ?, 10.00, ?, 1, NOW(), NOW())
                ")->execute([$dataTypeId, $district['state'] ?: 'Bihar', $districtId, $blockId, $ruleCode, $serviceRate, $ruleLabel]);
            }
        }

        // 5. Assign Block Operator if selected
        if ($operatorId > 0) {
            $opChk = $db->prepare("
                SELECT id, name FROM users
                WHERE id = ? AND (role = 'OPERATOR' OR EXISTS (SELECT 1 FROM user_role_assignments ura WHERE ura.user_id = users.id AND ura.role_code = 'OPERATOR' AND ura.is_active = 1))
                LIMIT 1
            ");
            $opChk->execute([$operatorId]);
            $opUser = $opChk->fetch(PDO::FETCH_ASSOC);
            if ($opUser) {
                // Ensure record in operator_block_assignments
                $db->prepare("
                    INSERT INTO operator_block_assignments (operator_user_id, district_id, block_id, is_active, assigned_by, created_at, updated_at)
                    VALUES (?, ?, ?, 1, ?, NOW(), NOW())
                    ON DUPLICATE KEY UPDATE is_active = 1, updated_at = NOW()
                ")->execute([$operatorId, $districtId, $blockId, AuthService::id()]);

                // Maintain legacy columns for backward compatibility display
                $db->prepare("UPDATE users SET assigned_district_id = ?, assigned_block_id = ?, updated_at = NOW() WHERE id = ?")
                   ->execute([$districtId, $blockId, $operatorId]);
                AuditService::log('OPERATOR_LOCATION_ASSIGNED', 'USER', (string)$operatorId, [
                    'assigned_district_id' => $districtId,
                    'assigned_block_id' => $blockId
                ]);
            }
        }

        // 6. Set Service Status to LIVE (or requested status)
        $adminUserId = AuthService::id();
        $curStmt = $db->prepare("SELECT id, status FROM service_block_availability WHERE data_type_id = ? AND district_id = ? AND block_id = ? LIMIT 1");
        $curStmt->execute([$dataTypeId, $districtId, $blockId]);
        $existingAvail = $curStmt->fetch(PDO::FETCH_ASSOC);
        $prevStatus = $existingAvail ? $existingAvail['status'] : 'INACTIVE';

        if ($existingAvail) {
            $upd = $db->prepare("
                UPDATE service_block_availability
                SET status = ?,
                    activated_at = IF(? = 'LIVE', NOW(), activated_at),
                    activated_by = IF(? = 'LIVE', ?, activated_by),
                    deactivated_at = IF(? = 'INACTIVE', NOW(), NULL),
                    deactivated_by = IF(? = 'INACTIVE', ?, NULL),
                    internal_note = COALESCE(?, internal_note),
                    updated_at = NOW()
                WHERE id = ?
            ");
            $upd->execute([$serviceStatus, $serviceStatus, $serviceStatus, $adminUserId, $serviceStatus, $serviceStatus, $adminUserId, $internalNote ?: null, $existingAvail['id']]);
        } else {
            $ins = $db->prepare("
                INSERT INTO service_block_availability
                (data_type_id, district_id, block_id, status, activated_at, activated_by, internal_note, created_at, updated_at)
                VALUES (?, ?, ?, ?, IF(? = 'LIVE', NOW(), NULL), IF(? = 'LIVE', ?, NULL), ?, NOW(), NOW())
            ");
            $ins->execute([$dataTypeId, $districtId, $blockId, $serviceStatus, $serviceStatus, $serviceStatus, $adminUserId, $internalNote ?: null]);
        }

        $auditAction = ($serviceStatus === 'LIVE') ? 'BLOCK_SERVICE_ACTIVATED' : 'BLOCK_SERVICE_DEACTIVATED';
        AuditService::log(
            $auditAction,
            'BLOCK_SERVICE',
            (string)$blockId,
            [
                'service_id' => $dataTypeId,
                'district_id' => $districtId,
                'block_id' => $blockId,
                'previous_status' => $prevStatus,
                'new_status' => $serviceStatus,
                'admin_user_id' => $adminUserId,
                'timestamp' => date('Y-m-d H:i:s')
            ]
        );

        $_SESSION['flash_success'] = "Block '{$block['name']}' ({$district['name']}) has been successfully saved and is now {$serviceStatus}.";
        header('Location: /admin/service-availability?district_id=' . $districtId);
        exit;
    }
}

