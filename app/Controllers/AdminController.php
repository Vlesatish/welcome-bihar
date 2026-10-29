<?php
namespace App\Controllers;

use App\Config\Database;
use App\Services\AuthService;
use App\Services\WalletService;
use App\Services\ApplicationService;
use App\Services\AuditService;
use App\Services\CsvService;
use App\Services\OperatorAuthorizationService;
use App\Services\RtpsCertificateService;
use PDO;
use Exception;

class AdminController
{

public function __construct()
    {
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';

        if (!AuthService::check()) {
            if ($uri === '/admin/applications/poll-status' || $uri === '/admin/applications/auto-fetch' || $uri === '/admin/rtps-queue/auto-fetch' || $uri === '/admin/applications/rtps-captcha') {
                http_response_code(401);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Unauthenticated']);
                exit;
            }
            header('Location: /login');
            exit;
        }

        $user = AuthService::user();
        if (!$user) {
            AuthService::logout();
            if ($uri === '/admin/applications/poll-status' || $uri === '/admin/applications/auto-fetch' || $uri === '/admin/rtps-queue/auto-fetch' || $uri === '/admin/applications/rtps-captcha') {
                http_response_code(401);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Unauthenticated']);
                exit;
            }
            header('Location: /login');
            exit;
        }

        $role = $user['role'];

        $activeMode = AuthService::activePortalMode();
        $isOperatorMode = ($activeMode === 'OPERATOR' && AuthService::canAccessOperatorMode());

        // Allow pollApplicationStatus to handle application-level access permissions
        if ($uri === '/admin/applications/poll-status') {
            return;
        }

        if ($role === 'OPERATOR' || $isOperatorMode) {
            $allowedUris = [
                '/admin/dashboard',
                '/admin/applications',
                '/admin/applications/poll-status',
                '/admin/applications/auto-fetch',
                '/admin/applications/rtps-captcha',
                '/admin/applications/trigger-rtps',
                '/admin/rtps-queue/auto-fetch',
                '/admin/rtps-queue/trigger'
            ];
            if (!in_array($uri, $allowedUris, true) && !str_starts_with($uri, '/admin/applications/')) {
                http_response_code(403);
                $_SESSION['flash_error'] = 'Access denied: Staff are restricted to Dashboard and Applications processing.';
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
        $db = Database::getConnection();

        // 1. Metric Counters for Top Cards (Global dataset totals)
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
        $reqStats['approved'] = (int)($reqStats['completed'] ?? 0);

        // 2. Load Active Districts from DB
        $districts = $db->query("SELECT id, name FROM districts WHERE is_active = 1 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

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
        } catch (\Throwable $e) {}
        try {
            $ruleCodes = $db->query("SELECT DISTINCT application_code FROM service_rate_rules WHERE application_code IS NOT NULL AND application_code != ''")->fetchAll(PDO::FETCH_COLUMN);
            foreach ($ruleCodes as $rc) {
                $codeOptions[$rc] = $rc;
            }
        } catch (\Throwable $e) {}
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
        $districtId = !empty($_GET['district_id']) ? (int)$_GET['district_id'] : 0;
        $blockId = !empty($_GET['block_id']) ? (int)$_GET['block_id'] : 0;
        $appCode = strtoupper(trim($_GET['app_code'] ?? ''));

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

        // 5. Build Backend SQL WHERE Clause
        $where = ["1=1"];
        $params = [];

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
            $where[] = "(a.applicant_identifier LIKE :search1 OR a.applicant_name LIKE :search2 OR a.applicant_mobile LIKE :search3 OR a.app_ref LIKE :search4 OR a.batch_ref LIKE :search5 OR u.name LIKE :search6 OR u.mobile LIKE :search7)";
            $searchVal = "%{$search}%";
            $params[':search1'] = $searchVal;
            $params[':search2'] = $searchVal;
            $params[':search3'] = $searchVal;
            $params[':search4'] = $searchVal;
            $params[':search5'] = $searchVal;
            $params[':search6'] = $searchVal;
            $params[':search7'] = $searchVal;
        }

        if ($dateFilter === 'TODAY') {
            $where[] = "DATE(a.created_at) = CURDATE()";
        } elseif ($dateFilter === 'WEEK') {
            $where[] = "a.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        } elseif ($dateFilter === 'MONTH') {
            $where[] = "a.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        }

        if ($districtId > 0) {
            $where[] = "a.district_id = :district_id";
            $params[':district_id'] = $districtId;
        }

        if ($blockId > 0) {
            $where[] = "a.block_id = :block_id";
            $params[':block_id'] = $blockId;
        }

        if (!empty($appCode)) {
            $where[] = "(a.applicant_identifier LIKE :app_code_like OR EXISTS (SELECT 1 FROM application_field_values afv WHERE afv.application_id = a.id AND afv.field_name = 'application_code' AND UPPER(afv.field_value) = :app_code))";
            $params[':app_code_like'] = $appCode . '%';
            $params[':app_code'] = $appCode;
        }

        $whereSql = implode(' AND ', $where);

        // 6. Pagination on entire dataset
        $countSql = "
            SELECT COUNT(*)
            FROM applications a
            JOIN users u ON a.user_id = u.id
            LEFT JOIN districts d ON a.district_id = d.id
            LEFT JOIN blocks b ON a.block_id = b.id
            JOIN data_types dt ON a.data_type_id = dt.id
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
            JOIN users u ON a.user_id = u.id
            LEFT JOIN districts d ON a.district_id = d.id
            LEFT JOIN blocks b ON a.block_id = b.id
            JOIN data_types dt ON a.data_type_id = dt.id
            WHERE {$whereSql}
            ORDER BY a.id DESC
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
                $fieldValuesByApp[$fv['application_id']][$fv['field_name']] = $fv['field_value'];
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

        header('Location: /admin/users');
        exit;
    }

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

        header('Location: /admin/users');
        exit;
    }

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

public function userDetail(): void
    {
        $userId = (int)($_GET['id'] ?? 0);
        $db = Database::getConnection();

        $stmt = $db->prepare("
            SELECT u.*, p.name as distributor_name, w.balance, w.total_credited, w.total_debited
            FROM users u
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

        $stmt = $db->prepare("
            UPDATE users
            SET status = ?,
                parent_distributor_id = ?,
                assigned_all_districts = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$newStatus, $distributorId, $assignAll, $userId]);

        AuditService::log('USER_UPDATE', 'USER', (string)$userId, ['status' => $newStatus, 'distributor_id' => $distributorId]);

        $_SESSION['flash_success'] = 'User account updated successfully.';
        header('Location: /admin/users/detail?id=' . $userId);
        exit;
    }

private function isAjaxRequest(): bool
    {
        return (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
            || (str_starts_with($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json'))
            || (str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json'));
    }

public function staffRouting(): void
    {
        AuthService::requireRole(['ADMIN']);
        $db = Database::getConnection();

        $filterDistrictId = !empty($_GET['district_id']) ? (int)$_GET['district_id'] : 0;
        $filterBlockId = !empty($_GET['block_id']) ? (int)$_GET['block_id'] : 0;
        $filterAppCode = strtoupper(trim((string)($_GET['app_code'] ?? '')));
        $rawStatus = strtoupper(trim((string)($_GET['status'] ?? ($_GET['staff_status'] ?? ''))));
        $filterStatus = in_array($rawStatus, ['ACTIVE', 'INACTIVE'], true) ? $rawStatus : '';
        $search = trim((string)($_GET['search'] ?? ''));

        $districtId = $filterDistrictId;
        $blockId = $filterBlockId;
        $appCode = $filterAppCode;
        $staffStatus = $filterStatus !== '' ? $filterStatus : 'ALL';

        // Ensure legacy operator district/block assignments on users table are synced to operator_block_assignments
        try {
            $db->exec("
                INSERT IGNORE INTO operator_block_assignments (operator_user_id, district_id, block_id, is_active, assigned_by, created_at, updated_at)
                SELECT id, assigned_district_id, assigned_block_id, 1, 1, NOW(), NOW()
                FROM users
                WHERE assigned_district_id IS NOT NULL AND assigned_district_id > 0
                  AND assigned_block_id IS NOT NULL AND assigned_block_id > 0
                  AND (
                      role = 'OPERATOR'
                      OR EXISTS (
                          SELECT 1 FROM user_role_assignments ura
                          WHERE ura.user_id = users.id AND ura.role_code = 'OPERATOR' AND ura.is_active = 1
                      )
                  )
            ");
        } catch (\Throwable $e) {}

        // Fetch active districts for filter
        $districts = $db->query("SELECT id, name FROM districts WHERE is_active = 1 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

        $blocks = [];
        if ($filterDistrictId > 0) {
            $bStmt = $db->prepare("SELECT id, name, district_id FROM blocks WHERE district_id = ? AND is_active = 1 ORDER BY name ASC");
            $bStmt->execute([$filterDistrictId]);
            $blocks = $bStmt->fetchAll(PDO::FETCH_ASSOC);
        }
        $availableBlocks = $blocks;

        // Fetch all active application codes for filter dropdown
        $availableCodeRows = \App\Services\ApplicationCodeFieldService::getAvailableApplicationCodes(null, $db);
        $allActiveCodes = array_values(array_unique(array_filter(array_column($availableCodeRows, 'application_code'))));

        // Query operators (dedicated OPERATOR role + dual-role operators + multi-block assignments)
        $where = [
            "(
                u.role = 'OPERATOR'
                OR EXISTS (
                    SELECT 1 FROM user_role_assignments ura
                    WHERE ura.user_id = u.id AND ura.role_code = 'OPERATOR' AND ura.is_active = 1
                )
            )"
        ];
        $params = [];

        if ($filterDistrictId > 0) {
            $where[] = "COALESCE(oba.district_id, u.assigned_district_id) = :district_id";
            $params[':district_id'] = $filterDistrictId;
        }

        if ($filterBlockId > 0) {
            $where[] = "COALESCE(oba.block_id, u.assigned_block_id) = :block_id";
            $params[':block_id'] = $filterBlockId;
        }

        if ($filterStatus === 'ACTIVE') {
            $where[] = "u.status = 'ACTIVE'";
        } elseif ($filterStatus === 'INACTIVE') {
            $where[] = "u.status != 'ACTIVE'";
        }

        if (!empty($search)) {
            $where[] = "(u.name LIKE :q OR u.mobile LIKE :q OR u.email LIKE :q)";
            $params[':q'] = "%{$search}%";
        }

        $whereClause = implode(' AND ', $where);
        $sql = "
            SELECT u.id, u.name, u.mobile, u.email, u.status,
                   COALESCE(oba.district_id, u.assigned_district_id) AS district_id,
                   COALESCE(oba.block_id, u.assigned_block_id) AS block_id,
                   d.name as district_name, b.name as block_name
            FROM users u
            LEFT JOIN operator_block_assignments oba ON oba.operator_user_id = u.id AND oba.is_active = 1
            LEFT JOIN districts d ON COALESCE(oba.district_id, u.assigned_district_id) = d.id
            LEFT JOIN blocks b ON COALESCE(oba.block_id, u.assigned_block_id) = b.id
            WHERE {$whereClause}
            GROUP BY u.id, COALESCE(oba.district_id, u.assigned_district_id), COALESCE(oba.block_id, u.assigned_block_id)
            ORDER BY u.status = 'ACTIVE' DESC, d.name ASC, b.name ASC, u.name ASC
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
              AND (? = 0 OR district_id = ?)
              AND (? = 0 OR block_id = ?)
        ");

        $staffList = [];
        $allPreparedList = [];
        foreach ($allOperators as $op) {
            $opDist = (int)($op['district_id'] ?? 0);
            $opBlk = (int)($op['block_id'] ?? 0);

            $assignedCodes = [];
            if ($opDist > 0 && $opBlk > 0) {
                $codeStmt->execute([(int)$op['id'], $opDist, $opBlk]);
                $assignedCodes = $codeStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
            }

            $countStmt->execute([(int)$op['id'], $opDist, $opDist, $opBlk, $opBlk]);
            $counts = $countStmt->fetch(PDO::FETCH_ASSOC) ?: ['pending_count' => 0, 'in_process_count' => 0];

            $op['district_id'] = $opDist;
            $op['block_id'] = $opBlk;
            $op['assigned_district_id'] = $opDist;
            $op['assigned_block_id'] = $opBlk;
            $op['assigned_codes'] = $assignedCodes;
            $op['pending_count'] = (int)($counts['pending_count'] ?? 0);
            $op['in_process_count'] = (int)($counts['in_process_count'] ?? 0);

            $allPreparedList[] = $op;

            if (!empty($filterAppCode) && !in_array($filterAppCode, $assignedCodes, true)) {
                continue;
            }

            $staffList[] = $op;
        }

        // If user navigated from Block Rates for an unassigned code (?district_id=X&block_id=Y&app_code=Z)
        // and no operator in that block has that code assigned yet, still show the block's operators so Admin can assign it
        if (empty($staffList) && !empty($allPreparedList) && $filterDistrictId > 0 && $filterBlockId > 0 && !empty($filterAppCode)) {
            $staffList = $allPreparedList;
        }

        $operators = $staffList;

        // Summary metrics
        $totalRules = (int)$db->query("SELECT COUNT(*) FROM operator_application_code_assignments WHERE is_active = 1")->fetchColumn();
        $unassignedAppsCount = (int)$db->query("SELECT COUNT(*) FROM applications WHERE assigned_operator_id IS NULL AND status = 'PENDING'")->fetchColumn();

        require dirname(__DIR__) . '/Views/admin/staff_routing.php';
    }

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


    /**
     * Admin: Complete User Location Directory & Users Management
     */
    public function users(): void
    {
        if (!AuthService::isAdmin()) {
            http_response_code(403);
            $_SESSION['flash_error'] = 'Administrative privilege required.';
            header('Location: /login');
            exit;
        }

        $db = Database::getConnection();

        $role = trim($_GET['role'] ?? '');
        $status = trim($_GET['status'] ?? '');
        $search = trim($_GET['search'] ?? '');
        $locationStatus = strtoupper(trim($_GET['location_status'] ?? ''));
        $districtId = !empty($_GET['district_id']) ? (int)$_GET['district_id'] : 0;
        $blockId = !empty($_GET['block_id']) ? (int)$_GET['block_id'] : 0;
        $dateFilter = strtoupper(trim($_GET['date_filter'] ?? 'ALL'));

        // Rule: Verify selected block belongs to selected district
        $locationMismatch = false;
        if ($districtId > 0 && $blockId > 0) {
            $chkBlk = $db->prepare("SELECT id FROM blocks WHERE id = ? AND district_id = ? LIMIT 1");
            $chkBlk->execute([$blockId, $districtId]);
            if (!$chkBlk->fetch()) {
                // Reject invalid combination server-side
                $blockId = 0;
                $locationMismatch = true;
            }
        }

        // Summary KPI statistics
        $statsRow = $db->query("
            SELECT 
                COUNT(*) as total_users,
                SUM(CASE WHEN u.profile_district_id IS NOT NULL AND u.profile_block_id IS NOT NULL THEN 1 ELSE 0 END) as location_set,
                SUM(CASE WHEN u.profile_district_id IS NULL OR u.profile_block_id IS NULL THEN 1 ELSE 0 END) as location_not_set,
                SUM(CASE WHEN u.status = 'ACTIVE' THEN 1 ELSE 0 END) as active_users,
                SUM(CASE WHEN u.status != 'ACTIVE' THEN 1 ELSE 0 END) as inactive_users,
                SUM(CASE WHEN u.profile_district_id IS NOT NULL AND u.profile_block_id IS NOT NULL AND EXISTS (SELECT 1 FROM blocks b_chk WHERE b_chk.id = u.profile_block_id AND b_chk.district_id != u.profile_district_id) THEN 1 ELSE 0 END) as invalid_locations
            FROM users u
        ")->fetch(PDO::FETCH_ASSOC) ?: [];

        $statTotal = (int)($statsRow['total_users'] ?? 0);
        $statLocationSet = (int)($statsRow['location_set'] ?? 0);
        $statLocationNotSet = (int)($statsRow['location_not_set'] ?? 0);
        $statActive = (int)($statsRow['active_users'] ?? 0);
        $statInactive = (int)($statsRow['inactive_users'] ?? 0);
        $statInvalid = (int)($statsRow['invalid_locations'] ?? 0);

        $where = ["1=1"];
        $params = [];

        if (!empty($role)) {
            $where[] = "u.role = :role";
            $params[':role'] = $role;
        }

        if (!empty($status)) {
            $where[] = "u.status = :status";
            $params[':status'] = $status;
        }

        if (!empty($search)) {
            $where[] = "(u.name LIKE :search_name OR u.mobile LIKE :search_mobile OR u.email LIKE :search_email OR u.center_name LIKE :search_center)";
            $searchVal = "%{$search}%";
            $params[':search_name'] = $searchVal;
            $params[':search_mobile'] = $searchVal;
            $params[':search_email'] = $searchVal;
            $params[':search_center'] = $searchVal;
        }

        if ($districtId > 0) {
            $where[] = "u.profile_district_id = :district_id";
            $params[':district_id'] = $districtId;
        }

        if ($blockId > 0) {
            $where[] = "u.profile_block_id = :block_id";
            $params[':block_id'] = $blockId;
        }

        if ($locationStatus === 'SET') {
            $where[] = "(u.profile_district_id IS NOT NULL AND u.profile_block_id IS NOT NULL)";
        } elseif ($locationStatus === 'NOT_SET') {
            $where[] = "(u.profile_district_id IS NULL OR u.profile_block_id IS NULL)";
        } elseif ($locationStatus === 'INVALID') {
            $where[] = "(u.profile_district_id IS NOT NULL AND u.profile_block_id IS NOT NULL AND EXISTS (SELECT 1 FROM blocks b_chk WHERE b_chk.id = u.profile_block_id AND b_chk.district_id != u.profile_district_id))";
        }

        if ($dateFilter === 'TODAY') {
            $where[] = "DATE(u.created_at) = CURDATE()";
        } elseif ($dateFilter === 'WEEK') {
            $where[] = "u.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        } elseif ($dateFilter === 'MONTH') {
            $where[] = "u.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFilter)) {
            $where[] = "DATE(u.created_at) = :date_filter";
            $params[':date_filter'] = $dateFilter;
        }

        $whereClause = implode(' AND ', $where);

        // Pagination
        // Pagination & Per-Page Limit (Supports 10, 25, 50, 100, 200, 500, 1000, or ALL)
        $limitInput = $_GET['limit'] ?? '25';
        if (strtoupper((string)$limitInput) === 'ALL') {
            $limit = 5000;
        } else {
            $limit = max(10, min(1000, (int)$limitInput));
            if (!in_array($limit, [10, 25, 50, 100, 200, 500, 1000], true)) {
                $limit = 25;
            }
        }
        $page = max(1, (int)($_GET['page'] ?? 1));
        $offset = ($page - 1) * $limit;

        $cntStmt = $db->prepare("SELECT COUNT(*) FROM users u WHERE {$whereClause}");
        $cntStmt->execute($params);
        $totalRecords = (int)$cntStmt->fetchColumn();
        $totalPages = (int)ceil($totalRecords / $limit);
        if ($totalPages > 0 && $page > $totalPages) {
            $page = $totalPages;
            $offset = ($page - 1) * $limit;
        }

        $sql = "
            SELECT u.id, u.uuid, u.role, u.name, u.mobile, u.email, u.status, u.center_name,
                   u.center_address, u.profile_state, u.profile_district_id, u.profile_block_id,
                   u.assigned_all_districts, u.assigned_district_id, u.assigned_block_id, u.created_at,
                   pd.name as profile_district_name,
                   pd.name_hi as profile_district_name_hi,
                   pb.name as profile_block_name,
                   pb.name_hi as profile_block_name_hi,
                   pb.district_id as block_actual_district_id,
                   d.name as assigned_district_name,
                   b.name as assigned_block_name,
                   p.name as distributor_name,
                   w.balance,
                   (SELECT COUNT(*) FROM applications WHERE user_id = u.id) as app_count,
                   (SELECT is_active FROM user_role_assignments WHERE user_id = u.id AND role_code = 'OPERATOR' LIMIT 1) as operator_access_active,
                   (SELECT COUNT(*) FROM user_role_assignments WHERE user_id = u.id AND role_code = 'OPERATOR') as has_operator_assignment,
                   (SELECT GROUP_CONCAT(application_code SEPARATOR ', ') FROM operator_application_code_assignments WHERE operator_user_id = u.id AND is_active = 1) as assigned_codes
            FROM users u
            LEFT JOIN districts pd ON u.profile_district_id = pd.id
            LEFT JOIN blocks pb ON u.profile_block_id = pb.id
            LEFT JOIN districts d ON u.assigned_district_id = d.id
            LEFT JOIN blocks b ON u.assigned_block_id = b.id
            LEFT JOIN users p ON u.parent_distributor_id = p.id
            LEFT JOIN wallets w ON u.id = w.user_id
            WHERE {$whereClause}
            ORDER BY u.id DESC
            LIMIT {$limit} OFFSET {$offset}
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $distributors = $db->query("SELECT id, name FROM users WHERE role = 'DISTRIBUTOR' AND status = 'ACTIVE' ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
        $districts = $db->query("SELECT id, name, name_hi FROM districts WHERE is_active = 1 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

        $allBlocksGrouped = [];
        $blocksRows = $db->query("SELECT id, name, name_hi, district_id FROM blocks WHERE is_active = 1 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($blocksRows as $br) {
            $allBlocksGrouped[$br['district_id']][] = [
                'id' => (int)$br['id'],
                'name' => $br['name'],
                'name_hi' => $br['name_hi'] ?? ''
            ];
        }

        $availableFilterBlocks = [];
        if ($districtId > 0 && isset($allBlocksGrouped[$districtId])) {
            $availableFilterBlocks = $allBlocksGrouped[$districtId];
        }

        $applicationCodes = $db->query("SELECT DISTINCT application_code FROM service_rate_rules WHERE application_code IS NOT NULL AND application_code != '' AND is_active = 1 ORDER BY application_code ASC")->fetchAll(PDO::FETCH_COLUMN);
        if (empty($applicationCodes)) {
            $applicationCodes = ['BICCO', 'BCCCO', 'BRCCO', 'BOBC', 'NCLCO', 'RTPS', 'OTHER'];
        }

        // Fetch all assigned blocks for operators (Work Assignment)
        $userAssignedBlocks = [];
        $opBlockRows = $db->query("
            SELECT oba.id, oba.operator_user_id, oba.district_id, oba.block_id, oba.is_active,
                   d.name as district_name, b.name as block_name
            FROM operator_block_assignments oba
            JOIN districts d ON oba.district_id = d.id
            JOIN blocks b ON oba.block_id = b.id
            ORDER BY oba.is_active DESC, d.name ASC, b.name ASC
        ")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($opBlockRows as $obr) {
            $userAssignedBlocks[(int)$obr['operator_user_id']][] = $obr;
        }

        // Fetch active codes per operator block
        $opCodesRows = $db->query("
            SELECT operator_user_id, district_id, block_id, application_code, is_active
            FROM operator_application_code_assignments
            WHERE is_active = 1
        ")->fetchAll(PDO::FETCH_ASSOC);
        $userBlockCodes = [];
        foreach ($opCodesRows as $ocr) {
            $key = (int)$ocr['operator_user_id'] . '_' . (int)$ocr['district_id'] . '_' . (int)$ocr['block_id'];
            $userBlockCodes[$key][] = $ocr['application_code'];
        }

        // Preserve filters for pagination and CSV export
        $filterParams = $_GET;
        unset($filterParams['page']);
        $queryString = http_build_query($filterParams);

        require dirname(__DIR__) . '/Views/admin/users.php';
    }

    /**
     * Export filtered Users to CSV
     */
    public function exportUsersCsv(): void
    {
        if (!AuthService::isAdmin()) {
            http_response_code(403);
            $_SESSION['flash_error'] = 'Administrative privilege required.';
            header('Location: /login');
            exit;
        }

        $db = Database::getConnection();

        $role = trim($_GET['role'] ?? '');
        $status = trim($_GET['status'] ?? '');
        $search = trim($_GET['search'] ?? '');
        $locationStatus = strtoupper(trim($_GET['location_status'] ?? ''));
        $districtId = !empty($_GET['district_id']) ? (int)$_GET['district_id'] : 0;
        $blockId = !empty($_GET['block_id']) ? (int)$_GET['block_id'] : 0;
        $dateFilter = strtoupper(trim($_GET['date_filter'] ?? 'ALL'));

        if ($districtId > 0 && $blockId > 0) {
            $chkBlk = $db->prepare("SELECT id FROM blocks WHERE id = ? AND district_id = ? LIMIT 1");
            $chkBlk->execute([$blockId, $districtId]);
            if (!$chkBlk->fetch()) {
                $blockId = 0;
            }
        }

        $where = ["1=1"];
        $params = [];

        if (!empty($role)) {
            $where[] = "u.role = :role";
            $params[':role'] = $role;
        }
        if (!empty($status)) {
            $where[] = "u.status = :status";
            $params[':status'] = $status;
        }
        if (!empty($search)) {
            $where[] = "(u.name LIKE :search_name OR u.mobile LIKE :search_mobile OR u.email LIKE :search_email OR u.center_name LIKE :search_center)";
            $searchVal = "%{$search}%";
            $params[':search_name'] = $searchVal;
            $params[':search_mobile'] = $searchVal;
            $params[':search_email'] = $searchVal;
            $params[':search_center'] = $searchVal;
        }
        if ($districtId > 0) {
            $where[] = "u.profile_district_id = :district_id";
            $params[':district_id'] = $districtId;
        }
        if ($blockId > 0) {
            $where[] = "u.profile_block_id = :block_id";
            $params[':block_id'] = $blockId;
        }
        if ($locationStatus === 'SET') {
            $where[] = "(u.profile_district_id IS NOT NULL AND u.profile_block_id IS NOT NULL)";
        } elseif ($locationStatus === 'NOT_SET') {
            $where[] = "(u.profile_district_id IS NULL OR u.profile_block_id IS NULL)";
        } elseif ($locationStatus === 'INVALID') {
            $where[] = "(u.profile_district_id IS NOT NULL AND u.profile_block_id IS NOT NULL AND EXISTS (SELECT 1 FROM blocks b_chk WHERE b_chk.id = u.profile_block_id AND b_chk.district_id != u.profile_district_id))";
        }
        if ($dateFilter === 'TODAY') {
            $where[] = "DATE(u.created_at) = CURDATE()";
        } elseif ($dateFilter === 'WEEK') {
            $where[] = "u.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        } elseif ($dateFilter === 'MONTH') {
            $where[] = "u.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFilter)) {
            $where[] = "DATE(u.created_at) = :date_filter";
            $params[':date_filter'] = $dateFilter;
        }

        $whereClause = implode(' AND ', $where);

        $sql = "
            SELECT u.id, u.name, u.mobile, u.email, u.center_name, u.role,
                   pd.name as profile_district_name,
                   pb.name as profile_block_name,
                   u.status, u.created_at,
                   (SELECT COUNT(*) FROM applications WHERE user_id = u.id) as app_count
            FROM users u
            LEFT JOIN districts pd ON u.profile_district_id = pd.id
            LEFT JOIN blocks pb ON u.profile_block_id = pb.id
            WHERE {$whereClause}
            ORDER BY u.id DESC
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        $headers = [
            'User ID',
            'Name',
            'Mobile',
            'Email',
            'Center Name',
            'Role',
            'Profile District',
            'Profile Block',
            'Status',
            'Registered Date',
            'Total Applications'
        ];

        $filename = 'users_directory_' . date('Ymd_His') . '.csv';

        $rows = [];
        while ($u = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $distName = !empty($u['profile_district_name']) ? $u['profile_district_name'] : 'Location Not Set';
            $blkName = !empty($u['profile_block_name']) ? $u['profile_block_name'] : 'Location Not Set';

            $rows[] = [
                $u['id'],
                $u['name'],
                $u['mobile'],
                $u['email'],
                $u['center_name'] ?: 'N/A',
                $u['role'],
                $distName,
                $blkName,
                $u['status'],
                date('d-m-Y H:i', strtotime($u['created_at'])),
                (int)$u['app_count']
            ];
        }

        CsvService::streamCsv($filename, $headers, $rows);
    }

    /**
     * Admin updates Profile Location (Center Home) for a user
     */
    public function updateUserProfileLocation(): void
    {
        if (!AuthService::isAdmin()) {
            http_response_code(403);
            $_SESSION['flash_error'] = 'Administrative privilege required.';
            header('Location: /login');
            exit;
        }

        if (!AuthService::validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $_SESSION['flash_error'] = 'Invalid CSRF token. Please try again.';
            header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/admin/users'));
            exit;
        }

        $userId = (int)($_POST['user_id'] ?? 0);
        $districtId = (int)($_POST['profile_district_id'] ?? 0);
        $blockId = (int)($_POST['profile_block_id'] ?? 0);
        $centerName = trim($_POST['center_name'] ?? '');
        $centerAddress = trim($_POST['center_address'] ?? '');

        if ($userId <= 0) {
            $_SESSION['flash_error'] = 'Invalid user identifier.';
            header('Location: /admin/users');
            exit;
        }

        $db = Database::getConnection();

        $uStmt = $db->prepare("SELECT id, name, role, profile_district_id, profile_block_id, center_name, center_address FROM users WHERE id = ?");
        $uStmt->execute([$userId]);
        $targetUser = $uStmt->fetch(PDO::FETCH_ASSOC);

        if (!$targetUser) {
            $_SESSION['flash_error'] = 'User not found.';
            header('Location: /admin/users');
            exit;
        }

        if ($districtId <= 0 || $blockId <= 0) {
            $_SESSION['flash_error'] = 'District and Block are both mandatory to set profile location.';
            header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/admin/users'));
            exit;
        }

        // Validate District is active
        $dChk = $db->prepare("SELECT id, name FROM districts WHERE id = ? AND is_active = 1 LIMIT 1");
        $dChk->execute([$districtId]);
        $dRow = $dChk->fetch(PDO::FETCH_ASSOC);
        if (!$dRow) {
            $_SESSION['flash_error'] = 'Please select a valid active District.';
            header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/admin/users'));
            exit;
        }

        // Validate Block belongs to District and is active
        $bChk = $db->prepare("SELECT id, name FROM blocks WHERE id = ? AND district_id = ? AND is_active = 1 LIMIT 1");
        $bChk->execute([$blockId, $districtId]);
        $bRow = $bChk->fetch(PDO::FETCH_ASSOC);
        if (!$bRow) {
            $_SESSION['flash_error'] = 'Selected Block does not belong to the selected District.';
            header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/admin/users'));
            exit;
        }

        $upd = $db->prepare("
            UPDATE users
            SET profile_district_id = ?, profile_block_id = ?,
                center_name = COALESCE(NULLIF(?, ''), center_name),
                center_address = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $upd->execute([$districtId, $blockId, $centerName, $centerAddress, $userId]);

        AuditService::log('ADMIN_UPDATE_USER_LOCATION', 'users', (string)$userId, [
            'admin_user_id' => AuthService::id(),
            'target_user_id' => $userId,
            'old_district_id' => $targetUser['profile_district_id'],
            'new_district_id' => $districtId,
            'old_block_id' => $targetUser['profile_block_id'],
            'new_block_id' => $blockId,
            'district_name' => $dRow['name'],
            'block_name' => $bRow['name']
        ], AuthService::id(), 'ADMIN');

        $_SESSION['flash_success'] = "Profile location for user {$targetUser['name']} updated to {$dRow['name']} > {$bRow['name']} successfully.";
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? ('/admin/users/detail?id=' . $userId)));
        exit;
    }

    /**
     * District and Block Location Report Directory
     */
    public function locationReport(): void
    {
        if (!AuthService::isAdmin()) {
            http_response_code(403);
            $_SESSION['flash_error'] = 'Administrative privilege required.';
            header('Location: /login');
            exit;
        }

        $db = Database::getConnection();

        $selectedDistrictId = !empty($_GET['district_id']) ? (int)$_GET['district_id'] : 0;
        $search = trim($_GET['search'] ?? '');
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = 50;
        $offset = ($page - 1) * $limit;

        // Overall stats
        $statsRow = $db->query("
            SELECT 
                COUNT(*) as total_users,
                SUM(CASE WHEN u.profile_district_id IS NOT NULL AND u.profile_block_id IS NOT NULL THEN 1 ELSE 0 END) as location_set,
                SUM(CASE WHEN u.profile_district_id IS NULL OR u.profile_block_id IS NULL THEN 1 ELSE 0 END) as location_not_set,
                SUM(CASE WHEN u.status = 'ACTIVE' THEN 1 ELSE 0 END) as active_users,
                SUM(CASE WHEN u.status != 'ACTIVE' THEN 1 ELSE 0 END) as inactive_users,
                SUM(CASE WHEN u.profile_district_id IS NOT NULL AND u.profile_block_id IS NOT NULL AND EXISTS (SELECT 1 FROM blocks b_chk WHERE b_chk.id = u.profile_block_id AND b_chk.district_id != u.profile_district_id) THEN 1 ELSE 0 END) as invalid_locations
            FROM users u
        ")->fetch(PDO::FETCH_ASSOC) ?: [];

        $statTotal = (int)($statsRow['total_users'] ?? 0);
        $statLocationSet = (int)($statsRow['location_set'] ?? 0);
        $statLocationNotSet = (int)($statsRow['location_not_set'] ?? 0);
        $statActive = (int)($statsRow['active_users'] ?? 0);
        $statInactive = (int)($statsRow['inactive_users'] ?? 0);
        $statInvalid = (int)($statsRow['invalid_locations'] ?? 0);

        $where = ["d.is_active = 1", "b.is_active = 1"];
        $params = [];

        if ($selectedDistrictId > 0) {
            $where[] = "d.id = :dist_id";
            $params[':dist_id'] = $selectedDistrictId;
        }

        if (!empty($search)) {
            $where[] = "(d.name LIKE :search_d OR b.name LIKE :search_b OR d.name_hi LIKE :search_dh OR b.name_hi LIKE :search_bh)";
            $searchVal = "%{$search}%";
            $params[':search_d'] = $searchVal;
            $params[':search_b'] = $searchVal;
            $params[':search_dh'] = $searchVal;
            $params[':search_bh'] = $searchVal;
        }

        $whereClause = implode(' AND ', $where);

        $cntStmt = $db->prepare("
            SELECT COUNT(*) 
            FROM districts d
            JOIN blocks b ON b.district_id = d.id
            WHERE {$whereClause}
        ");
        $cntStmt->execute($params);
        $totalRecords = (int)$cntStmt->fetchColumn();
        $totalPages = (int)ceil($totalRecords / $limit);

        // Aggregated counts per district and block
        $dataSql = "
            SELECT 
                d.id as district_id,
                d.name as district_name,
                d.name_hi as district_name_hi,
                b.id as block_id,
                b.name as block_name,
                b.name_hi as block_name_hi,
                (SELECT COUNT(*) FROM users u WHERE u.profile_district_id = d.id AND u.profile_block_id = b.id) as registered_users,
                (SELECT COUNT(*) FROM users u WHERE u.profile_district_id = d.id AND u.profile_block_id = b.id AND u.status = 'ACTIVE') as active_users,
                (SELECT COUNT(*) FROM applications a WHERE a.district_id = d.id AND a.block_id = b.id) as total_applications
            FROM districts d
            JOIN blocks b ON b.district_id = d.id
            WHERE {$whereClause}
            ORDER BY registered_users DESC, d.name ASC, b.name ASC
            LIMIT {$limit} OFFSET {$offset}
        ";
        $stmt = $db->prepare($dataSql);
        $stmt->execute($params);
        $reportRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch users with missing location
        $unassignedUsers = $db->query("
            SELECT u.id, u.name, u.mobile, u.email, u.role, u.status, u.created_at,
                   u.profile_district_id, u.profile_block_id,
                   pd.name as profile_district_name,
                   (SELECT COUNT(*) FROM applications WHERE user_id = u.id) as app_count
            FROM users u
            LEFT JOIN districts pd ON u.profile_district_id = pd.id
            WHERE u.profile_district_id IS NULL OR u.profile_block_id IS NULL
            ORDER BY u.id DESC
        ")->fetchAll(PDO::FETCH_ASSOC);

        // Fetch users with invalid district-block combinations
        $invalidUsers = $db->query("
            SELECT u.id, u.name, u.mobile, u.role, u.status,
                   u.profile_district_id, u.profile_block_id,
                   pd.name as selected_district_name,
                   pb.name as selected_block_name,
                   act_d.name as actual_block_district_name
            FROM users u
            JOIN districts pd ON u.profile_district_id = pd.id
            JOIN blocks pb ON u.profile_block_id = pb.id
            JOIN districts act_d ON pb.district_id = act_d.id
            WHERE pb.district_id != u.profile_district_id
            ORDER BY u.id DESC
        ")->fetchAll(PDO::FETCH_ASSOC);

        $districts = $db->query("SELECT id, name, name_hi FROM districts WHERE is_active = 1 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

        $filterParams = $_GET;
        unset($filterParams['page']);
        $queryString = http_build_query($filterParams);

        require dirname(__DIR__) . '/Views/admin/location_report.php';
    }

    /**
     * Export District & Block Location Report to CSV
     */
    public function exportLocationReportCsv(): void
    {
        if (!AuthService::isAdmin()) {
            http_response_code(403);
            $_SESSION['flash_error'] = 'Administrative privilege required.';
            header('Location: /login');
            exit;
        }

        $db = Database::getConnection();

        $selectedDistrictId = !empty($_GET['district_id']) ? (int)$_GET['district_id'] : 0;
        $search = trim($_GET['search'] ?? '');

        $where = ["d.is_active = 1", "b.is_active = 1"];
        $params = [];

        if ($selectedDistrictId > 0) {
            $where[] = "d.id = :dist_id";
            $params[':dist_id'] = $selectedDistrictId;
        }

        if (!empty($search)) {
            $where[] = "(d.name LIKE :search_d OR b.name LIKE :search_b OR d.name_hi LIKE :search_dh OR b.name_hi LIKE :search_bh)";
            $searchVal = "%{$search}%";
            $params[':search_d'] = $searchVal;
            $params[':search_b'] = $searchVal;
            $params[':search_dh'] = $searchVal;
            $params[':search_bh'] = $searchVal;
        }

        $whereClause = implode(' AND ', $where);

        $dataSql = "
            SELECT 
                d.name as district_name,
                b.name as block_name,
                (SELECT COUNT(*) FROM users u WHERE u.profile_district_id = d.id AND u.profile_block_id = b.id) as registered_users,
                (SELECT COUNT(*) FROM users u WHERE u.profile_district_id = d.id AND u.profile_block_id = b.id AND u.status = 'ACTIVE') as active_users,
                (SELECT COUNT(*) FROM applications a WHERE a.district_id = d.id AND a.block_id = b.id) as total_applications
            FROM districts d
            JOIN blocks b ON b.district_id = d.id
            WHERE {$whereClause}
            ORDER BY registered_users DESC, d.name ASC, b.name ASC
        ";
        $stmt = $db->prepare($dataSql);
        $stmt->execute($params);

        $headers = [
            'District',
            'Block',
            'Registered Users',
            'Active Users',
            'Applications'
        ];

        $filename = 'location_directory_report_' . date('Ymd_His') . '.csv';

        $rows = [];
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $rows[] = [
                $r['district_name'],
                $r['block_name'],
                (int)$r['registered_users'],
                (int)$r['active_users'],
                (int)$r['total_applications']
            ];
        }

        CsvService::streamCsv($filename, $headers, $rows);
    }


    /**
     * Trigger Batch Queue Check (Check eligible applications)
     * POST /admin/applications/trigger-rtps OR /admin/rtps-queue/trigger
     */
    public function triggerRtpsQueueCheck(): void
    {
        $isJson = (
            (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
            (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) ||
            !empty($_POST['ajax'])
        );

        if (!AuthService::isAdmin()) {
            if ($isJson) {
                http_response_code(403);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Access restricted: Administrator access required.']);
                exit;
            }
            $_SESSION['flash_error'] = 'Access restricted: Administrator access required to trigger queue worker.';
            header('Location: /admin/applications');
            exit;
        }

        // Validate CSRF if provided
        if (!empty($_POST['csrf_token']) && !AuthService::validateCsrfToken($_POST['csrf_token'])) {
            if ($isJson) {
                http_response_code(400);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Invalid security token (CSRF).']);
                exit;
            }
            $_SESSION['flash_error'] = 'Invalid security token (CSRF). Please try again.';
            header('Location: /admin/applications');
            exit;
        }

        $force = !isset($_POST['force']) || !empty($_POST['force']);
        $limit = isset($_POST['limit']) ? max(1, min(50, (int)$_POST['limit'])) : 25;

        try {
            $res = \App\Services\RtpsCertificateService::processScheduledQueue($limit, $force);

            if ($isJson) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'total_eligible' => $res['total_eligible'],
                    'processed' => $res['processed'],
                    'ready' => $res['ready'],
                    'unavailable' => $res['unavailable'],
                    'errors' => $res['errors'],
                    'message' => "Auto Check completed: {$res['processed']} applications checked, {$res['ready']} certificates downloaded & attached!"
                ]);
                exit;
            }

            $_SESSION['flash_success'] = "Queue worker executed: {$res['processed']} eligible applications checked. {$res['ready']} certificates attached. Next cycle runs every 5 hours (and daily 7:00 AM).";
        } catch (\Throwable $e) {
            if ($isJson) {
                http_response_code(500);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Queue trigger error: ' . $e->getMessage()]);
                exit;
            }
            $_SESSION['flash_error'] = 'Queue trigger error: ' . $e->getMessage();
        }

        $redirect = !empty($_POST['redirect_to']) ? $_POST['redirect_to'] : '/admin/applications';
        header('Location: ' . $redirect);
        exit;
    }

    /**
     * One-Click Auto Download and Attach Certificate from Bihar RTPS Portal
     * POST /admin/rtps-queue/auto-fetch
     */
    public function autoFetchRtpsCertificate(): void
    {
        if (!AuthService::isStaffOrAdmin()) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Unauthorized: Staff or Admin privilege required.']);
            exit;
        }

        // Release session lock immediately to prevent blocking other page requests
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $appId = (int)($_POST['application_id'] ?? $_POST['app_id'] ?? 0);
        if ($appId <= 0) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid application ID.']);
            exit;
        }

        try {
            $db = Database::getConnection();
            $rootPath = dirname(__DIR__, 2);

            // 1. Fetch and validate application record
            $appStmt = $db->prepare("
                SELECT id, status, result_file, application_code, applicant_identifier, applicant_name, district_id, block_id
                FROM applications
                WHERE id = ?
                LIMIT 1
            ");
            $appStmt->execute([$appId]);
            $app = $appStmt->fetch(PDO::FETCH_ASSOC);

            if (!$app) {
                http_response_code(404);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Application record not found.']);
                exit;
            }

            // 2. Prevent duplicate execution if certificate is already attached & verified on disk
            if ($app['status'] === 'COMPLETED' || $app['status'] === 'APPROVED' || !empty($app['result_file'])) {
                $resFile = $app['result_file'] ?? '';
                $isVerified = false;
                if (!empty($resFile)) {
                    $p1 = $rootPath . '/public' . (str_starts_with($resFile, '/') ? '' : '/') . $resFile;
                    $p2 = $rootPath . '/storage' . (str_starts_with($resFile, '/') ? '' : '/') . $resFile;
                    $isVerified = (file_exists($p1) && filesize($p1) >= 64) || (file_exists($p2) && filesize($p2) >= 64);
                }
                if ($isVerified) {
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => true,
                        'already_completed' => true,
                        'app_id' => $appId,
                        'status' => $app['status'],
                        'result_file' => $resFile,
                        'message' => 'Certificate is already attached and verified for this application.'
                    ]);
                    exit;
                }
            }

            // 3. Prevent duplicate requests from parallel processing if already checking recently
            $chkQ = $db->prepare("SELECT id, status, last_checked_at FROM rtps_certificate_queue WHERE application_id = ?");
            $chkQ->execute([$appId]);
            $existingQ = $chkQ->fetch(PDO::FETCH_ASSOC);

            if ($existingQ) {
                if ($existingQ['status'] === 'ATTACHED') {
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => true,
                        'already_completed' => true,
                        'app_id' => $appId,
                        'status' => 'ATTACHED',
                        'message' => 'Certificate already attached in queue.'
                    ]);
                    exit;
                }

                if ($existingQ['status'] === 'CHECKING') {
                    $lastTs = $existingQ['last_checked_at'] ? strtotime($existingQ['last_checked_at']) : 0;
                    if (time() - $lastTs < 60) {
                        header('Content-Type: application/json');
                        echo json_encode([
                            'success' => true,
                            'in_background' => true,
                            'already_checking' => true,
                            'app_id' => $appId,
                            'status' => 'CHECKING',
                            'message' => 'RTPS Portal verification already in progress for this application.'
                        ]);
                        exit;
                    }
                }
            }

            // 4. Reuse existing queue service method to ensure all required fields are populated
            // (application_code, application_number, applicant_name, district_id, block_id, service_type)
            \App\Services\RtpsCertificateService::enqueueApplication($appId);

            // 5. Update queue status to CHECKING immediately (protecting ATTACHED status)
            $db->prepare("
                UPDATE rtps_certificate_queue
                SET status = 'CHECKING',
                    last_checked_at = NOW(),
                    last_status_message = 'Background verification initiated',
                    updated_at = NOW()
                WHERE application_id = ? AND status != 'ATTACHED'
            ")->execute([$appId]);

            // Direct fast authoritative execution: Runs immediately without fragile nohup / CLI dependency on shared hosting
            @set_time_limit(90);
            $user = AuthService::user();
            $res = \App\Services\RtpsCertificateService::checkAndProcessApplication($appId, [
                'actor_user_id' => (int)($user['id'] ?? 1),
                'actor_role' => $user['role'] ?? 'ADMIN',
                'actor_name' => $user['name'] ?? 'Admin',
                'force' => true
            ]);

            header('Content-Type: application/json');
            echo json_encode($res);
            exit;
        } catch (\Throwable $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Error initiating background check: ' . $e->getMessage()
            ]);
            exit;
        }
    }

    /**
     * Fast non-blocking status poller for applications
     * GET /admin/applications/poll-status?id=123
     */
    public function pollApplicationStatus(): void
    {
        // Release session lock immediately so polling never blocks page navigation or clicks
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        header('Content-Type: application/json');

        // Enforce authentication
        if (!AuthService::check()) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Unauthenticated']);
            exit;
        }

        $appId = (int)($_GET['id'] ?? $_GET['application_id'] ?? 0);
        if ($appId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid application identifier']);
            exit;
        }

        $db = Database::getConnection();
        $user = AuthService::user();
        $role = $user['role'] ?? 'USER';
        $userId = (int)($user['id'] ?? 0);

        // Compatible query across all repository schemas (does not query nonexistent portal_status column)
        $stmt = $db->prepare("
            SELECT a.id, a.user_id, a.distributor_id, a.status, a.result_file, a.remarks, a.admin_remarks,
                   q.status as queue_status, q.last_status_message, q.certificate_file
            FROM applications a
            LEFT JOIN rtps_certificate_queue q ON a.id = q.application_id
            WHERE a.id = ?
            LIMIT 1
        ");
        $stmt->execute([$appId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Application not found']);
            exit;
        }

        // Enforce application-access permissions: Staff/Admin or owner (User/Distributor)
        if (!AuthService::isStaffOrAdmin()) {
            $appUserId = (int)$row['user_id'];
            $appDistId = (int)($row['distributor_id'] ?? 0);
            if ($role === 'DISTRIBUTOR' && $appDistId !== $userId) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Unauthorized access to application']);
                exit;
            } elseif ($role !== 'DISTRIBUTOR' && $appUserId !== $userId) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Unauthorized access to application']);
                exit;
            }
        }

        // Verify result / certificate file actually exists and is genuine on disk (>= 64 bytes)
        $resultFile = !empty($row['result_file']) ? $row['result_file'] : ($row['certificate_file'] ?? '');
        $isVerifiedFile = false;
        if (!empty($resultFile)) {
            $root = dirname(__DIR__, 2);
            $p1 = $root . '/public' . (str_starts_with($resultFile, '/') ? '' : '/') . $resultFile;
            $p2 = $root . '/storage' . (str_starts_with($resultFile, '/') ? '' : '/') . $resultFile;
            if ((file_exists($p1) && filesize($p1) >= 64) || (file_exists($p2) && filesize($p2) >= 64)) {
                $isVerifiedFile = true;
            }
        }

        // Success is ONLY shown when status is COMPLETED/APPROVED AND verified certificate file exists on disk
        $isCompleted = (($row['status'] === 'COMPLETED' || $row['status'] === 'APPROVED') && $isVerifiedFile);
        $isChecking = (($row['queue_status'] ?? '') === 'CHECKING');

        // Resolve portal status safely without assuming portal_status column exists in DB
        $portalStatus = $row['admin_remarks'] ?: ($row['remarks'] ?: ($row['last_status_message'] ?? ''));

        echo json_encode([
            'success' => true,
            'app_id' => $appId,
            'status' => $row['status'],
            'is_completed' => $isCompleted,
            'is_checking' => $isChecking,
            'queue_status' => $row['queue_status'] ?? '',
            'portal_status' => $portalStatus,
            'last_check_status' => $row['last_status_message'] ?? '',
            'result_file' => $isVerifiedFile ? $resultFile : '',
            'message' => $isCompleted ? 'Certificate attached successfully!' : ($row['last_status_message'] ?: 'Processing in background...')
        ]);
        exit;
    }


    /**
     * Fetch fresh ServiceOnline CAPTCHA session for Admin manual tracking modal
     * GET /admin/applications/rtps-captcha
     */
    public function getRtpsCaptcha(): void
    {
        if (!AuthService::isStaffOrAdmin()) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit;
        }

        $res = \App\Services\RtpsCertificateService::fetchCaptchaSession();
        header('Content-Type: application/json');
        echo json_encode($res);
        exit;
    }
}
