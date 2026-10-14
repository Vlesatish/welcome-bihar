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
        $blockId = !empty($_POST['block_id']) ? (int)$_POST['block_id'] : null;
        $centerAddress = trim($_POST['center_address'] ?? '');
        $profileState = trim($_POST['profile_state'] ?? 'Bihar') ?: 'Bihar';
        $distributorId = !empty($_POST['distributor_id']) ? (int)$_POST['distributor_id'] : null;

        if (empty($name) || strlen($mobile) !== 10 || empty($email) || strlen($password) < 6 || empty($districtId)) {
            $_SESSION['flash_error'] = 'Please fill all required registration fields correctly.';
            header('Location: /register');
            exit;
        }

        $db = Database::getConnection();

        // Validate district is active
        $chkDist = $db->prepare("SELECT id FROM districts WHERE id = ? AND is_active = 1 LIMIT 1");
        $chkDist->execute([$districtId]);
        if (!$chkDist->fetch()) {
            $_SESSION['flash_error'] = 'Please select a valid active District.';
            header('Location: /register');
            exit;
        }

        // Validate block belongs to district if provided
        if ($blockId !== null) {
            $chkBlk = $db->prepare("SELECT id FROM blocks WHERE id = ? AND district_id = ? AND is_active = 1 LIMIT 1");
            $chkBlk->execute([$blockId, $districtId]);
            if (!$chkBlk->fetch()) {
                $_SESSION['flash_error'] = 'The selected Block does not belong to the selected District.';
                header('Location: /register');
                exit;
            }
        }

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
                INSERT INTO users (
                    uuid, role, name, mobile, email, password_hash, status,
                    parent_distributor_id, preferred_lang, center_name,
                    profile_state, profile_district_id, profile_block_id, center_address,
                    created_at
                ) VALUES (
                    ?, 'USER', ?, ?, ?, ?, 'ACTIVE',
                    ?, 'hi', ?,
                    ?, ?, ?, ?,
                    NOW()
                )
            ");
            $ins->execute([
                $uuid, $name, $mobile, $email, $hash,
                $distributorId, $centerName,
                $profileState, $districtId, $blockId, $centerAddress
            ]);
            $newUserId = (int)$db->lastInsertId();

            // Initialize user wallet
            WalletService::getOrCreateWallet($newUserId);

            AuditService::log('USER_REGISTER', 'USER', (string)$newUserId, [
                'name' => $name,
                'mobile' => $mobile,
                'profile_district_id' => $districtId,
                'profile_block_id' => $blockId
            ], $newUserId, 'USER');

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


    /**
     * Ensure redirect stays on same-site path, preventing open redirect vulnerabilities
     */
    public static function sanitizeSameSiteRedirect(string $url): string
    {
        // Strip potential header-injection characters
        $clean = str_replace(["\r", "\n", "\t", "\0"], '', trim($url));

        // Parse URL components
        $parts = parse_url($clean);
        if ($parts === false) {
            return '/';
        }

        // If an external host is present, verify it matches our current HTTP_HOST
        if (!empty($parts['host'])) {
            $allowedHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
            if (strcasecmp($parts['host'], $allowedHost) !== 0) {
                return '/';
            }
        }

        // Extract path and validate it is a single-slash relative path
        $path = $parts['path'] ?? '/';

        // Disallow protocol-relative URLs (//example.com) or backslashes (/\example.com)
        if (!str_starts_with($path, '/') || str_starts_with($path, '//') || str_starts_with($path, '/\\')) {
            $path = '/';
        }

        // Append query string if present
        if (!empty($parts['query'])) {
            $path .= '?' . $parts['query'];
        }

        return $path;
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
        if ($role === 'ADMIN' || $role === 'OPERATOR') {
            header('Location: /admin/dashboard');
        } elseif ($role === 'DISTRIBUTOR') {
            header('Location: /distributor/dashboard');
        } else {
            header('Location: /user/dashboard');
        }
        exit;
    }
}
