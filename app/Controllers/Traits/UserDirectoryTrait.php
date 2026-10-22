<?php
declare(strict_types=1);

namespace App\Controllers\Traits;

use App\Config\Database;
use App\Services\AuthService;
use App\Services\AuditService;
use App\Services\CsvService;
use PDO;

trait UserDirectoryTrait
{
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
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = 25;
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

        require dirname(__DIR__, 2) . '/Views/admin/users.php';
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

        require dirname(__DIR__, 2) . '/Views/admin/location_report.php';
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
}
