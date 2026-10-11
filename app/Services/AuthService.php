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
        $stmt = $db->prepare("SELECT id, uuid, role, name, mobile, email, status, parent_distributor_id, preferred_lang, center_name, assigned_all_districts FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([self::id()]);
        return $stmt->fetch() ?: null;
    }

    public static function isAdmin(): bool
    {
        return self::role() === 'ADMIN';
    }

    public static function isDistributor(): bool
    {
        return self::role() === 'DISTRIBUTOR';
    }

    public static function isUser(): bool
    {
        return self::role() === 'USER';
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
        if (!empty($user['preferred_lang'])) {
            $_SESSION['lang'] = $user['preferred_lang'];
        }

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
        $stmt = $db->prepare("SELECT role, assigned_all_districts FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $u = $stmt->fetch();
        if (!$u) return false;

        // Admin has universal access
        if ($u['role'] === 'ADMIN' || (int)$u['assigned_all_districts'] === 1) {
            return true;
        }

        // Check location_permissions table
        if ($blockId !== null) {
            $permStmt = $db->prepare("
                SELECT COUNT(*) FROM location_permissions
                WHERE user_id = ? AND district_id = ? AND (block_id IS NULL OR block_id = ?)
            ");
            $permStmt->execute([$userId, $districtId, $blockId]);
        } else {
            $permStmt = $db->prepare("
                SELECT COUNT(*) FROM location_permissions
                WHERE user_id = ? AND district_id = ?
            ");
            $permStmt->execute([$userId, $districtId]);
        }

        return ((int)$permStmt->fetchColumn()) > 0;
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
