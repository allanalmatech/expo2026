<?php
declare(strict_types=1);

require_once __DIR__ . '/csrf.php';

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    try {
        $statement = db()->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $statement->execute([(int) $_SESSION['user_id']]);
        $user = $statement->fetch();
        if (!$user) {
            logout_user();
            return null;
        }
        $cached = $user;
        return $cached;
    } catch (Throwable $exception) {
        error_log('Current user lookup failed: ' . $exception->getMessage());
        return null;
    }
}

function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['full_name'] = $user['full_name'];
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function require_login(?string $role = null): array
{
    $user = current_user();
    if (!$user) {
        set_flash('error', 'Please log in to continue.');
        redirect('public/login.php');
    }

    if ($role !== null && $user['role'] !== $role) {
        set_flash('error', 'You do not have permission to access that page.');
        redirect($user['role'] === 'admin' ? 'admin/dashboard.php' : 'applicant/dashboard.php');
    }

    if (($user['account_status'] ?? 'active') !== 'active') {
        logout_user();
        set_flash('error', 'Your account is not active. Please contact support.');
        redirect('public/login.php');
    }

    return $user;
}

function require_guest(): void
{
    $user = current_user();
    if (!$user) {
        return;
    }
    redirect($user['role'] === 'admin' ? 'admin/dashboard.php' : 'applicant/dashboard.php');
}
