<?php
/**
 * Global Helper Functions for Welcome Bihar
 */

if (!function_exists('__')) {
    function __(string $key, ?string $fallback = null): string
    {
        return \App\Services\TranslationService::trans($key, $fallback);
    }
}

if (!function_exists('e')) {
    function e(?string $string): string
    {
        return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8');
    }
}

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
