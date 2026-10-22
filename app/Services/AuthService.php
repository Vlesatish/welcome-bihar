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

    public static function getCurrentUser(): ?array
    {
        return self::user();
    }

    public static function isAdmin(): bool
    {
        return self::role() === 'ADMIN';
    }

    public static function isOperator(): bool
    {
        return self::role() === 'OPERATOR' || (self::canAccessOperatorMode() && self::activePortalMode() === 'OPERATOR');
    }

    public static function isDistributor(): bool
    {
        return self::role() === 'DISTRIBUTOR';
    }

    public static function isUser(): bool
    {
        return self::role() === 'USER' || (self::canAccessRetailerMode() && self::activePortalMode() === 'RETAILER');
    }

    /**
     * Check if a user has a specific role (checks both users table and user_role_assignments)
     */
    public static function hasRole(int $userId, string $role): bool
    {
        $role = strtoupper(trim($role));
        $db = Database::getConnection();

        $stmt = $db->prepare("SELECT role, status FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $u = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$u || $u['status'] !== 'ACTIVE') {
            return false;
        }

        if ($u['role'] === $role) {
            return true;
        }

        if (in_array($role, ['USER', 'OPERATOR'], true)) {
            $chk = $db->prepare("
                SELECT 1 FROM user_role_assignments
                WHERE user_id = ? AND role_code = ? AND is_active = 1
                LIMIT 1
            ");
            $chk->execute([$userId, $role]);
            if ($chk->fetchColumn()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the authenticated user has a specific capability
     */
    public static function hasCapability(string $capability): bool
    {
        $cap = strtolower(trim($capability));
        return match ($cap) {
            'submit_applications' => self::canSubmitApplications(),
            'process_applications' => self::canProcessApplications(),
            'access_operator_mode' => self::canAccessOperatorMode(),
            'access_retailer_mode' => self::canAccessRetailerMode(),
            'admin_manage' => self::isAdmin(),
            default => false,
        };
    }

    /**
     * Whether the current account can submit applications in the current mode
     */
    public static function canSubmitApplications(): bool
    {
        if (!self::check()) return false;
        $role = self::role();
        if ($role === 'ADMIN' || $role === 'DISTRIBUTOR') return true;

        $mode = self::activePortalMode();
        return ($mode === 'RETAILER') && self::canAccessRetailerMode();
    }

    /**
     * Whether the current account can process applications in the current mode
     */
    public static function canProcessApplications(): bool
    {
        if (!self::check()) return false;
        $role = self::role();
        if ($role === 'ADMIN') return true;

        $mode = self::activePortalMode();
        return ($mode === 'OPERATOR') && self::canAccessOperatorMode();
    }

    /**
     * Whether the user has operator access privileges
     */
    public static function canAccessOperatorMode(?int $userId = null): bool
    {
        $uid = $userId ?? self::id();
        if (!$uid) return false;

        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT role, status, assigned_district_id, assigned_block_id FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$uid]);
        $u = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$u || $u['status'] !== 'ACTIVE') {
            return false;
        }

        if ($u['role'] === 'ADMIN') {
            return true;
        }

        // Check if operator has active assignments in operator_block_assignments or legacy users table
        $hasBlock = false;
        try {
            $chk = $db->prepare("SELECT 1 FROM operator_block_assignments WHERE operator_user_id = ? AND is_active = 1 LIMIT 1");
            $chk->execute([$uid]);
            if ($chk->fetchColumn()) {
                $hasBlock = true;
            }
        } catch (\Throwable $e) {}

        if (!$hasBlock) {
            return false;
        }

        if ($u['role'] === 'OPERATOR') {
            $ura = $db->prepare("SELECT is_active FROM user_role_assignments WHERE user_id = ? AND role_code = 'OPERATOR' LIMIT 1");
            $ura->execute([$uid]);
            $val = $ura->fetchColumn();
            if ($val !== false && (int)$val === 0) {
                return false;
            }
            return true;
        }

        // For other roles (e.g. USER), check active assignment in user_role_assignments
        $ura = $db->prepare("SELECT is_active FROM user_role_assignments WHERE user_id = ? AND role_code = 'OPERATOR' AND is_active = 1 LIMIT 1");
        $ura->execute([$uid]);
        return (bool)$ura->fetchColumn();
    }

    public static function isStaffOrAdmin(): bool
    {
        return self::isAdmin() || self::isOperator();
    }

    public static function hasDualRole(?int $userId = null): bool
    {
        return self::isDualRole($userId);
    }

    public static function hasRoleCapability(int $userId, string $role): bool
    {
        return self::hasRole($userId, $role);
    }

    public static function switchRoleMode(string $mode): bool
    {
        return self::setActivePortalMode($mode);
    }

    public static function getActiveRoleMode(): string
    {
        return self::activePortalMode();
    }

    /**
     * Whether the user has retailer access privileges
     */
    public static function canAccessRetailerMode(?int $userId = null): bool
    {
        $uid = $userId ?? self::id();
        if (!$uid) return false;

        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT role, status FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$uid]);
        $u = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$u || $u['status'] !== 'ACTIVE') {
            return false;
        }

        if (in_array($u['role'], ['USER', 'DISTRIBUTOR'], true)) {
            return true;
        }

        $ura = $db->prepare("SELECT is_active FROM user_role_assignments WHERE user_id = ? AND role_code = 'USER' AND is_active = 1 LIMIT 1");
        $ura->execute([$uid]);
        return (bool)$ura->fetchColumn();
    }

    /**
     * Whether user has both Retailer and Operator capability
     */
    public static function isDualRole(?int $userId = null): bool
    {
        $uid = $userId ?? self::id();
        if (!$uid) return false;

        return self::canAccessRetailerMode($uid) && self::canAccessOperatorMode($uid);
    }

    /**
     * Get active portal mode: 'RETAILER', 'OPERATOR', 'ADMIN', 'DISTRIBUTOR'
     */
    public static function activePortalMode(): string
    {
        $role = self::role();
        if ($role === 'ADMIN') return 'ADMIN';
        if ($role === 'DISTRIBUTOR') return 'DISTRIBUTOR';

        $uid = self::id();
        if (!$uid) return 'GUEST';

        $hasOperator = self::canAccessOperatorMode($uid);
        $hasRetailer = self::canAccessRetailerMode($uid);

        if ($hasOperator && $hasRetailer) {
            $sessMode = strtoupper((string)($_SESSION['active_portal_mode'] ?? ''));
            if ($sessMode === 'OPERATOR') {
                return 'OPERATOR';
            }
            return 'RETAILER';
        }

        if ($hasOperator) {
            return 'OPERATOR';
        }

        return 'RETAILER';
    }

    /**
     * Set active portal mode for dual-role user
     */
    public static function setActivePortalMode(string $mode): bool
    {
        $uid = self::id();
        if (!$uid) return false;

        $mode = strtoupper(trim($mode));
        if ($mode === 'OPERATOR') {
            if (!self::canAccessOperatorMode($uid)) {
                return false;
            }
            $_SESSION['active_portal_mode'] = 'OPERATOR';
            return true;
        }

        if ($mode === 'RETAILER') {
            if (!self::canAccessRetailerMode($uid)) {
                return false;
            }
            $_SESSION['active_portal_mode'] = 'RETAILER';
            return true;
        }

        return false;
    }

    /**
     * Validate active portal mode server-side on every request
     */
    public static function validateAndSyncActiveMode(): string
    {
        $uid = self::id();
        if (!$uid) return 'RETAILER';

        $role = self::role();
        if ($role === 'ADMIN' || $role === 'DISTRIBUTOR') return (string)$role;

        $hasOperator = self::canAccessOperatorMode($uid);
        $hasRetailer = self::canAccessRetailerMode($uid);

        if ($hasOperator && $hasRetailer) {
            $mode = strtoupper((string)($_SESSION['active_portal_mode'] ?? ''));
            if (!in_array($mode, ['RETAILER', 'OPERATOR'], true)) {
                $mode = 'RETAILER';
                $_SESSION['active_portal_mode'] = 'RETAILER';
            }
            return $mode;
        } elseif ($hasOperator) {
            $_SESSION['active_portal_mode'] = 'OPERATOR';
            return 'OPERATOR';
        } else {
            // Operator access was revoked or never present
            if (isset($_SESSION['active_portal_mode']) && $_SESSION['active_portal_mode'] === 'OPERATOR') {
                $_SESSION['active_portal_mode'] = 'RETAILER';
                $_SESSION['flash_error'] = 'Operator access has been suspended or revoked. Switched to Retailer Mode.';
                $currUri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';
                if (str_starts_with($currUri, '/admin/') && !headers_sent()) {
                    header('Location: /user/dashboard');
                    exit;
                }
            } else {
                $_SESSION['active_portal_mode'] = 'RETAILER';
            }
            return 'RETAILER';
        }
    }

    public static function requireRole($roles): void
    {
        if (!self::check()) {
            header('Location: /login');
            exit;
        }

        $userRole = self::role();
        $allowed = is_array($roles) ? $roles : [$roles];

        // Also permit dual-role users acting in allowed mode
        $activeMode = self::activePortalMode();
        $isPermitted = in_array($userRole, $allowed, true)
            || (in_array('OPERATOR', $allowed, true) && $activeMode === 'OPERATOR' && self::canAccessOperatorMode())
            || (in_array('USER', $allowed, true) && $activeMode === 'RETAILER' && self::canAccessRetailerMode());

        if (!$isPermitted) {
            if (str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/api/')) {
                http_response_code(403);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'error' => 'Forbidden: insufficient role permissions']);
                exit;
            }
            $_SESSION['flash_error'] = 'Access denied: You do not have permission to access this resource.';
            if (self::canAccessRetailerMode()) {
                header('Location: /user/dashboard');
            } else {
                header('Location: /admin/dashboard');
            }
            exit;
        }
    }

    /**
     * Reusable authorization policy for block operators
     * Verifies:
     * - User exists and status is ACTIVE
     * - Has active operator capability
     * - District and Block assignments are present
     * - Application District equals operator District
     * - Application Block equals operator Block
     * - Strict self-processing protection: application creator CANNOT be assigned or process
     * - Application Code matches configured operator assignment (if codes configured)
     */
    /**
     * Get all active block and code assignments for an operator
     */
    public static function getOperatorAssignments(int $operatorId): array
    {
        $db = Database::getConnection();
        $blocks = self::getOperatorAssignedBlocks($operatorId);
        $result = [];
        foreach ($blocks as $blk) {
            $distId = (int)$blk['district_id'];
            $blkId = (int)$blk['block_id'];
            $cStmt = $db->prepare("
                SELECT application_code 
                FROM operator_application_code_assignments
                WHERE operator_user_id = ? AND district_id = ? AND block_id = ? AND is_active = 1
                ORDER BY application_code ASC
            ");
            $cStmt->execute([$operatorId, $distId, $blkId]);
            $codes = $cStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
            $result[] = [
                'operator_user_id' => $operatorId,
                'district_id' => $distId,
                'district_name' => $blk['district_name'],
                'block_id' => $blkId,
                'block_name' => $blk['block_name'],
                'application_codes' => $codes,
            ];
        }
        return $result;
    }

    /**
     * Get all distinct assigned districts for an operator
     */
    public static function getOperatorAssignedDistricts(int $operatorId): array
    {
        $db = Database::getConnection();
        $blocks = self::getOperatorAssignedBlocks($operatorId);
        $distIds = array_values(array_unique(array_filter(array_column($blocks, 'district_id'))));
        if (empty($distIds)) {
            return [];
        }
        $in = implode(',', array_map('intval', $distIds));
        return $db->query("SELECT id, name, name_hi, state FROM districts WHERE id IN ({$in}) ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Get active block assignments for an operator, optionally filtered by district_id
     */
    public static function getOperatorAssignedBlocks(int $operatorId, ?int $districtId = null): array
    {
        $db = Database::getConnection();
        $params = [$operatorId];
        $distSql = "";
        if ($districtId !== null && $districtId > 0) {
            $distSql = " AND oba.district_id = ?";
            $params[] = $districtId;
        }

        $stmt = $db->prepare("
            SELECT oba.district_id, oba.block_id as id, oba.block_id, oba.is_active,
                   d.name as district_name, b.name as block_name, b.name as name
            FROM operator_block_assignments oba
            JOIN districts d ON oba.district_id = d.id
            JOIN blocks b ON oba.block_id = b.id
            WHERE oba.operator_user_id = ? AND oba.is_active = 1 {$distSql}
            ORDER BY d.name ASC, b.name ASC
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $rows ?: [];
    }

    public static function isOperatorAssignedToBlock(int $operatorId, int $districtId, int $blockId): bool
    {
        if ($operatorId <= 0 || $districtId <= 0 || $blockId <= 0) {
            return false;
        }

        $db = Database::getConnection();
        $chk = $db->prepare("
            SELECT 1 FROM operator_block_assignments
            WHERE operator_user_id = ? AND district_id = ? AND block_id = ? AND is_active = 1
            LIMIT 1
        ");
        $chk->execute([$operatorId, $districtId, $blockId]);
        return (bool)$chk->fetchColumn();
    }

    public static function canOperatorAccessApplication(int $operatorId, int $applicationId): bool
    {
        if ($operatorId <= 0 || $applicationId <= 0) {
            return false;
        }

        return OperatorAuthorizationService::canAccessApplication($operatorId, $applicationId);
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

        $appStmt = $db->prepare("SELECT id, user_id, distributor_id FROM applications WHERE id = ? LIMIT 1");
        $appStmt->execute([$applicationId]);
        $app = $appStmt->fetch(PDO::FETCH_ASSOC);
        if (!$app) {
            return false;
        }

        // Check if viewing in Staff mode
        $activeMode = self::activePortalMode();
        if ($activeMode === 'OPERATOR' && self::canAccessOperatorMode($userId)) {
            return self::canOperatorAccessApplication($userId, $applicationId);
        }

        if ($u['role'] === 'DISTRIBUTOR') {
            return (int)$app['distributor_id'] === $userId;
        }

        // Retailer mode: user views only their own submitted applications
        return (int)$app['user_id'] === $userId;
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

        // Check user credentials
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

        // If in strict production and demo logins are not explicitly allowed, warn if using default Welcome@2026
        $isProduction = strtolower(\App\Config\Config::get('APP_ENV', 'development')) === 'production';
        $allowDemo = filter_var(\App\Config\Config::get('APP_ALLOW_DEMO', 'true'), FILTER_VALIDATE_BOOLEAN);
        if ($isProduction && !$allowDemo && password_verify('Welcome@2026', $user['password_hash'])) {
            AuditService::log('DEMO_CREDENTIALS_BLOCKED_IN_PRODUCTION', 'USER', (string)$user['id'], ['identifier' => $identifier]);
            return [
                'success' => false,
                'message' => 'Default demo password is deactivated in production. Please change your password or set APP_ALLOW_DEMO=true in .env.'
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

        // Initialize active portal mode: dual-role defaults to last valid selected mode, or OPERATOR if assigned work exists, otherwise RETAILER
        if (self::canAccessOperatorMode((int)$user['id']) && self::canAccessRetailerMode((int)$user['id'])) {
            $lastMode = strtoupper((string)($_SESSION['active_portal_mode'] ?? ''));
            if ($lastMode === 'OPERATOR' || $lastMode === 'RETAILER') {
                $_SESSION['active_portal_mode'] = $lastMode;
            } elseif ($user['role'] === 'OPERATOR' || !empty(self::getOperatorAssignedBlocks((int)$user['id']))) {
                $_SESSION['active_portal_mode'] = 'OPERATOR';
            } else {
                $_SESSION['active_portal_mode'] = 'RETAILER';
            }
        } elseif (self::canAccessOperatorMode((int)$user['id'])) {
            $_SESSION['active_portal_mode'] = 'OPERATOR';
        } else {
            $_SESSION['active_portal_mode'] = 'RETAILER';
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
        $stmt = $db->prepare("SELECT role, assigned_all_districts, assigned_district_id, assigned_block_id FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $u = $stmt->fetch();
        if (!$u) return false;

        // Retailer mode, ADMIN, and DISTRIBUTOR can access any active location for applications
        if (self::canAccessRetailerMode($userId) || in_array($u['role'], ['USER', 'ADMIN', 'DISTRIBUTOR'], true) || (int)($u['assigned_all_districts'] ?? 0) === 1) {
            return true;
        }

        // Dedicated OPERATOR or staff checking location access
        if ($blockId !== null) {
            return self::isOperatorAssignedToBlock($userId, $districtId, $blockId);
        }

        // District level check
        $distChk = $db->prepare("SELECT 1 FROM operator_block_assignments WHERE operator_user_id = ? AND district_id = ? AND is_active = 1 LIMIT 1");
        $distChk->execute([$userId, $districtId]);
        if ($distChk->fetchColumn()) {
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
