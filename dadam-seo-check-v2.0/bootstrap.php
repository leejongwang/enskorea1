<?php

declare(strict_types=1);

$config = require __DIR__ . '/config.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'httponly' => true,
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'samesite' => 'Lax',
        'path' => '/',
    ]);
    session_start();
}

require_once __DIR__ . '/ReportStore.php';
require_once __DIR__ . '/RateLimiter.php';

function appConfig(?string $key = null, mixed $default = null): mixed
{
    global $config;

    if ($key === null) {
        return $config;
    }

    $value = $config;
    foreach (explode('.', $key) as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }
        $value = $value[$segment];
    }

    return $value;
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function statusLabel(string $status): string
{
    return match ($status) {
        'good' => '통과',
        'critical' => '심각',
        'warning' => '개선',
        default => '참고',
    };
}

function csrfToken(): string
{
    if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf(string $token): bool
{
    return isset($_SESSION['csrf_token'])
        && is_string($_SESSION['csrf_token'])
        && $token !== ''
        && hash_equals($_SESSION['csrf_token'], $token);
}

function appBaseUrl(): string
{
    $configured = trim((string) appConfig('base_url', ''));
    if ($configured !== '') {
        return rtrim($configured, '/');
    }

    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $scheme = $https ? 'https' : 'http';
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
    $directory = rtrim(dirname($scriptName), '/.');

    return $scheme . '://' . $host . ($directory !== '' ? $directory : '');
}

function appUrl(string $path = ''): string
{
    return appBaseUrl() . '/' . ltrim($path, '/');
}

function redirectTo(string $url): never
{
    header('Location: ' . $url, true, 303);
    exit;
}

function reportStore(): ReportStore
{
    static $store;
    if (!$store instanceof ReportStore) {
        $store = new ReportStore((string) appConfig('storage_path'));
    }
    return $store;
}

function clientIp(): string
{
    return (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
}

function formatWon(int $amount): string
{
    return number_format($amount) . '원';
}

function businessFooter(): string
{
    $business = (array) appConfig('business', []);
    $parts = array_filter([
        (string) ($business['company'] ?? ''),
        '대표 ' . (string) ($business['representative'] ?? ''),
        '사업자번호 ' . (string) ($business['business_number'] ?? ''),
        (string) ($business['phone'] ?? ''),
    ], static fn (string $value): bool => trim($value) !== '');

    return implode(' · ', $parts);
}
