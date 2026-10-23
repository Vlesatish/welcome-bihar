<?php
namespace App\Controllers;

use App\Config\Database;
use App\Services\AuthService;
use App\Services\WalletService;
use App\Services\ApplicationService;
use App\Services\AuditService;
use App\Services\CsvService;
use App\Services\OperatorAuthorizationService;
use PDO;
use Exception;

class AdminController
{
    use \App\Controllers\Traits\UserDirectoryTrait;
    use \App\Controllers\Traits\RtpsQueueTrait;

    public function __construct()
    {
        if (!AuthService::check()) {
            header('Location: /login');
            exit;
        }

        $user = AuthService::user();
        if (!$user) {
            AuthService::logout();
            header('Location: /login');
            exit;
        }

        $role = $user['role'];
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';

        $activeMode = AuthService::activePortalMode();
        $isOperatorMode = ($activeMode === 'OPERATOR' && AuthService::canAccessOperatorMode());

        if ($role === 'OPERATOR' || $isOperatorMode) {
            $allowedOperatorUris = [
                '/admin/dashboard',
                '/admin/applications',
                '/admin/rtps-queue',
                '/admin/rtps-queue/attach',
                '/admin/rtps-queue/unavailable',
                '/admin/rtps-queue/auto-fetch'
            ];
            if (!in_array($uri, $allowedOperatorUris, true)) {
                http_response_code(403);
                $_SESSION['flash_error'] = 'Access denied: Staff are restricted to Dashboard, Applications, and RTPS Certificate processing.';
                header('Location: /admin/dashboard');
                exit;
            }
            return;
        }

        if ($role !== 'ADMIN') {
            $_SESSION['flash_error'] = 'Access denied. Administrative privilege required.';
            header('Location: /user/dashboard');
            exit;
        }
    }

    public function systemUpdate(): void
    {
        if (AuthService::role() !== 'ADMIN') {
            $_SESSION['flash_error'] = 'Access denied. Administrative privilege required.';
            header('Location: /login');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!AuthService::validateCsrfToken($_POST['csrf_token'] ?? '')) {
                $_SESSION['flash_error'] = 'Invalid request token (CSRF). Please try again.';
                header('Location: /admin/dashboard');
                exit;
            }
        }

        $result = \App\Services\SystemUpdateService::runFullUpdate();

