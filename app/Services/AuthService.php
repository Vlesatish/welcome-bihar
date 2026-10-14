<?php
namespace App\Services;

use App\Config\Database;
use PDO;

class AuthService
{
    public static function check(): bool
    {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }

    public static function id(): ?int
    {
        return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    }

    public static function role(): ?string
    {
        return $_SESSION['user_role'] ?? null;
    }

    public static function user(): ?array
    {
        if (!self::check()) {
            return null;
        }

        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT id, uuid, role, name, mobile, email, status, parent_distributor_id, preferred_lang, center_name, profile_state, profile_district_id, profile_block_id, center_address, assigned_all_districts, assigned_district_id, assigned_block_id FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([self::id()]);
        $u = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$u) {
            return null;
        }
        if ($u['status'] !== 'ACTIVE') {
            return null;
        }
        return $u;
    }

    public static function isAdmin(): bool
    {
        return self::role() === 'ADMIN';
    }

    public static function isOperator(): bool
    {
        return self::role() === 'OPERATOR';
    }

    public static function isDistributor(): bool
    {
        return self::role() === 'DISTRIBUTOR';
    }

    public static function isUser(): bool
    {
        return self::role() === 'USER';
    }

    /**
     * Reusable authorization policy for block operators
     * Verifies:
     * - User exists
     * - Role is OPERATOR
     * - Status is ACTIVE
     * - District and Block assignments are present
     * - Application District equals operator District
     * - Application Block equals operator Block
     */
    public static function canOperatorAccessApplication(int $operatorId, int $applicationId): bool
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT id, role, status, assigned_district_id, assigned_block_id FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$operatorId]);
        $op = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$op || $op['role'] !== 'OPERATOR' || $op['status'] !== 'ACTIVE') {
            return false;
        }

        if (empty($op['assigned_district_id']) || empty($op['assigned_block_id'])) {
            return false;
        }

        $appStmt = $db->prepare("SELECT id, district_id, block_id FROM applications WHERE id = ? LIMIT 1");
        $appStmt->execute([$applicationId]);
        $app = $appStmt->fetch(PDO::FETCH_ASSOC);

        if (!$app) {
            return false;
        }

        return ((int)$app['district_id'] === (int)$op['assigned_district_id']) &&
               ((int)$app['block_id'] === (int)$op['assigned_block_id']);
    }

    /**
     * General authorization policy across roles
     */
    public static function canUserAccessApplication(int $userId, int $applicationId): bool
    {
        $db = Database::getConnection();
        $uStmt = $db->prepare("SELECT id, role, status, assigned_district_id, assigned_block_id FROM users WHERE id = ? LIMIT 1");
        $uStmt->execute([$userId]);
        $u = $uStmt->fetch(PDO::FETCH_ASSOC);
        if (!$u || $u['status'] !== 'ACTIVE') {
            return false;
        }

        if ($u['role'] === 'ADMIN') {
            return true;
        }
        if ($u['role'] === 'OPERATOR') {
            return self::canOperatorAccessApplication($userId, $applicationId);
        }

        $appStmt = $db->prepare("SELECT id, user_id, distributor_id FROM applications WHERE id = ? LIMIT 1");
        $appStmt->execute([$applicationId]);
        $app = $appStmt->fetch(PDO::FETCH_ASSOC);
        if (!$app) {
            return false;
        }

        if ($u['role'] === 'USER') {
            return (int)$app['user_id'] === $userId;
        }
        if ($u['role'] === 'DISTRIBUTOR') {
            return (int)$app['distributor_id'] === $userId;
        }
        return false;
    }

    public static function attempt(string $identifier, string $password): array
    {
        $db = Database::getConnection();
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        // Check rate limiting: max 5 failed attempts in past 15 mins
        $stmt = $db->prepare("
            SELECT COUNT(*) FROM login_attempts
            WHERE (ip_address = ? OR username_or_mobile = ?)
              AND attempt_time > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
              AND is_successful = 0
        ");
        $stmt->execute([$ip, $identifier]);
        $failedCount = (int)$stmt->fetchColumn();

        if ($failedCount >= 5) {
            return [
                'success' => false,
                'message' => __('rate_limit_exceeded', 'Too many failed login attempts. Please wait 15 minutes before trying again.')
            ];
        }

        // Search user by email or mobile
        $cleanMobile = preg_replace('/\D/', '', $identifier);

        // In production, disable demo seed accounts and default demo passwords
        $isProduction = strtolower(\App\Config\Config::get('APP_ENV', 'development')) === 'production'
            || strtolower(\App\Config\Config::get('APP_DEBUG', 'true')) === 'false';

        if ($isProduction) {
            $demoEmails = ['admin@welcomebihar.gov.in', 'distributor@welcomebihar.gov.in', 'operator@welcomebihar.gov.in'];
            $demoMobiles = ['9999900001', '9999900002', '9999900003'];
            if (in_array(strtolower(trim($identifier)), $demoEmails, true) || in_array($cleanMobile, $demoMobiles, true) || $password === 'Welcome@2026') {
                return [
                    'success' => false,
                    'message' => 'Demo credentials and seed accounts are disabled in production mode. Please use official registered credentials.'
                ];
            }
        }

        $stmt = $db->prepare("
            SELECT id, uuid, role, name, mobile, email, password_hash, status, preferred_lang
            FROM users
            WHERE (email = ? OR mobile = ?) LIMIT 1
        ");
        $stmt->execute([$identifier, $cleanMobile ?: $identifier]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            // Record failed attempt
            $logStmt = $db->prepare("INSERT INTO login_attempts (ip_address, username_or_mobile, attempt_time, is_successful) VALUES (?, ?, NOW(), 0)");
            $logStmt->execute([$ip, $identifier]);

            AuditService::log('LOGIN_FAILED', 'USER', $user ? (string)$user['id'] : null, ['identifier' => $identifier]);

            return [
                'success' => false,
                'message' => __('invalid_credentials', 'Invalid mobile/email or password.')
            ];
        }

        // Production Security Gate: Disable default seed demo credentials in production
        $isProduction = strtolower(\App\Config\Config::get('APP_ENV', 'development')) === 'production';
        if ($isProduction && password_verify('Welcome@2026', $user['password_hash'])) {
            AuditService::log('DEMO_CREDENTIALS_BLOCKED_IN_PRODUCTION', 'USER', (string)$user['id'], ['identifier' => $identifier]);
            return [
                'success' => false,
                'message' => 'Default demo seed credentials are deactivated in production. Please use your personalized administrator credentials.'
            ];
        }

        if ($user['status'] !== 'ACTIVE') {
            return [
                'success' => false,
                'message' => __('account_inactive', 'Your account is currently inactive or suspended. Please contact administrator.')
            ];
        }

        // Record successful attempt & clear old attempts
        $logStmt = $db->prepare("INSERT INTO login_attempts (ip_address, username_or_mobile, attempt_time, is_successful) VALUES (?, ?, NOW(), 1)");
        $logStmt->execute([$ip, $identifier]);

        // Regenerate session to prevent fixation
        session_regenerate_id(true);

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_uuid'] = $user['uuid'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_mobile'] = $user['mobile'];
        $_SESSION['lang'] = 'en';

        // Update last login
        $db->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?")->execute([$user['id']]);

        AuditService::log('LOGIN_SUCCESS', 'USER', (string)$user['id'], ['name' => $user['name'], 'role' => $user['role']], $user['id'], $user['role']);

        return ['success' => true, 'user' => $user];
    }

    public static function logout(): void
    {
        if (self::check()) {
            AuditService::log('LOGOUT', 'USER', (string)self::id());
        }

        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
    }

    public static function generateCsrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function validateCsrfToken(?string $token): bool
    {
        if (empty($token) || empty($_SESSION['csrf_token'])) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }

    public static function canAccessLocation(int $userId, int $districtId, ?int $blockId = null): bool
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT role, assigned_all_districts, assigned_district_id, assigned_block_id FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $u = $stmt->fetch();
        if (!$u) return false;

        // USER/RETAILER, ADMIN, and DISTRIBUTOR can access any active location for applications
        if (in_array($u['role'], ['USER', 'ADMIN', 'DISTRIBUTOR'], true) || (int)$u['assigned_all_districts'] === 1) {
            return true;
        }

        // OPERATOR is strictly limited to assigned district and block
        if ($u['role'] === 'OPERATOR') {
            if (empty($u['assigned_district_id'])) return false;
            if ((int)$u['assigned_district_id'] !== $districtId) return false;
            if ($blockId !== null && (int)$u['assigned_block_id'] !== $blockId) return false;
            return true;
        }

        return false;
    }
}

// CSRF helpers
if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return \App\Services\AuthService::generateCsrfToken();
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
    }
}
