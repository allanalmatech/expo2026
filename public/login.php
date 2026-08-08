<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_guest();

$pageTitle = 'Login';
$bodyClass = 'auth-page';
$message = '';
$adminPhone = setting('contact_phone', '');
$adminEmail = setting('contact_email', '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $message = 'Security token expired. Please refresh and try again.';
    } else {
        $identifier = trim((string) ($_POST['identifier'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if ($identifier === '' || $password === '') {
            $message = 'Please enter your email or phone number and password.';
        } else {
            try {
                $user = find_user_by_identifier(db(), $identifier);
                $passwordOk = false;
                if ($user) {
                    $passwordOk = password_verify($password, (string) $user['password_hash']);
                    $normalizedPasswordPhone = normalize_phone($password);
                    if (!$passwordOk && $normalizedPasswordPhone) {
                        $passwordOk = password_verify($normalizedPasswordPhone, (string) $user['password_hash']);
                    }
                }
                if (!$user || !$passwordOk) {
                    $message = 'Invalid login details. Please check your email or phone number and password.';
                } elseif (($user['account_status'] ?? 'active') !== 'active') {
                    $message = (($user['account_status'] ?? '') === 'pending_approval')
                        ? 'Your account is pending approval because no paid amount is confirmed yet. Contact admin after payment is made.'
                        : 'Your account is not active. Please contact support.';
                } else {
                    login_user($user);
                    set_flash('success', 'Welcome back, ' . ($user['full_name'] ?? 'there') . '.');
                    redirect($user['role'] === 'admin' ? 'admin/dashboard.php' : 'applicant/dashboard.php');
                }
            } catch (Throwable $exception) {
                error_log('Login failed: ' . $exception->getMessage());
                $message = 'Login is temporarily unavailable. Please try again later.';
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/public-nav.php';
?>
<main class="auth-wrap">
    <section class="auth-card">
        <h1>Portal Login</h1>
        <p class="lead">Use your payment-register phone number and portal password.</p>

        <?php if ($message): ?>
            <div class="notice error"><?php echo h($message); ?></div>
        <?php endif; ?>

        <form method="post" class="form-grid" novalidate>
            <?php echo csrf_field(); ?>
            <div class="field">
                <label for="identifier">Email or Phone Number</label>
                <input type="text" id="identifier" name="identifier" value="<?php echo h($_POST['identifier'] ?? ''); ?>" required>
            </div>
            <div class="field">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button class="button button-primary" type="submit">Login</button>
        </form>

        <div class="divider"></div>
        <p class="help-text">Already submitted the Google Form but do not have an account?</p>
        <a class="link-strong" href="<?php echo h(app_url('public/create-account.php')); ?>">Create Portal Account</a>
        <p class="help-text">Forgot your password?</p>
        <a class="link-strong" href="<?php echo h(app_url('public/forgot-password.php')); ?>">Recover by email</a>
        <p class="help-text">If recovery fails, contact admin<?php echo $adminPhone !== '' ? ' on ' . h($adminPhone) : ''; ?><?php echo $adminEmail !== '' ? ' or ' . h($adminEmail) : ''; ?>.</p>
    </section>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
