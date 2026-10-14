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

        // 2. Application statistics for this user
        $statStmt = $db->prepare("
            SELECT
                COUNT(*) as total,
                SUM(CASE WHEN status = 'PENDING' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'UNDER_REVIEW' THEN 1 ELSE 0 END) as under_review,
                SUM(CASE WHEN status = 'APPROVED' THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN status = 'REJECTED' THEN 1 ELSE 0 END) as rejected
            FROM applications
            WHERE user_id = ?
        ");
        $statStmt->execute([$userId]);
        $stats = $statStmt->fetch();

        // 3. Recent 5 applications
        $recentStmt = $db->prepare("
            SELECT a.id, a.app_ref, a.applicant_name, a.applicant_mobile, a.applicant_identifier, a.status, a.fee_debited, a.created_at,
                   dt.name_en as dt_name_en, dt.name_hi as dt_name_hi,
                   d.name as district_name, d.name_hi as district_name_hi,
                   b.name as block_name, b.name_hi as block_name_hi
            FROM applications a
            JOIN data_types dt ON a.data_type_id = dt.id
            JOIN districts d ON a.district_id = d.id
            JOIN blocks b ON a.block_id = b.id
            WHERE a.user_id = ?
            ORDER BY a.id DESC
            LIMIT 5
        ");
        $recentStmt->execute([$userId]);
        $recentApplications = $recentStmt->fetchAll();

        // 4. Recent wallet transactions
        $passbook = WalletService::getPassbook($userId, 5, 0);

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
