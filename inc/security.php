<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verify_csrf_or_die(): void
{
    $token = $_POST['csrf_token'] ?? '';
    $sessionToken = $_SESSION['csrf_token'] ?? '';

    if (!is_string($token) || !is_string($sessionToken) || !hash_equals($sessionToken, $token)) {
        http_response_code(400);
        exit('Ungültiger CSRF-Token.');
    }
}

function post_string(string $key, int $maxLength = 255): string
{
    $value = trim((string)($_POST[$key] ?? ''));
    if ($maxLength > 0) {
        $value = mb_substr($value, 0, $maxLength);
    }

    return $value;
}

function get_string(string $key, int $maxLength = 255): string
{
    $value = trim((string)($_GET[$key] ?? ''));
    if ($maxLength > 0) {
        $value = mb_substr($value, 0, $maxLength);
    }

    return $value;
}

function valid_date_or_null(string $value): ?string
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return null;
    }

    $parts = explode('-', $value);
    if (count($parts) !== 3) {
        return null;
    }

    $year = (int)$parts[0];
    $month = (int)$parts[1];
    $day = (int)$parts[2];

    if (!checkdate($month, $day, $year)) {
        return null;
    }

    return $value;
}

function build_url(string $path, array $params = []): string
{
    $config = require __DIR__ . '/config.php';
    $base = rtrim((string)$config['base_url'], '/');
    $url = $base . '/' . ltrim($path, '/');

    if ($params !== []) {
        $url .= '?' . http_build_query($params);
    }

    return $url;
}

function redirect_to(string $path, array $params = []): void
{
    header('Location: ' . build_url($path, $params));
    exit;
}