        $_SESSION['flash_success'] = '1-Click System Update Completed Successfully! ' . implode(' | ', $result['log']);
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/admin/dashboard'));
        exit;
    }

    public function dashboard(): void
    {
        \App\Services\SystemUpdateService::ensureSchema();
        $db = Database::getConnection();
        $currentUser = AuthService::user();
        $activeMode = AuthService::activePortalMode();
        $isOperator = ($currentUser['role'] === 'OPERATOR' || ($activeMode === 'OPERATOR' && AuthService::canAccessOperatorMode()));
        $operatorDistrictId = $isOperator ? (int)($currentUser['assigned_district_id'] ?? 0) : 0;
        $assignedBlocks = $isOperator ? AuthService::getOperatorAssignedBlocks((int)$currentUser['id']) : [];
        $unassignedOperator = ($isOperator && empty($assignedBlocks));

        if ($unassignedOperator) {
            $reqStats = [
                'total' => 0, 'pending' => 0, 'in_process' => 0,
                'completed' => 0, 'approved' => 0, 'rejected' => 0,
                'total_fees' => 0, 'total_commissions' => 0
            ];
            $districts = [];
            $availableBlocks = [];
            $allBlocksGrouped = [];
            $codeOptions = ['BICCO' => 'BICCO', 'BCCCO' => 'BCCCO', 'BRCCO' => 'BRCCO', 'BOBC' => 'BOBC', 'NCLCO' => 'NCLCO', 'RTPS' => 'RTPS', 'OTHER' => 'OTHER'];
            $currentTab = 'ALL';
            $search = '';
            $dateFilter = 'ALL';
            $districtId = 0;
            $blockId = 0;
            $appCode = '';
            $totalRecords = 0;
            $page = 1;
            $perPage = 25;
            $totalPages = 1;
            $allRequests = [];
            require dirname(__DIR__) . '/Views/admin/dashboard.php';
            return;
        }

        // 1. Metric Counters for Top Cards
        if ($isOperator) {
            $reqStats = OperatorAuthorizationService::getStatusCounts((int)$currentUser['id']);
            $routingDashboardStats = null;
        } else {
            $reqStats = $db->query("
                SELECT
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'PENDING' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status = 'IN_PROCESS' OR status = 'UNDER_REVIEW' THEN 1 ELSE 0 END) as in_process,
                    SUM(CASE WHEN status = 'COMPLETED' OR status = 'APPROVED' THEN 1 ELSE 0 END) as completed,
                    SUM(CASE WHEN status = 'REJECTED' THEN 1 ELSE 0 END) as rejected,
                    COALESCE(SUM(fee_debited), 0) as total_fees,
                    COALESCE(SUM(commission_credited), 0) as total_commissions
                FROM applications
            ")->fetch(PDO::FETCH_ASSOC);

            // Admin Routing KPI Metrics
            $routingDashboardStats = [
                'assigned' => 0,
                'unassigned' => 0,
                'active_rules' => 0,
                'codes_without_staff' => 0
            ];
            try {
                $routingDashboardStats['assigned'] = (int)$db->query("SELECT COUNT(*) FROM applications WHERE assigned_operator_id IS NOT NULL")->fetchColumn();
                $routingDashboardStats['unassigned'] = (int)$db->query("SELECT COUNT(*) FROM applications WHERE assigned_operator_id IS NULL AND status = 'PENDING'")->fetchColumn();
                $routingDashboardStats['active_rules'] = (int)$db->query("SELECT COUNT(*) FROM operator_application_code_assignments WHERE is_active = 1")->fetchColumn();
                $routingDashboardStats['codes_without_staff'] = (int)$db->query("
                    SELECT COUNT(DISTINCT srr.district_id, srr.block_id, srr.application_code)
                    FROM service_rate_rules srr
                    WHERE srr.is_active = 1 AND srr.fee_amount > 0 AND srr.application_code IS NOT NULL AND srr.application_code != ''
                      AND NOT EXISTS (
                          SELECT 1 FROM operator_application_code_assignments oaca
                          JOIN users u ON oaca.operator_user_id = u.id
                          WHERE oaca.district_id = srr.district_id
                            AND oaca.block_id = srr.block_id
                            AND oaca.application_code = srr.application_code
                            AND oaca.is_active = 1
                            AND u.role = 'OPERATOR' AND u.status = 'ACTIVE'
                      )
                ")->fetchColumn();
            } catch (\Throwable $re) {
                // Table might not exist yet before migration runs
            }
        }
        $reqStats['approved'] = (int)($reqStats['completed'] ?? 0);

        // 2. Load Active Districts & Blocks
        if ($isOperator) {
            $districts = AuthService::getOperatorAssignedDistricts((int)$currentUser['id']);
            $availableBlocks = [];
            $allBlocksGrouped = [];
            foreach ($assignedBlocks as $ab) {
                $bId = (int)($ab['id'] ?? $ab['block_id']);
                $bName = $ab['name'] ?? $ab['block_name'];
                $allBlocksGrouped[$ab['district_id']][] = [
                    'id' => $bId,
                    'name' => $bName,
                    'district_id' => (int)$ab['district_id']
                ];
            }
        } else {
            $districts = $db->query("SELECT id, name FROM districts WHERE is_active = 1 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
        }

        // Service Availability Summary Stats for Admin Dashboard
        $serviceAvailabilityStats = [
            'live_blocks' => 0,
            'inactive_blocks' => 0,
            'live_missing_operator' => 0,
            'live_missing_rate' => 0
        ];

        if (!$isOperator) {
            $dtStmt = $db->query("SELECT id FROM data_types WHERE code = 'srv_block_app' LIMIT 1");
            $sId = (int)($dtStmt->fetchColumn() ?: 4);

            $sbaRows = $db->query("
                SELECT
                    b.id,
                    COALESCE(sba.status, 'INACTIVE') as s_status,
                    (SELECT COUNT(DISTINCT oba.operator_user_id) FROM operator_block_assignments oba JOIN users u ON oba.operator_user_id = u.id WHERE oba.district_id = b.district_id AND oba.block_id = b.id AND oba.is_active = 1 AND u.status = 'ACTIVE' AND (u.role = 'OPERATOR' OR EXISTS (SELECT 1 FROM user_role_assignments ura WHERE ura.user_id = u.id AND ura.role_code = 'OPERATOR' AND ura.is_active = 1))) as op_count,
                    (SELECT COUNT(*) FROM service_rate_rules srr WHERE srr.data_type_id = {$sId} AND srr.block_id = b.id AND srr.is_active = 1) as rate_count
                FROM blocks b
                JOIN districts d ON b.district_id = d.id
                LEFT JOIN service_block_availability sba ON sba.block_id = b.id AND sba.district_id = d.id AND sba.data_type_id = {$sId}
                WHERE b.is_active = 1 AND d.is_active = 1
            ")->fetchAll(PDO::FETCH_ASSOC);

            foreach ($sbaRows as $r) {
                if ($r['s_status'] === 'LIVE') {
                    $serviceAvailabilityStats['live_blocks']++;
                    if ((int)$r['op_count'] === 0) {
                        $serviceAvailabilityStats['live_missing_operator']++;
                    }
                    if ((int)$r['rate_count'] === 0) {
                        $serviceAvailabilityStats['live_missing_rate']++;
                    }
                } else {
                    $serviceAvailabilityStats['inactive_blocks']++;
                }
            }
        }

        // 3. Load Configured Application Codes from DB (data_type_fields + service_rate_rules)
        $codeOptions = [];

        try {
            $dtfRow = $db->query("SELECT options_json FROM data_type_fields WHERE field_name = 'application_code' AND options_json IS NOT NULL LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            if ($dtfRow && !empty($dtfRow['options_json'])) {
                $decoded = json_decode($dtfRow['options_json'], true);
                if (is_array($decoded)) {
                    foreach ($decoded as $opt) {
                        if (!empty($opt['value'])) {
                            $codeOptions[$opt['value']] = $opt['value'];
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            // fallback gracefully
        }
        $ruleCodes = $db->query("SELECT DISTINCT application_code FROM service_rate_rules WHERE application_code IS NOT NULL AND application_code != ''")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($ruleCodes as $rc) {
            $codeOptions[$rc] = $rc;
        }
        if (empty($codeOptions)) {
            $codeOptions = ['BICCO' => 'BICCO', 'BCCCO' => 'BCCCO', 'BRCCO' => 'BRCCO', 'BOBC' => 'BOBC', 'NCLCO' => 'NCLCO', 'RTPS' => 'RTPS', 'OTHER' => 'OTHER'];
        }

        // 4. Parse Filters from GET
        $currentTab = strtoupper(trim($_GET['status'] ?? 'ALL'));
        if (!in_array($currentTab, ['ALL', 'PENDING', 'IN_PROCESS', 'APPROVED', 'REJECTED'], true)) {
            $currentTab = 'ALL';
        }

        $search = trim($_GET['search'] ?? '');
        $dateFilter = strtoupper(trim($_GET['date_filter'] ?? 'ALL'));

        if ($isOperator) {
            $districtId = !empty($_GET['district_id']) ? (int)$_GET['district_id'] : 0;
            $blockId = !empty($_GET['block_id']) ? (int)$_GET['block_id'] : 0;
            if ($districtId > 0 && isset($allBlocksGrouped[$districtId])) {
                $availableBlocks = $allBlocksGrouped[$districtId];
            } else {
                $availableBlocks = $assignedBlocks;
            }
        } else {
            $districtId = !empty($_GET['district_id']) ? (int)$_GET['district_id'] : 0;
            $blockId = !empty($_GET['block_id']) ? (int)$_GET['block_id'] : 0;

            // Load Blocks for selected district if districtId > 0
            $availableBlocks = [];
            if ($districtId > 0) {
                $bStmt = $db->prepare("SELECT id, name, district_id FROM blocks WHERE district_id = ? AND is_active = 1 ORDER BY name ASC");
                $bStmt->execute([$districtId]);
                $availableBlocks = $bStmt->fetchAll(PDO::FETCH_ASSOC);
            }

            // Also fetch all blocks grouped by district for instant JS switching
            $allBlocksGrouped = [];
            $allBlocksRows = $db->query("SELECT id, name, district_id FROM blocks WHERE is_active = 1 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($allBlocksRows as $abr) {
                $allBlocksGrouped[$abr['district_id']][] = [
                    'id' => (int)$abr['id'],
                    'name' => $abr['name']
                ];
            }
        }

        $appCode = strtoupper(trim($_GET['app_code'] ?? ''));

        // 5. Build Backend SQL WHERE Clause using canonical OperatorAuthorizationService
        $filterData = [
            'status' => $currentTab,
            'district_id' => $districtId,
            'block_id' => $blockId,
            'app_code' => $appCode,
            'date_filter' => $dateFilter,
            'search' => $search,
        ];
        $filterResult = OperatorAuthorizationService::buildFilterConditions(
            $filterData,
            $isOperator,
            $isOperator ? (int)$currentUser['id'] : null,
            'a'
        );
        $where = !empty($filterResult['where']) ? $filterResult['where'] : ["1=1"];
        $params = $filterResult['params'];
        $whereSql = implode(' AND ', $where);

        // 6. Pagination on entire dataset
        $countSql = "
            SELECT COUNT(*)
            FROM applications a
            LEFT JOIN users u ON a.user_id = u.id
            LEFT JOIN districts d ON a.district_id = d.id
            LEFT JOIN blocks b ON a.block_id = b.id
            LEFT JOIN data_types dt ON a.data_type_id = dt.id
            WHERE {$whereSql}
        ";
        $countStmt = $db->prepare($countSql);
        $countStmt->execute($params);
        $totalRecords = (int)$countStmt->fetchColumn();

        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 25;
        $totalPages = max(1, (int)ceil($totalRecords / $perPage));
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $perPage;

        // 7. Fetch Filtered Paginated Applications
        $dataSql = "
            SELECT a.id, a.app_ref, a.batch_ref, a.applicant_name, a.applicant_mobile, a.applicant_identifier,
                   a.fee_debited, a.commission_credited, a.status, a.admin_remarks, a.remarks,
                   a.rejection_reason, a.result_file, a.is_refunded, a.is_commission_paid,
                   a.assigned_operator_id, a.claimed_at, a.created_at, a.updated_at,
                   u.id as retailer_id, u.name as retailer_name, u.mobile as retailer_mobile, u.center_name,
                   d.name as district_name,
                   b.name as block_name,
                   dt.name_en as service_title
            FROM applications a
            LEFT JOIN users u ON a.user_id = u.id
            LEFT JOIN districts d ON a.district_id = d.id
            LEFT JOIN blocks b ON a.block_id = b.id
            LEFT JOIN data_types dt ON a.data_type_id = dt.id
            WHERE {$whereSql}
            ORDER BY a.updated_at DESC, a.id DESC
            LIMIT {$perPage} OFFSET {$offset}
        ";
        $dataStmt = $db->prepare($dataSql);
        $dataStmt->execute($params);
        $allRequests = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch dynamic fields for these applications
        $appIds = array_column($allRequests, 'id');
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

        foreach ($allRequests as &$r) {
            $r['formData'] = $fieldValuesByApp[$r['id']] ?? [];
            if (!isset($r['formData']['applicant_name'])) $r['formData']['applicant_name'] = $r['applicant_name'];
            if (!isset($r['formData']['mobile'])) $r['formData']['mobile'] = $r['applicant_mobile'];
            if (!isset($r['formData']['application_number'])) $r['formData']['application_number'] = $r['applicant_identifier'];
            if (!isset($r['formData']['district_name'])) $r['formData']['district_name'] = $r['district_name'];
            if (!isset($r['formData']['block_name'])) $r['formData']['block_name'] = $r['block_name'];

            $r['display_status'] = $r['status'];
            if ($r['status'] === 'UNDER_REVIEW') $r['display_status'] = 'IN_PROCESS';
            elseif ($r['status'] === 'COMPLETED' || $r['status'] === 'APPROVED') $r['display_status'] = 'APPROVED';
        }
        unset($r);

        require dirname(__DIR__) . '/Views/admin/dashboard.php';
    }
    // users() and directory methods provided by UserDirectoryTrait


    /**
     * Create dedicated Block Operator account
     * Strictly validates District & Block, mobile/email uniqueness, secure password hash
     */
    public function createOperator(): void
    {
        if (!AuthService::isAdmin()) {
            http_response_code(403);
            $_SESSION['flash_error'] = 'Administrative privilege required.';
            header('Location: /login');
            exit;
        }

        if (!AuthService::validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $_SESSION['flash_error'] = 'Invalid CSRF token. Please try again.';
            header('Location: /admin/users');
            exit;
        }

        $name = trim((string)($_POST['name'] ?? ''));
        $mobile = preg_replace('/\D/', '', trim((string)($_POST['mobile'] ?? '')));
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $password = (string)($_POST['password'] ?? '');
        $districtId = (int)($_POST['district_id'] ?? 0);
        $blockId = (int)($_POST['block_id'] ?? 0);
        $status = strtoupper(trim((string)($_POST['status'] ?? 'ACTIVE')));
        if (!in_array($status, ['ACTIVE', 'INACTIVE'], true)) {
            $status = 'ACTIVE';
        }

        if (empty($name)) {
            $_SESSION['flash_error'] = 'Operator full name is mandatory.';
            header('Location: /admin/users');
            exit;
        }

        if (strlen($mobile) !== 10) {
            $_SESSION['flash_error'] = 'Valid 10-digit mobile number is mandatory.';
            header('Location: /admin/users');
            exit;
        }

        if (strlen($password) < 6) {
            $_SESSION['flash_error'] = 'Password must be at least 6 characters.';
            header('Location: /admin/users');
            exit;
        }

        if ($districtId <= 0 || $blockId <= 0) {
            $_SESSION['flash_error'] = 'Assigned District and Block are mandatory for Block Operators.';
            header('Location: /admin/users');
            exit;
        }

        $db = Database::getConnection();

        // Validate that block belongs to the selected district
        $bStmt = $db->prepare("SELECT id FROM blocks WHERE id = ? AND district_id = ? AND is_active = 1 LIMIT 1");
        $bStmt->execute([$blockId, $districtId]);
        if (!$bStmt->fetch()) {
            $_SESSION['flash_error'] = 'Validation failed: The selected block does not belong to the selected district.';
            header('Location: /admin/users');
            exit;
        }

        // Validate unique mobile and email
        $chk = $db->prepare("SELECT id, mobile, email FROM users WHERE mobile = ? OR (email != '' AND email IS NOT NULL AND email = ?) LIMIT 1");
        $chk->execute([$mobile, $email ?: 'NO_EMAIL_' . bin2hex(random_bytes(4))]);
        if ($chk->fetch()) {
            $_SESSION['flash_error'] = 'Mobile number or email is already registered to an existing account.';
            header('Location: /admin/users');
            exit;
        }

        $uuid = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
        $passwordHash = password_hash($password, PASSWORD_BCRYPT);

        $db->beginTransaction();
        try {
            $ins = $db->prepare("
                INSERT INTO users (
                    uuid, role, name, mobile, email, password_hash,
                    status, assigned_district_id, assigned_block_id, assigned_all_districts,
                    preferred_lang, center_name, created_at, updated_at
                ) VALUES (
                    ?, 'OPERATOR', ?, ?, ?, ?,
                    ?, ?, ?, 0,
                    'hi', ?, NOW(), NOW()
                )
            ");
            $centerName = 'Block Operator Office';
            $ins->execute([
                $uuid, $name, $mobile, $email ?: null, $passwordHash,
                $status, $districtId, $blockId, $centerName
            ]);
            $newOpId = (int)$db->lastInsertId();

            // Save block assignment in operator_block_assignments
            \App\Services\StaffRoutingService::saveOperatorBlockAssignment($newOpId, $districtId, $blockId, ($status === 'ACTIVE' ? 1 : 0), AuthService::id(), $db);

            // Initialize operator wallet
            WalletService::getOrCreateWallet($newOpId);

            AuditService::log('OPERATOR_CREATED', 'USER', (string)$newOpId, [
                'name' => $name,
                'mobile' => $mobile,
                'district_id' => $districtId,
                'block_id' => $blockId,
                'status' => $status
            ], AuthService::id(), 'ADMIN');

            $db->commit();
            $_SESSION['flash_success'] = "Block Operator '{$name}' created successfully with assigned block.";
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $_SESSION['flash_error'] = 'Failed to create block operator: ' . $e->getMessage();
        }

        header('Location: /admin/users');
        exit;
    }

    /**
     * Super Admin resets operator password
     */
    public function resetOperatorPassword(): void
    {
        if (!AuthService::isAdmin()) {
            http_response_code(403);
            exit;
        }

        if (!AuthService::validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $_SESSION['flash_error'] = 'Invalid CSRF token.';
            header('Location: /admin/users');
            exit;
        }

        $userId = (int)($_POST['operator_id'] ?? 0);
        $newPassword = (string)($_POST['new_password'] ?? '');

        if ($userId <= 0 || strlen($newPassword) < 6) {
            $_SESSION['flash_error'] = 'Password must be at least 6 characters.';
            header('Location: /admin/users');
            exit;
        }

        $db = Database::getConnection();
        $uStmt = $db->prepare("SELECT id, name, role FROM users WHERE id = ?");
        $uStmt->execute([$userId]);
        $target = $uStmt->fetch(PDO::FETCH_ASSOC);

        if (!$target) {
            $_SESSION['flash_error'] = 'User not found.';
            header('Location: /admin/users');
            exit;
        }

        $hash = password_hash($newPassword, PASSWORD_BCRYPT);
        $upd = $db->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?");
        $upd->execute([$hash, $userId]);

        AuditService::log('OPERATOR_PASSWORD_RESET', 'USER', (string)$userId, [
            'name' => $target['name'],
            'role' => $target['role']
        ], AuthService::id(), 'ADMIN');

        $_SESSION['flash_success'] = "Password reset successfully for '{$target['name']}'.";
        header('Location: /admin/users');
        exit;
    }

    /**
     * Super Admin reassigns operator District and Block
     */
    public function reassignOperator(): void
    {
        if (!AuthService::isAdmin()) {
            http_response_code(403);
            exit;
        }

        if (!AuthService::validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $_SESSION['flash_error'] = 'Invalid CSRF token.';
            header('Location: /admin/users');
            exit;
        }

        $userId = (int)($_POST['operator_id'] ?? 0);
        $districtId = (int)($_POST['district_id'] ?? 0);
        $blockId = (int)($_POST['block_id'] ?? 0);

        if ($userId <= 0 || $districtId <= 0 || $blockId <= 0) {
            $_SESSION['flash_error'] = 'District and Block are mandatory for operator assignment.';
            header('Location: /admin/users');
            exit;
        }

        $db = Database::getConnection();
        // Validate block belongs to district
        $bStmt = $db->prepare("SELECT b.name as block_name, d.name as district_name FROM blocks b JOIN districts d ON b.district_id = d.id WHERE b.id = ? AND b.district_id = ? AND b.is_active = 1 LIMIT 1");
        $bStmt->execute([$blockId, $districtId]);
        $loc = $bStmt->fetch(PDO::FETCH_ASSOC);

        if (!$loc) {
            $_SESSION['flash_error'] = 'Validation failed: The selected block does not belong to the selected district.';
            header('Location: /admin/users');
            exit;
        }

        $uStmt = $db->prepare("SELECT id, name, role FROM users WHERE id = ?");
        $uStmt->execute([$userId]);
        $target = $uStmt->fetch(PDO::FETCH_ASSOC);

        if (!$target || $target['role'] !== 'OPERATOR') {
            $_SESSION['flash_error'] = 'Target user is not a Block Operator.';
            header('Location: /admin/users');
            exit;
        }

        $upd = $db->prepare("UPDATE users SET assigned_district_id = ?, assigned_block_id = ?, assigned_all_districts = 0, updated_at = NOW() WHERE id = ?");
        $upd->execute([$districtId, $blockId, $userId]);

        // Save block assignment in operator_block_assignments
        \App\Services\StaffRoutingService::saveOperatorBlockAssignment($userId, $districtId, $blockId, 1, AuthService::id(), $db);

        AuditService::log('OPERATOR_REASSIGNED', 'USER', (string)$userId, [
            'district_id' => $districtId,
            'district_name' => $loc['district_name'],
            'block_id' => $blockId,
            'block_name' => $loc['block_name']
        ], AuthService::id(), 'ADMIN');

        $_SESSION['flash_success'] = "Operator '{$target['name']}' reassigned to {$loc['district_name']} - {$loc['block_name']}.";
        header('Location: /admin/users');
        exit;
    }

    /**
     * Super Admin grants operator access to an existing USER (Retailer)
     * Preserves USER role and adds OPERATOR capability via user_role_assignments
     */
    public function grantOperatorAccess(): void
    {
        if (!AuthService::isAdmin()) {
            http_response_code(403);
            $_SESSION['flash_error'] = 'Administrative privilege required.';
            header('Location: /login');
            exit;
        }

        if (!AuthService::validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $_SESSION['flash_error'] = 'Invalid request security token (CSRF).';
            header('Location: /admin/users');
            exit;
        }

        $userId = (int)($_POST['user_id'] ?? 0);
        $districtId = (int)($_POST['district_id'] ?? 0);
        $blockId = (int)($_POST['block_id'] ?? 0);
        $status = strtoupper(trim((string)($_POST['status'] ?? 'ACTIVE')));
        $codes = $_POST['application_codes'] ?? [];
        if (is_string($codes)) {
            $codes = array_filter(array_map('trim', explode(',', $codes)));
        }
        if (!is_array($codes)) {
            $codes = [];
        }

        if ($userId <= 0 || $districtId <= 0 || $blockId <= 0) {
            $_SESSION['flash_error'] = 'User, District, and Block are mandatory to grant operator access.';
            header('Location: /admin/users');
            exit;
        }

        $db = Database::getConnection();

        // Validate user is a USER
        $uStmt = $db->prepare("SELECT id, name, role, status FROM users WHERE id = ?");
        $uStmt->execute([$userId]);
        $targetUser = $uStmt->fetch(PDO::FETCH_ASSOC);

        if (!$targetUser) {
            $_SESSION['flash_error'] = 'User not found.';
            header('Location: /admin/users');
            exit;
        }

        if ($targetUser['role'] !== 'USER') {
            $_SESSION['flash_error'] = 'Operator access can only be granted to Retailer (USER) accounts. Dedicated operators already have operator capabilities.';
            header('Location: /admin/users');
            exit;
        }

        // Validate block belongs to district
        $bStmt = $db->prepare("SELECT b.name as block_name, d.name as district_name FROM blocks b JOIN districts d ON b.district_id = d.id WHERE b.id = ? AND b.district_id = ? AND b.is_active = 1 LIMIT 1");
        $bStmt->execute([$blockId, $districtId]);
        $loc = $bStmt->fetch(PDO::FETCH_ASSOC);
        if (!$loc) {
            $_SESSION['flash_error'] = 'The selected block does not belong to the selected district.';
            header('Location: /admin/users');
            exit;
        }

        $adminId = AuthService::id();
        $isActive = ($status === 'ACTIVE') ? 1 : 0;

        $db->beginTransaction();
        try {
            // Update users table assigned district and block
            $updUser = $db->prepare("UPDATE users SET assigned_district_id = ?, assigned_block_id = ?, updated_at = NOW() WHERE id = ?");
            $updUser->execute([$districtId, $blockId, $userId]);

            // Ensure USER role assignment exists
            $insUserRole = $db->prepare("
                INSERT INTO user_role_assignments (user_id, role_code, is_active, granted_by, granted_at, created_at, updated_at)
                VALUES (?, 'USER', 1, ?, NOW(), NOW(), NOW())
                ON DUPLICATE KEY UPDATE is_active = 1, updated_at = NOW()
            ");
            $insUserRole->execute([$userId, $adminId]);

            // Insert / Update OPERATOR role assignment
            $insOpRole = $db->prepare("
                INSERT INTO user_role_assignments (user_id, role_code, is_active, granted_by, granted_at, revoked_by, revoked_at, created_at, updated_at)
                VALUES (?, 'OPERATOR', ?, ?, NOW(), NULL, NULL, NOW(), NOW())
                ON DUPLICATE KEY UPDATE is_active = VALUES(is_active), granted_by = VALUES(granted_by), granted_at = NOW(), revoked_by = NULL, revoked_at = NULL, updated_at = NOW()
            ");
            $insOpRole->execute([$userId, $isActive, $adminId]);

            // Save block assignment in operator_block_assignments
            \App\Services\StaffRoutingService::saveOperatorBlockAssignment($userId, $districtId, $blockId, $isActive, $adminId, $db);

            // Sync Application Code assignments
            \App\Services\StaffRoutingService::saveOperatorCodeAssignments($userId, $districtId, $blockId, $codes, $adminId);

            // Audit Log
            AuditService::log('OPERATOR_ACCESS_GRANTED', 'USER', (string)$userId, [
                'user_id' => $userId,
                'user_name' => $targetUser['name'],
                'district_id' => $districtId,
                'district_name' => $loc['district_name'],
                'block_id' => $blockId,
                'block_name' => $loc['block_name'],
                'application_codes' => $codes,
                'admin_id' => $adminId,
                'previous_status' => null,
                'new_status' => $status
            ], $adminId, 'ADMIN');

            $db->commit();

            // WhatsApp Notification
            if ($isActive) {
                try {
                    \App\Services\WhatsAppNotificationService::queueOperatorAccessNotification($userId);
                } catch (\Throwable $we) {
                    // non-fatal
                }
            }

            $_SESSION['flash_success'] = "Dual Role Operator access granted to '{$targetUser['name']}' for {$loc['district_name']} - {$loc['block_name']}.";
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $_SESSION['flash_error'] = 'Failed to grant operator access: ' . $e->getMessage();
        }

        header('Location: /admin/users');
        exit;
    }

    /**
     * Super Admin edits existing operator assignment for dual-role user or operator
     */
    public function editOperatorAssignment(): void
    {
        if (!AuthService::isAdmin()) {
            http_response_code(403);
            exit;
        }

        if (!AuthService::validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $_SESSION['flash_error'] = 'Invalid CSRF token.';
            header('Location: /admin/users');
            exit;
        }

        $userId = (int)($_POST['user_id'] ?? 0);
        $districtId = (int)($_POST['district_id'] ?? 0);
        $blockId = (int)($_POST['block_id'] ?? 0);
        $status = strtoupper(trim((string)($_POST['status'] ?? 'ACTIVE')));
        $codes = $_POST['application_codes'] ?? [];
        if (is_string($codes)) {
            $codes = array_filter(array_map('trim', explode(',', $codes)));
        }
        if (!is_array($codes)) {
            $codes = [];
        }

        if ($userId <= 0 || $districtId <= 0 || $blockId <= 0) {
            $_SESSION['flash_error'] = 'District and Block are mandatory for operator assignment.';
            header('Location: /admin/users');
            exit;
        }

        $db = Database::getConnection();
        $bStmt = $db->prepare("SELECT b.name as block_name, d.name as district_name FROM blocks b JOIN districts d ON b.district_id = d.id WHERE b.id = ? AND b.district_id = ? AND b.is_active = 1 LIMIT 1");
        $bStmt->execute([$blockId, $districtId]);
        $loc = $bStmt->fetch(PDO::FETCH_ASSOC);
        if (!$loc) {
            $_SESSION['flash_error'] = 'The selected block does not belong to the selected district.';
            header('Location: /admin/users');
            exit;
        }

        $uStmt = $db->prepare("SELECT id, name, role, assigned_district_id, assigned_block_id FROM users WHERE id = ?");
        $uStmt->execute([$userId]);
        $targetUser = $uStmt->fetch(PDO::FETCH_ASSOC);
        if (!$targetUser) {
            $_SESSION['flash_error'] = 'User not found.';
            header('Location: /admin/users');
            exit;
        }

        $adminId = AuthService::id();
        $isActive = ($status === 'ACTIVE') ? 1 : 0;

        $db->beginTransaction();
        try {
            // Update user location
            $updUser = $db->prepare("UPDATE users SET assigned_district_id = ?, assigned_block_id = ?, updated_at = NOW() WHERE id = ?");
            $updUser->execute([$districtId, $blockId, $userId]);

            // Update user_role_assignments
            $updRole = $db->prepare("
                UPDATE user_role_assignments
                SET is_active = ?,
                    revoked_by = " . ($isActive ? "NULL" : "?") . ",
                    revoked_at = " . ($isActive ? "NULL" : "NOW()") . ",
                    updated_at = NOW()
                WHERE user_id = ? AND role_code = 'OPERATOR'
            ");
            if ($isActive) {
                $updRole->execute([1, $userId]);
            } else {
                $updRole->execute([0, $adminId, $userId]);
            }

            // Save block assignment in operator_block_assignments
            \App\Services\StaffRoutingService::saveOperatorBlockAssignment($userId, $districtId, $blockId, $isActive, $adminId, $db);

            // Sync codes
            \App\Services\StaffRoutingService::saveOperatorCodeAssignments($userId, $districtId, $blockId, $codes, $adminId);

            // Audit
            AuditService::log('OPERATOR_ACCESS_UPDATED', 'USER', (string)$userId, [
                'user_id' => $userId,
                'district_id' => $districtId,
                'block_id' => $blockId,
                'application_codes' => $codes,
                'admin_id' => $adminId,
                'new_status' => $status
            ], $adminId, 'ADMIN');

            $db->commit();
            $_SESSION['flash_success'] = "Operator assignment updated for '{$targetUser['name']}'.";
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $_SESSION['flash_error'] = 'Failed to update operator assignment: ' . $e->getMessage();
        }

        header('Location: /admin/users');
        exit;
    }

    /**
     * Super Admin suspends operator access for a dual-role user
     */
    public function suspendOperatorAccess(): void
    {
        if (!AuthService::isAdmin()) {
            http_response_code(403);
            exit;
        }

        if (!AuthService::validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $_SESSION['flash_error'] = 'Invalid CSRF token.';
            header('Location: /admin/users');
            exit;
        }

        $userId = (int)($_POST['user_id'] ?? 0);
        if ($userId <= 0) {
            $_SESSION['flash_error'] = 'Invalid user ID.';
            header('Location: /admin/users');
            exit;
        }

        $db = Database::getConnection();
        $adminId = AuthService::id();

        $uStmt = $db->prepare("SELECT id, name, role, assigned_district_id, assigned_block_id FROM users WHERE id = ?");
        $uStmt->execute([$userId]);
        $targetUser = $uStmt->fetch(PDO::FETCH_ASSOC);
        if (!$targetUser) {
            $_SESSION['flash_error'] = 'User not found.';
            header('Location: /admin/users');
            exit;
        }

        $db->beginTransaction();
        try {
            // Update user_role_assignments
            $stmt = $db->prepare("UPDATE user_role_assignments SET is_active = 0, revoked_by = ?, revoked_at = NOW(), updated_at = NOW() WHERE user_id = ? AND role_code = 'OPERATOR'");
            $stmt->execute([$adminId, $userId]);

            // Deactivate code assignments
            $stmt2 = $db->prepare("UPDATE operator_application_code_assignments SET is_active = 0, updated_at = NOW() WHERE operator_user_id = ?");
            $stmt2->execute([$userId]);

            // Deactivate block assignments
            $stmtBlocks = $db->prepare("UPDATE operator_block_assignments SET is_active = 0, updated_at = NOW() WHERE operator_user_id = ?");
            $stmtBlocks->execute([$userId]);

            // Audit
            AuditService::log('OPERATOR_ACCESS_SUSPENDED', 'USER', (string)$userId, [
                'user_id' => $userId,
                'user_name' => $targetUser['name'],
                'district_id' => (int)($targetUser['assigned_district_id'] ?? 0),
                'block_id' => (int)($targetUser['assigned_block_id'] ?? 0),
                'admin_id' => $adminId,
                'previous_status' => 'ACTIVE',
                'new_status' => 'SUSPENDED'
            ], $adminId, 'ADMIN');

            $db->commit();
            $_SESSION['flash_success'] = "Operator access suspended for '{$targetUser['name']}'. Retailer features remain active.";
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $_SESSION['flash_error'] = 'Failed to suspend operator access: ' . $e->getMessage();
        }

        $referer = $_SERVER['HTTP_REFERER'] ?? ('/admin/users/detail?id=' . $userId);
        header('Location: ' . $referer);
        exit;
    }

    /**
     * Super Admin restores operator access for a dual-role user or operator
     */
    public function restoreOperatorAccess(): void
    {
        if (!AuthService::isAdmin()) {
            http_response_code(403);
            exit;
        }

        if (!AuthService::validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $_SESSION['flash_error'] = 'Invalid CSRF token.';
            header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/admin/users'));
            exit;
        }

        $userId = (int)($_POST['user_id'] ?? 0);
        if ($userId <= 0) {
            $_SESSION['flash_error'] = 'Invalid user ID.';
            header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/admin/users'));
            exit;
        }

        $db = Database::getConnection();
        $adminId = AuthService::id();

        $uStmt = $db->prepare("SELECT id, name, role, assigned_district_id, assigned_block_id FROM users WHERE id = ?");
        $uStmt->execute([$userId]);
        $targetUser = $uStmt->fetch(PDO::FETCH_ASSOC);
        if (!$targetUser) {
            $_SESSION['flash_error'] = 'User not found.';
            header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/admin/users'));
            exit;
        }

        $db->beginTransaction();
        try {
            // Restore operator role assignment
            $stmt = $db->prepare("
                INSERT INTO user_role_assignments (user_id, role_code, is_active, granted_by, granted_at, created_at, updated_at)
                VALUES (?, 'OPERATOR', 1, ?, NOW(), NOW(), NOW())
                ON DUPLICATE KEY UPDATE is_active = 1, granted_by = VALUES(granted_by), granted_at = NOW(), revoked_by = NULL, revoked_at = NULL, updated_at = NOW()
            ");
            $stmt->execute([$userId, $adminId]);

            // Reactivate block assignments
            $stmtBlocks = $db->prepare("UPDATE operator_block_assignments SET is_active = 1, updated_at = NOW() WHERE operator_user_id = ?");
            $stmtBlocks->execute([$userId]);

            // Reactivate code assignments
            $stmt2 = $db->prepare("UPDATE operator_application_code_assignments SET is_active = 1, updated_at = NOW() WHERE operator_user_id = ?");
            $stmt2->execute([$userId]);

            AuditService::log('OPERATOR_ACCESS_RESTORED', 'USER', (string)$userId, [
                'user_id' => $userId,
                'user_name' => $targetUser['name'],
                'admin_id' => $adminId,
                'status' => 'ACTIVE'
            ], $adminId, 'ADMIN');

            $db->commit();
            $_SESSION['flash_success'] = "Operator access restored for '{$targetUser['name']}'.";
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $_SESSION['flash_error'] = 'Failed to restore operator access: ' . $e->getMessage();
        }

        $referer = $_SERVER['HTTP_REFERER'] ?? ('/admin/users/detail?id=' . $userId);
        header('Location: ' . $referer);
        exit;
    }

    /**
     * Super Admin removes operator access completely from a dual-role user
     * Does NOT delete the user, wallet, submitted applications, or passbook
     */
    public function removeOperatorAccess(): void
    {
        if (!AuthService::isAdmin()) {
            http_response_code(403);
            exit;
        }

        if (!AuthService::validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $_SESSION['flash_error'] = 'Invalid CSRF token.';
            header('Location: /admin/users');
            exit;
        }

        $userId = (int)($_POST['user_id'] ?? 0);
        if ($userId <= 0) {
            $_SESSION['flash_error'] = 'Invalid user ID.';
            header('Location: /admin/users');
            exit;
        }

        $db = Database::getConnection();
        $adminId = AuthService::id();

        $uStmt = $db->prepare("SELECT id, name, role, assigned_district_id, assigned_block_id FROM users WHERE id = ?");
        $uStmt->execute([$userId]);
        $targetUser = $uStmt->fetch(PDO::FETCH_ASSOC);
        if (!$targetUser) {
            $_SESSION['flash_error'] = 'User not found.';
            header('Location: /admin/users');
            exit;
        }

        $db->beginTransaction();
        try {
            // Mark operator role assignment revoked
            $stmt = $db->prepare("UPDATE user_role_assignments SET is_active = 0, revoked_by = ?, revoked_at = NOW(), updated_at = NOW() WHERE user_id = ? AND role_code = 'OPERATOR'");
            $stmt->execute([$adminId, $userId]);

            // Deactivate code assignments
            $stmt2 = $db->prepare("UPDATE operator_application_code_assignments SET is_active = 0, updated_at = NOW() WHERE operator_user_id = ?");
            $stmt2->execute([$userId]);

            // Deactivate block assignments
            $stmtBlocks = $db->prepare("UPDATE operator_block_assignments SET is_active = 0, updated_at = NOW() WHERE operator_user_id = ?");
            $stmtBlocks->execute([$userId]);

            // If user is a USER (retailer), clear operator district and block assignment
            if ($targetUser['role'] === 'USER') {
                $stmt3 = $db->prepare("UPDATE users SET assigned_district_id = NULL, assigned_block_id = NULL, updated_at = NOW() WHERE id = ?");
                $stmt3->execute([$userId]);
            }

            // Audit
            AuditService::log('OPERATOR_ACCESS_REVOKED', 'USER', (string)$userId, [
                'user_id' => $userId,
                'user_name' => $targetUser['name'],
                'district_id' => (int)($targetUser['assigned_district_id'] ?? 0),
                'block_id' => (int)($targetUser['assigned_block_id'] ?? 0),
                'admin_id' => $adminId,
                'previous_status' => 'ACTIVE',
                'new_status' => 'REVOKED'
            ], $adminId, 'ADMIN');

            $db->commit();
            $_SESSION['flash_success'] = "Operator access removed for '{$targetUser['name']}'. Retailer wallet, submissions, and passbook history remain intact.";
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $_SESSION['flash_error'] = 'Failed to remove operator access: ' . $e->getMessage();
        }

        $referer = $_SERVER['HTTP_REFERER'] ?? ('/admin/users/detail?id=' . $userId);
        header('Location: ' . $referer);
        exit;
    }

    /**
     * AJAX endpoint: fetch operator assignments
     * GET /admin/operators/api-assignments?user_id=123
     */
    public function apiOperatorAssignments(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        if (!AuthService::isAdmin()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Administrative privilege required.']);
            exit;
        }

        $userId = (int)($_GET['user_id'] ?? 0);
        if ($userId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid user ID.']);
            exit;
        }

        $db = Database::getConnection();
        $uStmt = $db->prepare("SELECT id, name, mobile, email, role, status, assigned_district_id, assigned_block_id FROM users WHERE id = ?");
        $uStmt->execute([$userId]);
        $user = $uStmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'User not found.']);
            exit;
        }

        $roleActiveStmt = $db->prepare("SELECT is_active FROM user_role_assignments WHERE user_id = ? AND role_code = 'OPERATOR' LIMIT 1");
        $roleActiveStmt->execute([$userId]);
        $hasRoleAssignment = $roleActiveStmt->fetch(PDO::FETCH_ASSOC);
        $operatorAccessActive = $hasRoleAssignment ? ((int)$hasRoleAssignment['is_active'] === 1) : ($user['role'] === 'OPERATOR');

        // Fetch assigned blocks from operator_block_assignments
        $bStmt = $db->prepare("
            SELECT oba.id, oba.district_id, oba.block_id, oba.is_active,
                   d.name as district_name, b.name as block_name
            FROM operator_block_assignments oba
            JOIN districts d ON oba.district_id = d.id
            JOIN blocks b ON oba.block_id = b.id
            WHERE oba.operator_user_id = ?
            ORDER BY oba.is_active DESC, d.name ASC, b.name ASC
        ");
        $bStmt->execute([$userId]);
        $blocks = $bStmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch codes per block
        $cStmt = $db->prepare("
            SELECT district_id, block_id, application_code, is_active
            FROM operator_application_code_assignments
            WHERE operator_user_id = ? AND is_active = 1
            ORDER BY application_code ASC
        ");
        $cStmt->execute([$userId]);
        $codes = $cStmt->fetchAll(PDO::FETCH_ASSOC);

        $codesByBlock = [];
        foreach ($codes as $c) {
            $key = (int)$c['district_id'] . '_' . (int)$c['block_id'];
            $codesByBlock[$key][] = $c['application_code'];
        }

        foreach ($blocks as &$blk) {
            $key = (int)$blk['district_id'] . '_' . (int)$blk['block_id'];
            $blk['codes'] = $codesByBlock[$key] ?? [];
        }
        unset($blk);

        echo json_encode([
            'success' => true,
            'user' => [
                'id' => (int)$user['id'],
                'name' => $user['name'],
                'mobile' => $user['mobile'],
                'role' => $user['role'],
                'operator_access_active' => $operatorAccessActive,
            ],
            'assignments' => $blocks
        ]);
        exit;
    }

    /**
     * Add or update an individual block assignment with application codes for an operator
     * POST /admin/operators/save-block-assignment
     */
    public function saveOperatorBlockAssignment(): void
    {
        if (!AuthService::isAdmin()) {
            http_response_code(403);
            if ($this->isAjaxRequest()) {
                echo json_encode(['success' => false, 'error' => 'Admin privilege required']);
                exit;
            }
            header('Location: /login');
            exit;
        }

        if (!AuthService::validateCsrfToken($_POST['csrf_token'] ?? '')) {
            if ($this->isAjaxRequest()) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
                exit;
            }
            $_SESSION['flash_error'] = 'Invalid security token (CSRF).';
            header('Location: /admin/users');
            exit;
        }

        $userId = (int)($_POST['user_id'] ?? 0);
        $districtId = (int)($_POST['district_id'] ?? 0);
        $blockId = (int)($_POST['block_id'] ?? 0);
        $status = strtoupper(trim((string)($_POST['status'] ?? 'ACTIVE')));
        $isActive = ($status === 'ACTIVE') ? 1 : 0;
        $codes = $_POST['application_codes'] ?? [];
        if (is_string($codes)) {
            $codes = array_filter(array_map('trim', explode(',', $codes)));
        }
        if (!is_array($codes)) {
            $codes = [];
        }

        if ($userId <= 0 || $districtId <= 0 || $blockId <= 0) {
            $msg = 'User, District, and Block are mandatory.';
            if ($this->isAjaxRequest()) {
                http_response_code(422);
                echo json_encode(['success' => false, 'error' => $msg]);
                exit;
            }
            $_SESSION['flash_error'] = $msg;
            header('Location: /admin/users');
            exit;
        }

        $db = Database::getConnection();
        $adminId = AuthService::id();

        // Validate user
        $uStmt = $db->prepare("SELECT id, name, role, status FROM users WHERE id = ?");
        $uStmt->execute([$userId]);
        $targetUser = $uStmt->fetch(PDO::FETCH_ASSOC);
        if (!$targetUser) {
            $msg = 'User not found.';
            if ($this->isAjaxRequest()) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => $msg]);
                exit;
            }
            $_SESSION['flash_error'] = $msg;
            header('Location: /admin/users');
            exit;
        }

        // Validate location
        $bStmt = $db->prepare("SELECT b.name as block_name, d.name as district_name FROM blocks b JOIN districts d ON b.district_id = d.id WHERE b.id = ? AND b.district_id = ? AND b.is_active = 1 LIMIT 1");
        $bStmt->execute([$blockId, $districtId]);
        $loc = $bStmt->fetch(PDO::FETCH_ASSOC);
        if (!$loc) {
            $msg = 'The selected block does not exist or does not belong to the selected district.';
            if ($this->isAjaxRequest()) {
                http_response_code(422);
                echo json_encode(['success' => false, 'error' => $msg]);
                exit;
            }
            $_SESSION['flash_error'] = $msg;
            header('Location: /admin/users');
            exit;
        }

        // Check if other active operators handle any of these codes in this block
        $overlappingStaff = [];
        if (!empty($codes)) {
            $phList = implode(',', array_fill(0, count($codes), '?'));
            $chkParams = array_merge([$districtId, $blockId, $userId], $codes);
            $overlapStmt = $db->prepare("
                SELECT DISTINCT u.name, oaca.application_code
                FROM operator_application_code_assignments oaca
                JOIN users u ON oaca.operator_user_id = u.id
                WHERE oaca.district_id = ? AND oaca.block_id = ? AND oaca.is_active = 1
                  AND oaca.operator_user_id != ?
                  AND oaca.application_code IN ({$phList})
                  AND u.status = 'ACTIVE'
            ");
            $overlapStmt->execute($chkParams);
            $overlappingStaff = $overlapStmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $db->beginTransaction();
        try {
            // Ensure USER has active OPERATOR role in user_role_assignments
            $insRole = $db->prepare("
                INSERT INTO user_role_assignments (user_id, role_code, is_active, granted_by, granted_at, created_at, updated_at)
                VALUES (?, 'OPERATOR', 1, ?, NOW(), NOW(), NOW())
                ON DUPLICATE KEY UPDATE is_active = 1, updated_at = NOW()
            ");
            $insRole->execute([$userId, $adminId]);

            // Save block assignment
            \App\Services\StaffRoutingService::saveOperatorBlockAssignment($userId, $districtId, $blockId, $isActive, $adminId, $db);

            // Sync codes
            \App\Services\StaffRoutingService::saveOperatorCodeAssignments($userId, $districtId, $blockId, $codes, $adminId);

            // Audit
            AuditService::log('OPERATOR_BLOCK_ASSIGNED', 'USER', (string)$userId, [
                'user_id' => $userId,
                'district_id' => $districtId,
                'block_id' => $blockId,
                'district_name' => $loc['district_name'],
                'block_name' => $loc['block_name'],
                'codes' => $codes,
                'is_active' => $isActive,
                'admin_id' => $adminId
            ], $adminId, 'ADMIN');

            $db->commit();

            $overlapNotice = '';
            if (!empty($overlappingStaff)) {
                $names = array_unique(array_column($overlappingStaff, 'name'));
                $overlapNotice = ' Note: Shared with active staff (' . implode(', ', $names) . ') using round-robin routing.';
            }

            $successMsg = "Assigned {$loc['district_name']} - {$loc['block_name']} to '{$targetUser['name']}' successfully.{$overlapNotice}";

            if ($this->isAjaxRequest()) {
                echo json_encode([
                    'success' => true,
                    'message' => $successMsg,
                    'overlap_warning' => !empty($overlappingStaff),
                    'overlapping_staff' => $overlappingStaff
                ]);
                exit;
            }

            $_SESSION['flash_success'] = $successMsg;
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $errMsg = 'Failed to save block assignment: ' . $e->getMessage();
            if ($this->isAjaxRequest()) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => $errMsg]);
                exit;
            }
            $_SESSION['flash_error'] = $errMsg;
        }

        $referer = $_SERVER['HTTP_REFERER'] ?? ('/admin/users/detail?id=' . $userId);
        header('Location: ' . $referer);
        exit;
    }

    /**
     * Toggle active/inactive status of a specific block assignment
     * POST /admin/operators/toggle-block-assignment
     */
    public function toggleOperatorBlockAssignment(): void
    {
        if (!AuthService::isAdmin()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Admin required']);
            exit;
        }

        if (!AuthService::validateCsrfToken($_POST['csrf_token'] ?? '')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
            exit;
        }

        $userId = (int)($_POST['user_id'] ?? 0);
        $districtId = (int)($_POST['district_id'] ?? 0);
        $blockId = (int)($_POST['block_id'] ?? 0);
        $isActive = !empty($_POST['is_active']) ? 1 : 0;

        $db = Database::getConnection();
        $adminId = AuthService::id();

        $db->beginTransaction();
        try {
            $stmt = $db->prepare("UPDATE operator_block_assignments SET is_active = ?, updated_at = NOW() WHERE operator_user_id = ? AND district_id = ? AND block_id = ?");
            $stmt->execute([$isActive, $userId, $districtId, $blockId]);

            // Also toggle corresponding codes for this block
            $stmt2 = $db->prepare("UPDATE operator_application_code_assignments SET is_active = ?, updated_at = NOW() WHERE operator_user_id = ? AND district_id = ? AND block_id = ?");
            $stmt2->execute([$isActive, $userId, $districtId, $blockId]);

            AuditService::log('OPERATOR_BLOCK_TOGGLED', 'USER', (string)$userId, [
                'user_id' => $userId,
                'district_id' => $districtId,
                'block_id' => $blockId,
                'is_active' => $isActive,
                'admin_id' => $adminId
            ], $adminId, 'ADMIN');

            $db->commit();
            echo json_encode(['success' => true, 'is_active' => $isActive]);
            exit;
        } catch (\Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
    }

    /**
     * Remove / deactivate a specific block assignment without touching unrelated assignments
     * POST /admin/operators/remove-block-assignment
     */
    public function removeOperatorBlockAssignment(): void
    {
        if (!AuthService::isAdmin()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Admin required']);
            exit;
        }

        if (!AuthService::validateCsrfToken($_POST['csrf_token'] ?? '')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
            exit;
        }

        $userId = (int)($_POST['user_id'] ?? 0);
        $districtId = (int)($_POST['district_id'] ?? 0);
        $blockId = (int)($_POST['block_id'] ?? 0);

        $db = Database::getConnection();
        $adminId = AuthService::id();

        $db->beginTransaction();
        try {
            // Deactivate specific block assignment
            $stmt = $db->prepare("UPDATE operator_block_assignments SET is_active = 0, updated_at = NOW() WHERE operator_user_id = ? AND district_id = ? AND block_id = ?");
            $stmt->execute([$userId, $districtId, $blockId]);

            // Deactivate codes for this specific block
            $stmt2 = $db->prepare("UPDATE operator_application_code_assignments SET is_active = 0, updated_at = NOW() WHERE operator_user_id = ? AND district_id = ? AND block_id = ?");
            $stmt2->execute([$userId, $districtId, $blockId]);

            AuditService::log('OPERATOR_BLOCK_DEACTIVATED', 'USER', (string)$userId, [
                'user_id' => $userId,
                'district_id' => $districtId,
                'block_id' => $blockId,
                'admin_id' => $adminId,
                'reason' => 'Block assignment removed without affecting other assignments'
            ], $adminId, 'ADMIN');

            $db->commit();

            if ($this->isAjaxRequest()) {
                echo json_encode(['success' => true, 'message' => 'Block assignment removed successfully.']);
                exit;
            }

            $_SESSION['flash_success'] = 'Block assignment removed successfully.';
            $referer = $_SERVER['HTTP_REFERER'] ?? ('/admin/users/detail?id=' . $userId);
            header('Location: ' . $referer);
            exit;
        } catch (\Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            if ($this->isAjaxRequest()) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                exit;
            }
            $_SESSION['flash_error'] = 'Failed to remove block assignment: ' . $e->getMessage();
            $referer = $_SERVER['HTTP_REFERER'] ?? ('/admin/users/detail?id=' . $userId);
            header('Location: ' . $referer);
            exit;
        }
    }

    private function isAjaxRequest(): bool
    {
        return (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
            || (str_starts_with($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json'))
            || (str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json'));
    }

    public function userDetail(): void
    {
        $userId = (int)($_GET['id'] ?? 0);
        $db = Database::getConnection();

        $stmt = $db->prepare("
            SELECT u.*, p.name as distributor_name, w.balance, w.total_credited, w.total_debited,
                   pd.name as profile_district_name, pd.name_hi as profile_district_name_hi,
                   pb.name as profile_block_name, pb.name_hi as profile_block_name_hi,
                   pb.district_id as block_actual_district_id
            FROM users u
            LEFT JOIN districts pd ON u.profile_district_id = pd.id
            LEFT JOIN blocks pb ON u.profile_block_id = pb.id
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

        // Permitted locations (legacy location_permissions)
        $permStmt = $db->prepare("
            SELECT lp.id, d.name as district_name, d.id as district_id, b.name as block_name, b.id as block_id
            FROM location_permissions lp
            JOIN districts d ON lp.district_id = d.id
            LEFT JOIN blocks b ON lp.block_id = b.id
            WHERE lp.user_id = ?
        ");
        $permStmt->execute([$userId]);
        $permissions = $permStmt->fetchAll();

        // Operator dual-role assignment info
        $roleActiveStmt = $db->prepare("SELECT is_active FROM user_role_assignments WHERE user_id = ? AND role_code = 'OPERATOR' LIMIT 1");
        $roleActiveStmt->execute([$userId]);
        $hasRoleAssignment = $roleActiveStmt->fetch(PDO::FETCH_ASSOC);
        $hasOperatorCapability = ($hasRoleAssignment !== false) || ($targetUser['role'] === 'OPERATOR');
        $operatorAccessActive = $hasRoleAssignment ? ((int)$hasRoleAssignment['is_active'] === 1) : ($targetUser['role'] === 'OPERATOR' && $targetUser['status'] === 'ACTIVE');

        // Fetch assigned blocks from operator_block_assignments
        $bStmt = $db->prepare("
            SELECT oba.id, oba.district_id, oba.block_id, oba.is_active,
                   d.name as district_name, b.name as block_name
            FROM operator_block_assignments oba
            JOIN districts d ON oba.district_id = d.id
            JOIN blocks b ON oba.block_id = b.id
            WHERE oba.operator_user_id = ?
            ORDER BY oba.is_active DESC, d.name ASC, b.name ASC
        ");
        $bStmt->execute([$userId]);
        $assignedBlockRows = $bStmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch codes per block
        $cStmt = $db->prepare("
            SELECT district_id, block_id, application_code, is_active
            FROM operator_application_code_assignments
            WHERE operator_user_id = ? AND is_active = 1
            ORDER BY application_code ASC
        ");
        $cStmt->execute([$userId]);
        $codeRows = $cStmt->fetchAll(PDO::FETCH_ASSOC);

        $codesByBlock = [];
        foreach ($codeRows as $c) {
            $key = (int)$c['district_id'] . '_' . (int)$c['block_id'];
            $codesByBlock[$key][] = $c['application_code'];
        }

        foreach ($assignedBlockRows as &$blk) {
            $key = (int)$blk['district_id'] . '_' . (int)$blk['block_id'];
            $blk['codes'] = $codesByBlock[$key] ?? [];
        }
        unset($blk);

        // Fetch all application codes configured in service_rate_rules or fallback list
        $allApplicationCodes = $db->query("
            SELECT DISTINCT application_code
            FROM service_rate_rules
            WHERE application_code IS NOT NULL AND application_code != '' AND application_code != '*' AND is_active = 1
            ORDER BY application_code ASC
        ")->fetchAll(PDO::FETCH_COLUMN);
        if (empty($allApplicationCodes)) {
            $allApplicationCodes = ['BICCO', 'BCCCO', 'BRCCO', 'BOBC', 'NCLCO', 'RTPS', 'LABOURCARD', 'OTHER'];
        }

        // Recent user transactions
        $passbook = WalletService::getPassbook($userId, 10, 0);

        // District list for adding permission / operator block
        $districts = $db->query("SELECT id, name, name_hi FROM districts WHERE is_active = 1 ORDER BY name ASC")->fetchAll();
        $distributors = $db->query("SELECT id, name FROM users WHERE role = 'DISTRIBUTOR' AND status = 'ACTIVE' ORDER BY name ASC")->fetchAll();

        $allBlocksGrouped = [];
        $blocksRows = $db->query("SELECT id, name, name_hi, district_id FROM blocks WHERE is_active = 1 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($blocksRows as $br) {
            $allBlocksGrouped[$br['district_id']][] = [
                'id' => (int)$br['id'],
                'name' => $br['name'],
                'name_hi' => $br['name_hi'] ?? ''
            ];
        }

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

        // 1. Validate target user existence
        $targetStmt = $db->prepare("SELECT id, role, status FROM users WHERE id = ?");
        $targetStmt->execute([$userId]);
        $targetUser = $targetStmt->fetch();

        if (!$targetUser) {
            $_SESSION['flash_error'] = 'Target user does not exist.';
            header('Location: /admin/users');
            exit;
        }

        // 2. Validate allowed status
        $allowedStatuses = ['ACTIVE', 'INACTIVE', 'BLOCKED', 'PENDING_APPROVAL'];
        if (!in_array($newStatus, $allowedStatuses, true)) {
            $_SESSION['flash_error'] = 'Invalid user account status requested.';
            header('Location: /admin/users/detail?id=' . $userId);
            exit;
        }

        // 3. Self-lockout prevention
        $currentAdminId = AuthService::id();
        if ($userId === $currentAdminId && $newStatus !== 'ACTIVE') {
            $_SESSION['flash_error'] = 'Action prevented: You cannot deactivate, block, or lock out your own administrator account.';
            header('Location: /admin/users/detail?id=' . $userId);
            exit;
        }

        // 4. Validate parent distributor if provided
        if ($distributorId !== null) {
            if ($distributorId === $userId) {
                $_SESSION['flash_error'] = 'A user cannot be assigned as their own parent distributor.';
                header('Location: /admin/users/detail?id=' . $userId);
                exit;
            }
            $dStmt = $db->prepare("SELECT id FROM users WHERE id = ? AND role = 'DISTRIBUTOR'");
            $dStmt->execute([$distributorId]);
            if (!$dStmt->fetch()) {
                $_SESSION['flash_error'] = 'Selected parent distributor is not a valid active distributor.';
                header('Location: /admin/users/detail?id=' . $userId);
                exit;
            }
        }

        if ($targetUser['role'] === 'OPERATOR') {
            $assignAll = 0;
        }

        $stmt = $db->prepare("
            UPDATE users
            SET status = ?,
                parent_distributor_id = ?,
                assigned_all_districts = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$newStatus, $distributorId, $assignAll, $userId]);

        AuditService::log('USER_UPDATE', 'USER', (string)$userId, ['role' => $targetUser['role'], 'status' => $newStatus, 'distributor_id' => $distributorId]);

        $_SESSION['flash_success'] = 'User account status updated successfully.';
        $redirectUrl = (!empty($_POST['redirect']) && $_POST['redirect'] === 'users') ? '/admin/users' : '/admin/users/detail?id=' . $userId;
        header('Location: ' . $redirectUrl);
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

        $action = trim($_GET['action'] ?? '');
        $targetType = trim($_GET['target_type'] ?? '');

        $where = ["1=1"];
        $params = [];

        if (!empty($action)) {
            $where[] = "(al.action LIKE :action OR al.details_json LIKE :action_det OR al.target_id LIKE :action_tgt)";
            $params[':action'] = '%' . $action . '%';
            $params[':action_det'] = '%' . $action . '%';
            $params[':action_tgt'] = '%' . $action . '%';
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
            LIMIT 150
        ");
        $stmt->execute($params);
        $logs = $stmt->fetchAll();

        // Dynamically get all target types present in audit_logs
        $targetTypesStmt = $db->query("SELECT DISTINCT target_type FROM audit_logs WHERE target_type IS NOT NULL AND target_type != '' ORDER BY target_type ASC");
        $availableTargetTypes = $targetTypesStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

        // Count total matching logs
        $countStmt = $db->prepare("SELECT COUNT(*) FROM audit_logs al WHERE {$whereClause}");
        $countStmt->execute($params);
        $totalCount = (int)$countStmt->fetchColumn();

        require dirname(__DIR__) . '/Views/admin/audit_logs.php';
    }

    public function whatsappLogs(): void
    {
        $db = Database::getConnection();

        $status = trim($_GET['status'] ?? '');
        $districtId = (int)($_GET['district_id'] ?? 0);
        $blockId = (int)($_GET['block_id'] ?? 0);
        $date = trim($_GET['date'] ?? '');
        $ref = trim($_GET['reference'] ?? '');

        $where = ["1=1"];
        $params = [];

        if (!empty($status)) {
            $where[] = "o.status = :status";
            $params[':status'] = strtoupper($status);
        }

        if ($districtId > 0) {
            $where[] = "a.district_id = :district_id";
            $params[':district_id'] = $districtId;
        }

        if ($blockId > 0) {
            $where[] = "a.block_id = :block_id";
            $params[':block_id'] = $blockId;
        }

        if (!empty($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $where[] = "DATE(o.created_at) = :date";
            $params[':date'] = $date;
        }

        if (!empty($ref)) {
            $where[] = "(a.app_ref LIKE :ref OR a.applicant_identifier LIKE :ref OR o.recipient_mobile LIKE :ref)";
            $params[':ref'] = '%' . $ref . '%';
        }

        $whereClause = implode(' AND ', $where);

        $stmt = $db->prepare("
            SELECT
                o.*,
                a.app_ref,
                a.applicant_identifier,
                a.applicant_name,
                d.name as district_name,
                b.name as block_name,
                u.name as operator_name
            FROM whatsapp_notification_outbox o
            JOIN applications a ON o.application_id = a.id
            LEFT JOIN districts d ON a.district_id = d.id
            LEFT JOIN blocks b ON a.block_id = b.id
            LEFT JOIN users u ON o.recipient_user_id = u.id
            WHERE {$whereClause}
            ORDER BY o.id DESC
            LIMIT 150
        ");
        $stmt->execute($params);
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Stats summary
        $statsStmt = $db->query("
            SELECT
                COUNT(*) as total,
                SUM(CASE WHEN status = 'SENT' THEN 1 ELSE 0 END) as sent_count,
                SUM(CASE WHEN status = 'PENDING' THEN 1 ELSE 0 END) as pending_count,
                SUM(CASE WHEN status = 'FAILED' THEN 1 ELSE 0 END) as failed_count,
                SUM(CASE WHEN status = 'SKIPPED' THEN 1 ELSE 0 END) as skipped_count
            FROM whatsapp_notification_outbox
        ");
        $stats = $statsStmt->fetch(PDO::FETCH_ASSOC) ?: [
            'total' => 0, 'sent_count' => 0, 'pending_count' => 0, 'failed_count' => 0, 'skipped_count' => 0
        ];

        // Fetch active districts for filter
        $districts = $db->query("SELECT id, name FROM districts WHERE is_active = 1 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
        $blocks = [];
        if ($districtId > 0) {
            $bStmt = $db->prepare("SELECT id, name FROM blocks WHERE district_id = ? AND is_active = 1 ORDER BY name ASC");
            $bStmt->execute([$districtId]);
            $blocks = $bStmt->fetchAll(PDO::FETCH_ASSOC);
        }

        require dirname(__DIR__) . '/Views/admin/whatsapp_logs.php';
    }

    public function whatsappRetry(): void
    {
        if (!AuthService::validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $_SESSION['flash_error'] = 'Invalid CSRF token.';
            header('Location: /admin/whatsapp/logs');
            exit;
        }

        $outboxId = (int)($_POST['id'] ?? 0);
        if ($outboxId <= 0) {
            $_SESSION['flash_error'] = 'Invalid delivery record ID.';
            header('Location: /admin/whatsapp/logs');
            exit;
        }

        $res = \App\Services\WhatsAppNotificationService::retryFailedMessage($outboxId, AuthService::id());
        if ($res['success']) {
            $_SESSION['flash_success'] = 'Notification retry initiated successfully.';
        } else {
            $_SESSION['flash_error'] = $res['error'] ?? 'Failed to retry notification.';
        }

        header('Location: /admin/whatsapp/logs');
        exit;
    }

    /**
     * Admin Staff Routing Management Page
     */
    public function staffRouting(): void
    {
        AuthService::requireRole(['ADMIN']);
        $db = Database::getConnection();

        $districtId = !empty($_GET['district_id']) ? (int)$_GET['district_id'] : 0;
        $blockId = !empty($_GET['block_id']) ? (int)$_GET['block_id'] : 0;
        $appCode = strtoupper(trim($_GET['app_code'] ?? ''));
        $staffStatus = strtoupper(trim($_GET['status'] ?? $_GET['staff_status'] ?? 'ALL'));
        if (!in_array($staffStatus, ['ALL', 'ACTIVE', 'INACTIVE'], true)) {
            $staffStatus = 'ALL';
        }
        $search = trim($_GET['search'] ?? '');

        // Fetch active districts for filter
        $districts = $db->query("SELECT id, name FROM districts WHERE is_active = 1 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

        $availableBlocks = [];
        if ($districtId > 0) {
            $bStmt = $db->prepare("SELECT id, name, district_id FROM blocks WHERE district_id = ? AND is_active = 1 ORDER BY name ASC");
            $bStmt->execute([$districtId]);
            $availableBlocks = $bStmt->fetchAll(PDO::FETCH_ASSOC);
        }

        // Query operators
        // Query operators (both dedicated and dual-role)
        $where = ["(u.role = 'OPERATOR' OR EXISTS (SELECT 1 FROM user_role_assignments ura WHERE ura.user_id = u.id AND ura.role_code = 'OPERATOR' AND ura.is_active = 1))"];
        $params = [];

        if ($staffStatus === 'ACTIVE') {
            $where[] = "u.status = 'ACTIVE'";
        } elseif ($staffStatus === 'INACTIVE') {
            $where[] = "u.status != 'ACTIVE'";
        }

        if (!empty($search)) {
            $where[] = "(u.name LIKE :q OR u.mobile LIKE :q OR u.email LIKE :q)";
            $params[':q'] = "%{$search}%";
        }

        $whereClause = implode(' AND ', $where);
        $sql = "
            SELECT u.id, u.name, u.mobile, u.email, u.status, u.role,
                   u.assigned_district_id, u.assigned_block_id
            FROM users u
            WHERE {$whereClause}
            ORDER BY u.status = 'ACTIVE' DESC, u.name ASC
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $allOperators = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Enhance operators with assigned codes and live queue counts
        $codeStmt = $db->prepare("
            SELECT application_code
            FROM operator_application_code_assignments
            WHERE operator_user_id = ? AND district_id = ? AND block_id = ? AND is_active = 1
            ORDER BY application_code ASC
        ");

        $countStmt = $db->prepare("
            SELECT
                SUM(CASE WHEN status = 'PENDING' THEN 1 ELSE 0 END) as pending_count,
                SUM(CASE WHEN status IN ('IN_PROCESS', 'UNDER_REVIEW') THEN 1 ELSE 0 END) as in_process_count
            FROM applications
            WHERE assigned_operator_id = ?
        ");

        $staffList = [];
        foreach ($allOperators as $op) {
            $opId = (int)$op['id'];
            $blocksAssigned = AuthService::getOperatorAssignedBlocks($opId);

            if ($districtId > 0) {
                $blocksAssigned = array_values(array_filter($blocksAssigned, fn($b) => (int)$b['district_id'] === $districtId));
            }
            if ($blockId > 0) {
                $blocksAssigned = array_values(array_filter($blocksAssigned, fn($b) => (int)$b['block_id'] === $blockId));
            }

            if (($districtId > 0 || $blockId > 0) && empty($blocksAssigned)) {
                continue;
            }

            $countStmt->execute([$opId]);
            $counts = $countStmt->fetch(PDO::FETCH_ASSOC) ?: ['pending_count' => 0, 'in_process_count' => 0];

            if (!empty($blocksAssigned)) {
                foreach ($blocksAssigned as $bIdx => $ba) {
                    $bDist = (int)$ba['district_id'];
                    $bBlk = (int)$ba['block_id'];
                    $codeStmt->execute([$opId, $bDist, $bBlk]);
                    $assignedCodes = $codeStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

                    if (!empty($appCode) && !in_array($appCode, $assignedCodes, true)) {
                        continue;
                    }

                    $opRow = $op;
                    $opRow['district_id'] = $bDist;
                    $opRow['block_id'] = $bBlk;
                    $opRow['district_name'] = $ba['district_name'];
                    $opRow['block_name'] = $ba['block_name'];
                    $opRow['assigned_codes'] = $assignedCodes;
                    $opRow['pending_count'] = (int)($counts['pending_count'] ?? 0);
                    $opRow['in_process_count'] = (int)($counts['in_process_count'] ?? 0);
                    $staffList[] = $opRow;
                }
            } else {
                if (!empty($appCode)) {
                    continue;
                }
                $opRow = $op;
                $opRow['district_id'] = 0;
                $opRow['block_id'] = 0;
                $opRow['district_name'] = 'Unassigned';
                $opRow['block_name'] = 'Unassigned';
                $opRow['assigned_codes'] = [];
                $opRow['pending_count'] = (int)($counts['pending_count'] ?? 0);
                $opRow['in_process_count'] = (int)($counts['in_process_count'] ?? 0);
                $staffList[] = $opRow;
            }
        }

        // Summary metrics
        $totalRules = 0;
        try {
            $totalRules = (int)$db->query("SELECT COUNT(*) FROM operator_application_code_assignments WHERE is_active = 1")->fetchColumn();
        } catch (\Throwable $e) {}
        $unassignedAppsCount = (int)$db->query("SELECT COUNT(*) FROM applications WHERE assigned_operator_id IS NULL AND status = 'PENDING'")->fetchColumn();

        // Populate variables expected by staff_routing.php view
        $operators = $staffList;
        $blocks = $availableBlocks;
        $filterDistrictId = $districtId;
        $filterBlockId = $blockId;
        $filterAppCode = $appCode;
        $filterStatus = $staffStatus;

        $allActiveCodes = $db->query("
            SELECT DISTINCT UPPER(TRIM(application_code))
            FROM service_rate_rules
            WHERE is_active = 1 AND application_code IS NOT NULL AND application_code != '' AND application_code != '*'
            ORDER BY application_code ASC
        ")->fetchAll(PDO::FETCH_COLUMN) ?: [];
        if (empty($allActiveCodes)) {
            $allActiveCodes = ['BCCCO', 'BICCO', 'BOBC', 'BRCCO', 'LABOURCARD', 'NCLCO', 'OTHER'];
        }

        require dirname(__DIR__) . '/Views/admin/staff_routing.php';
    }

    /**
     * POST /admin/staff-routing/save
     */
    public function saveStaffRouting(): void
    {
        AuthService::requireRole(['ADMIN']);

        if (!AuthService::validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $_SESSION['flash_error'] = 'Invalid CSRF token.';
            header('Location: /admin/staff-routing');
            exit;
        }

        $operatorId = (int)($_POST['operator_user_id'] ?? 0);
        $districtId = (int)($_POST['district_id'] ?? 0);
        $blockId = (int)($_POST['block_id'] ?? 0);
        $codes = (array)($_POST['application_codes'] ?? []);

        try {
            $res = \App\Services\StaffRoutingService::saveOperatorCodeAssignments(
                $operatorId,
                $districtId,
                $blockId,
                $codes,
                AuthService::id()
            );
            $_SESSION['flash_success'] = $res['message'];
        } catch (\Exception $e) {
            $_SESSION['flash_error'] = $e->getMessage();
        }

        header("Location: /admin/staff-routing?district_id={$districtId}&block_id={$blockId}");
        exit;
    }

    /**
     * GET /admin/staff-routing/api-block-data?district_id=X&block_id=Y
     * Returns JSON with active block operators and active application codes
     */
    public function apiGetBlockStaffRouting(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        AuthService::requireRole(['ADMIN']);
        $db = Database::getConnection();

        $districtId = (int)($_GET['district_id'] ?? 0);
        $blockId = (int)($_GET['block_id'] ?? 0);

        if ($districtId <= 0 || $blockId <= 0) {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'Invalid District or Block.']);
            exit;
        }

        $operators = \App\Services\StaffRoutingService::getOperatorsForBlock($districtId, $blockId, $db);
        $codes = \App\Services\StaffRoutingService::getActiveCodesForBlock($districtId, $blockId, $db);

        // Fetch block service status
        $sbaStmt = $db->prepare("SELECT status FROM service_block_availability WHERE district_id = ? AND block_id = ? LIMIT 1");
        $sbaStmt->execute([$districtId, $blockId]);
        $sbaStatus = $sbaStmt->fetchColumn() ?: 'DISABLED';

        // Fetch currently assigned codes for each operator
        $codeStmt = $db->prepare("
            SELECT application_code
            FROM operator_application_code_assignments
            WHERE operator_user_id = ? AND district_id = ? AND block_id = ? AND is_active = 1
        ");

        foreach ($operators as &$op) {
            $codeStmt->execute([(int)$op['id'], $districtId, $blockId]);
            $op['assigned_codes'] = $codeStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        }
        unset($op);

        $distinctCodes = array_values(array_unique(array_filter(array_map(function ($c) {
            return is_array($c) ? ($c['code'] ?? '') : (string)$c;
        }, $codes))));

        echo json_encode([
            'success' => true,
            'service_status' => $sbaStatus,
            'operators' => $operators,
            'rate_codes' => $codes,
            'active_codes' => $distinctCodes
        ]);
        exit;
    }
}

