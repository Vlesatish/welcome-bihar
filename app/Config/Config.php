<?php
namespace App\Config;

class Config
{
    public static function init(): void
    {
        // Load environment
        $envPath = dirname(__DIR__, 2) . '/.env';
        Env::load($envPath);

        // Load Translation & Security Helpers
        require_once dirname(__DIR__) . '/Services/TranslationService.php';

        // Timezone
        date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'Asia/Kolkata');

        // Session settings
        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.cookie_httponly', '1');
            ini_set('session.use_only_cookies', '1');
            ini_set('session.cookie_samesite', 'Lax');
            ini_set('session.gc_maxlifetime', (string)($_ENV['SESSION_LIFETIME'] ?? 7200));

            // Set secure session cookie if HTTPS or behind HTTPS reverse-proxy
            $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
                || (str_starts_with($_ENV['APP_URL'] ?? '', 'https://'));

            if ($isHttps) {
                ini_set('session.cookie_secure', '1');
            }

            session_start();
        }

        // Set default language in session if not set (English default)
        if (!isset($_SESSION['lang'])) {
            $_SESSION['lang'] = $_ENV['APP_DEFAULT_LANG'] ?? 'en';
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_ENV[$key] ?? $default;
    }

    public static function baseUrl(): string
    {
        $appUrl = $_ENV['APP_URL'] ?? '';
        if (!empty($appUrl)) {
            return rtrim($appUrl, '/');
        }

        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');
        $protocol = $isHttps ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8080';
        return $protocol . $host;
    }
}
