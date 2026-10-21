<?php
namespace App\Controllers;

use App\Config\Database;
use App\Services\AuthService;
use App\Services\WalletService;
use App\Services\AuditService;
use PDO;
use Exception;

class UserController
{
    public function __construct()
    {
        if (!AuthService::check()) {
            header('Location: /login');
            exit;
        }
    }

    public function dashboard(): void
    {
        $userId = AuthService::id();
        $db = Database::getConnection();

        // 1. Current wallet balance
        $balance = WalletService::getBalance($userId);

        // 2. Application statistics for this user (matching Admin dashboard tabs)
        $statStmt = $db->prepare("
            SELECT
                COUNT(*) as total,
                SUM(CASE WHEN status = 'PENDING' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status IN ('IN_PROCESS', 'UNDER_REVIEW') THEN 1 ELSE 0 END) as in_process,
                SUM(CASE WHEN status IN ('APPROVED', 'COMPLETED') THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN status = 'REJECTED' THEN 1 ELSE 0 END) as rejected
            FROM applications
            WHERE user_id = ?
        ");
        $statStmt->execute([$userId]);
        $stats = $statStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $cntAll = (int)($stats['total'] ?? 0);
        $cntPending = (int)($stats['pending'] ?? 0);
        $cntInProcess = (int)($stats['in_process'] ?? 0);
        $cntApproved = (int)($stats['approved'] ?? 0);
        $cntRejected = (int)($stats['rejected'] ?? 0);

        // 3. Filters
        $currentTab = strtoupper(trim($_GET['status'] ?? 'ALL'));
        if (!in_array($currentTab, ['ALL', 'PENDING', 'IN_PROCESS', 'APPROVED', 'REJECTED'], true)) {
            $currentTab = 'ALL';
        }

        $districtId = !empty($_GET['district_id']) ? (int)$_GET['district_id'] : 0;
        $blockId = !empty($_GET['block_id']) ? (int)$_GET['block_id'] : 0;
        $appCode = strtoupper(trim($_GET['app_code'] ?? ''));
        $dateFilter = strtoupper(trim($_GET['date_filter'] ?? $_GET['dateFilter'] ?? 'ALL'));
        $search = trim($_GET['search'] ?? '');
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = 20;
        $offset = ($page - 1) * $limit;

        $where = ["a.user_id = :user_id"];
        $params = [':user_id' => $userId];

        if (!empty($districtId)) {
            $where[] = "a.district_id = :district_id";
            $params[':district_id'] = $districtId;
        }

        if (!empty($blockId)) {
            $where[] = "a.block_id = :block_id";
            $params[':block_id'] = $blockId;
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

        if (!empty($appCode)) {
            $where[] = "(a.applicant_identifier LIKE :app_code_like OR a.application_code = :app_code_exact)";
            $params[':app_code_like'] = $appCode . '%';
            $params[':app_code_exact'] = $appCode;
        }

        if ($dateFilter === 'TODAY') {
            $where[] = "DATE(a.created_at) = CURDATE()";
        } elseif ($dateFilter === 'WEEK') {
            $where[] = "a.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        } elseif ($dateFilter === 'MONTH') {
            $where[] = "a.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
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

        $cntStmt = $db->prepare("SELECT COUNT(*) FROM applications a WHERE {$whereClause}");
        $cntStmt->execute($params);
        $totalRecords = (int)$cntStmt->fetchColumn();
        $totalPages = ceil($totalRecords / $limit);

        $sql = "
            SELECT a.id, a.app_ref, a.batch_ref, a.applicant_name, a.applicant_mobile, a.applicant_identifier,
                   a.status, a.fee_debited, a.commission_credited, a.created_at,
                   a.result_file, a.is_refunded, a.admin_remarks, a.remarks,
                   a.district_id, a.block_id, a.application_code, a.assigned_operator_id,
                   dt.name_en as dt_name_en, dt.name_hi as dt_name_hi, dt.enable_chat,
                   d.name as district_name, d.name_hi as district_name_hi,
                   b.name as block_name, b.name_hi as block_name_hi
            FROM applications a
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

        $districts = $db->query("SELECT id, name, name_hi, state FROM districts WHERE is_active = 1 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

        $blocks = [];
        if ($districtId > 0) {
            $bStmt = $db->prepare("SELECT id, name, name_hi FROM blocks WHERE district_id = ? AND is_active = 1 ORDER BY name ASC");
            $bStmt->execute([$districtId]);
            $blocks = $bStmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $appCodes = [
            'BICCO' => 'BICCO',
            'BCCCO' => 'BCCCO',
            'BRCCO' => 'BRCCO',
            'BOBC'  => 'BOBC',
            'NCLCO' => 'NCLCO',
            'RTPS'  => 'RTPS',
            'LABOURCARD' => 'LABOURCARD',
            'OTHER' => 'OTHER'
        ];
        try {
            $ruleCodes = $db->query("SELECT DISTINCT application_code FROM service_rate_rules WHERE application_code IS NOT NULL AND application_code != '' AND application_code != '*'")->fetchAll(PDO::FETCH_COLUMN);
            foreach ($ruleCodes as $rc) {
                $cKey = strtoupper(trim((string)$rc));
                if (!isset($appCodes[$cKey])) {
                    $appCodes[$cKey] = $cKey;
                }
            }
        } catch (\Throwable $e) {}

        $selectedDistrictName = '';
        if ($districtId > 0) {
            foreach ($districts as $d) {
                if ((int)$d['id'] === $districtId) {
                    $selectedDistrictName = $d['name'];
                    break;
                }
            }
        }

        $selectedBlockName = '';
        if ($blockId > 0 && !empty($blocks)) {
            foreach ($blocks as $b) {
                if ((int)$b['id'] === $blockId) {
                    $selectedBlockName = $b['name'];
                    break;
                }
            }
        }

        require dirname(__DIR__) . '/Views/user/dashboard.php';
    }

    public function profile(): void
    {
        $user = AuthService::user();
        $db = Database::getConnection();

        // All active districts for profile location selector
        $districts = $db->query("SELECT id, name, name_hi, state FROM districts WHERE is_active = 1 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

        // Blocks for current user's profile district
        $blocks = [];
        if (!empty($user['profile_district_id'])) {
            $bStmt = $db->prepare("SELECT id, name, name_hi FROM blocks WHERE district_id = ? AND is_active = 1 ORDER BY name ASC");
            $bStmt->execute([$user['profile_district_id']]);
            $blocks = $bStmt->fetchAll(PDO::FETCH_ASSOC);
        }

        require dirname(__DIR__) . '/Views/user/profile.php';
    }

    public function updateProfile(): void
    {
        if (!AuthService::validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $_SESSION['flash_error'] = 'Invalid CSRF token.';
            header('Location: /user/profile');
            exit;
        }

        $userId = AuthService::id();
        $name = trim($_POST['name'] ?? '');
        $centerName = trim($_POST['center_name'] ?? '');
        $profileState = trim($_POST['profile_state'] ?? 'Bihar') ?: 'Bihar';
        $profileDistrictId = !empty($_POST['profile_district_id']) ? (int)$_POST['profile_district_id'] : null;
        $profileBlockId = !empty($_POST['profile_block_id']) ? (int)$_POST['profile_block_id'] : null;
        $centerAddress = trim($_POST['center_address'] ?? '');
        $newPassword = (string)($_POST['new_password'] ?? '');

        $db = Database::getConnection();

        try {
            if (empty($name)) {
                throw new \Exception('Full Name is required.');
            }

            // Validate profile district if provided
            if ($profileDistrictId !== null && $profileDistrictId > 0) {
                $dChk = $db->prepare("SELECT id FROM districts WHERE id = ? AND is_active = 1 LIMIT 1");
                $dChk->execute([$profileDistrictId]);
                if (!$dChk->fetch()) {
                    throw new \Exception('Please select a valid active District.');
                }
            } else {
                $profileDistrictId = null;
            }

            // Validate profile block belongs to profile district
            if ($profileBlockId !== null && $profileBlockId > 0) {
                if (!$profileDistrictId) {
                    throw new \Exception('Please select a District before selecting a Block.');
                }
                $bChk = $db->prepare("SELECT id FROM blocks WHERE id = ? AND district_id = ? AND is_active = 1 LIMIT 1");
                $bChk->execute([$profileBlockId, $profileDistrictId]);
                if (!$bChk->fetch()) {
                    throw new \Exception('Selected Block does not belong to the selected District.');
                }
            } else {
                $profileBlockId = null;
            }

            if (!empty($newPassword)) {
                if (strlen($newPassword) < 6) {
                    throw new \Exception('Password must be at least 6 characters.');
                }
                $hash = password_hash($newPassword, PASSWORD_BCRYPT);
                $stmt = $db->prepare("
                    UPDATE users
                    SET name = ?, center_name = ?, profile_state = ?, profile_district_id = ?,
                        profile_block_id = ?, center_address = ?, password_hash = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$name, $centerName, $profileState, $profileDistrictId, $profileBlockId, $centerAddress, $hash, $userId]);
            } else {
                $stmt = $db->prepare("
                    UPDATE users
                    SET name = ?, center_name = ?, profile_state = ?, profile_district_id = ?,
                        profile_block_id = ?, center_address = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$name, $centerName, $profileState, $profileDistrictId, $profileBlockId, $centerAddress, $userId]);
            }

            $_SESSION['user_name'] = $name;
            AuditService::log('PROFILE_UPDATE', 'USER', (string)$userId, [
                'name' => $name,
                'center_name' => $centerName,
                'profile_district_id' => $profileDistrictId,
                'profile_block_id' => $profileBlockId
            ]);

            $_SESSION['flash_success'] = 'Profile and Center Location updated successfully.';
        } catch (\Exception $e) {
            $_SESSION['flash_error'] = 'Error: ' . $e->getMessage();
        }

        header('Location: /user/profile');
        exit;
    }
}
