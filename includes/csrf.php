<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

function csrf_token(string $key = 'default'): string
{
    if (empty($_SESSION['_csrf'][$key])) {
        $_SESSION['_csrf'][$key] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'][$key];
}

function csrf_field(string $key = 'default'): string
{
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token($key)) . '">';
}

function verify_csrf(string $key = 'default'): bool
{
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    return is_string($token) && isset($_SESSION['_csrf'][$key]) && hash_equals($_SESSION['_csrf'][$key], $token);
}

function require_csrf(string $key = 'default'): void
{
    if (!verify_csrf($key)) {
        if (str_starts_with($_SERVER['REQUEST_URI'] ?? '', app_base_path() . '/ajax/')) {
            json_response(['ok' => false, 'message' => 'Security token expired. Please refresh and try again.'], 419);
        }
        set_flash('error', 'Security token expired. Please refresh and try again.');
        redirect('public/login.php');
    }
}
