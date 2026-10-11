<?php
namespace App\Controllers;

use App\Config\Database;
use App\Services\AuthService;
use App\Services\TranslationService;
use App\Services\WalletService;
use App\Services\AuditService;

class AuthController
{
    public function showLogin(): void
    {
        if (AuthService::check()) {
            $this->redirectBasedOnRole();
            return;
        }

        require dirname(__DIR__) . '/Views/auth/login.php';
    }

    public function login(): void
    {
        if (!AuthService::validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $_SESSION['flash_error'] = 'Invalid request token (CSRF). Please try again.';
            header('Location: /login');
            exit;
        }

        $identifier = trim($_POST['identifier'] ?? '');
        $password = (string)($_POST['password'] ?? '');

        if (empty($identifier) || empty($password)) {
            $_SESSION['flash_error'] = __('required_field', 'Please enter your mobile/email and password.');
            header('Location: /login');
            exit;
        }

        $result = AuthService::attempt($identifier, $password);
        if ($result['success']) {
            $this->redirectBasedOnRole();
            return;
        }

        $_SESSION['flash_error'] = $result['message'];
        header('Location: /login');
        exit;
    }

    public function showRegister(): void
    {
        if (AuthService::check()) {
            $this->redirectBasedOnRole();
            return;
        }

        $db = Database::getConnection();
        // Fetch active districts for kiosk registration
        $districts = $db->query("SELECT id, name, name_hi FROM districts WHERE is_active = 1 ORDER BY name ASC")->fetchAll();
        // Fetch active distributors
        $distributors = $db->query("SELECT id, name, mobile, center_name FROM users WHERE role = 'DISTRIBUTOR' AND status = 'ACTIVE' ORDER BY name ASC")->fetchAll();

        require dirname(__DIR__) . '/Views/auth/register.php';
    }

    public function register(): void
    {
        if (!AuthService::validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $_SESSION['flash_error'] = 'Invalid CSRF token.';
            header('Location: /register');
            exit;
        }

        $name = trim($_POST['name'] ?? '');
        $mobile = preg_replace('/\D/', '', trim($_POST['mobile'] ?? ''));
        $email = strtolower(trim($_POST['email'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $centerName = trim($_POST['center_name'] ?? '');
        $districtId = (int)($_POST['district_id'] ?? 0);
        $distributorId = !empty($_POST['distributor_id']) ? (int)$_POST['distributor_id'] : null;

        if (empty($name) || strlen($mobile) !== 10 || empty($email) || strlen($password) < 6 || empty($districtId)) {
            $_SESSION['flash_error'] = 'Please fill all required registration fields correctly.';
            header('Location: /register');
            exit;
        }

        $db = Database::getConnection();

        // Check unique mobile or email
        $chk = $db->prepare("SELECT id FROM users WHERE mobile = ? OR email = ? LIMIT 1");
        $chk->execute([$mobile, $email]);
        if ($chk->fetch()) {
            $_SESSION['flash_error'] = 'Mobile number or email is already registered. Please login.';
            header('Location: /register');
            exit;
        }

        $db->beginTransaction();
        try {
            $uuid = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                mt_rand(0, 0xffff),
                mt_rand(0, 0x0fff) | 0x4000,
                mt_rand(0, 0x3fff) | 0x8000,
                mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
            );
            $hash = password_hash($password, PASSWORD_BCRYPT);

            $ins = $db->prepare("
                INSERT INTO users (uuid, role, name, mobile, email, password_hash, status, parent_distributor_id, preferred_lang, center_name, created_at)
                VALUES (?, 'USER', ?, ?, ?, ?, 'ACTIVE', ?, 'hi', ?, NOW())
            ");
            $ins->execute([$uuid, $name, $mobile, $email, $hash, $distributorId, $centerName]);
            $newUserId = (int)$db->lastInsertId();

            // Grant location permission for selected district
            $perm = $db->prepare("INSERT INTO location_permissions (user_id, district_id, block_id, created_at) VALUES (?, ?, NULL, NOW())");
            $perm->execute([$newUserId, $districtId]);

            // Initialize user wallet
            WalletService::getOrCreateWallet($newUserId);

            AuditService::log('USER_REGISTER', 'USER', (string)$newUserId, ['name' => $name, 'mobile' => $mobile], $newUserId, 'USER');

            $db->commit();

            $_SESSION['flash_success'] = 'Registration successful! You may now login.';
            header('Location: /login');
            exit;
        } catch (\Exception $e) {
            $db->rollBack();
            $_SESSION['flash_error'] = 'Registration failed: ' . $e->getMessage();
            header('Location: /register');
            exit;
        }
    }

    public function switchLang(): void
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? '/';
        header('Location: ' . $referer);
        exit;
    }

    public function logout(): void
    {
        AuthService::logout();
        header('Location: /login');
        exit;
    }

    private function redirectBasedOnRole(): void
    {
        $role = AuthService::role();
        if ($role === 'ADMIN') {
            header('Location: /admin/dashboard');
        } elseif ($role === 'DISTRIBUTOR') {
            header('Location: /distributor/dashboard');
        } else {
            header('Location: /user/dashboard');
        }
        exit;
    }
}
