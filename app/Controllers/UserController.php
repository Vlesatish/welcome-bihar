<?php
namespace App\Controllers;

use App\Config\Database;
use App\Services\AuthService;
use App\Services\WalletService;
use App\Services\AuditService;

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
            SELECT a.id, a.app_ref, a.applicant_name, a.applicant_mobile, a.status, a.fee_debited, a.created_at,
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

        // Permitted districts
        $permStmt = $db->prepare("
            SELECT d.name as district_name, d.name_hi as district_name_hi, b.name as block_name
            FROM location_permissions lp
            JOIN districts d ON lp.district_id = d.id
            LEFT JOIN blocks b ON lp.block_id = b.id
            WHERE lp.user_id = ?
        ");
        $permStmt->execute([$user['id']]);
        $locations = $permStmt->fetchAll();

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
        $newPassword = (string)($_POST['new_password'] ?? '');

        $db = Database::getConnection();

        try {
            if (!empty($newPassword)) {
                if (strlen($newPassword) < 6) {
                    throw new \Exception('Password must be at least 6 characters.');
                }
                $hash = password_hash($newPassword, PASSWORD_BCRYPT);
                $stmt = $db->prepare("UPDATE users SET name = ?, center_name = ?, password_hash = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$name, $centerName, $hash, $userId]);
            } else {
                $stmt = $db->prepare("UPDATE users SET name = ?, center_name = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$name, $centerName, $userId]);
            }

            $_SESSION['user_name'] = $name;
            AuditService::log('PROFILE_UPDATE', 'USER', (string)$userId);

            $_SESSION['flash_success'] = 'Profile updated successfully.';
        } catch (\Exception $e) {
            $_SESSION['flash_error'] = 'Error: ' . $e->getMessage();
        }

        header('Location: /user/profile');
        exit;
    }
}
