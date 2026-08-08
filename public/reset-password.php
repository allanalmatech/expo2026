<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_guest();
ensure_vendor_access_schema();

$pageTitle = 'Reset Password';
$bodyClass = 'auth-page';
$token = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
$message = '';
$messageType = 'error';
$validToken = false;

if ($token !== '') {
    $lookup = db()->prepare('SELECT prt.*, u.full_name FROM password_reset_tokens prt INNER JOIN users u ON u.id = prt.user_id WHERE prt.token_hash = ? AND prt.used_at IS NULL AND prt.expires_at > NOW() LIMIT 1');
    $lookup->execute([hash('sha256', $token)]);
    $reset = $lookup->fetch() ?: null;
    $validToken = (bool) $reset;
} else {
    $reset = null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $message = 'Security token expired. Please refresh and try again.';
    } elseif (!$validToken || !$reset) {
        $message = 'This reset link is invalid or expired.';
    } else {
        $password = (string) ($_POST['password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');
        if (strlen($password) < 8) {
            $message = 'Your new password must be at least 8 characters long.';
        } elseif ($password !== $confirmPassword) {
            $message = 'The password confirmation does not match.';
        } else {
            $pdo = db();
            $pdo->beginTransaction();
            try {
                $pdo->prepare('UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?')
                    ->execute([password_hash($password, PASSWORD_DEFAULT), (int) $reset['user_id']]);
                $pdo->prepare('UPDATE password_reset_tokens SET used_at = NOW() WHERE id = ?')->execute([(int) $reset['id']]);
                $pdo->commit();
                set_flash('success', 'Password reset successful. Please log in.');
                redirect('public/login.php');
            } catch (Throwable $exception) {
                $pdo->rollBack();
                error_log('Password reset failed: ' . $exception->getMessage());
                $message = 'Password could not be reset. Please try again.';
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/public-nav.php';
?>
<main class="auth-wrap">
    <section class="auth-card">
        <h1>Reset Password</h1>
        <?php if ($message): ?><div class="notice <?php echo h($messageType); ?>"><?php echo h($message); ?></div><?php endif; ?>
        <?php if ($validToken): ?>
            <p class="lead">Set a new password for <?php echo h($reset['full_name'] ?? 'your account'); ?>.</p>
            <form method="post" class="form-grid" novalidate>
                <?php echo csrf_field(); ?>
                <input type="hidden" name="token" value="<?php echo h($token); ?>">
                <div class="field"><label for="password">New Password</label><input type="password" id="password" name="password" minlength="8" required></div>
                <div class="field"><label for="confirm_password">Confirm New Password</label><input type="password" id="confirm_password" name="confirm_password" minlength="8" required></div>
                <button class="button button-primary" type="submit">Reset Password</button>
            </form>
        <?php else: ?>
            <div class="empty-state"><h2>Invalid or Expired Link</h2><p>Request a new recovery link or contact admin for help.</p></div>
            <a class="button button-primary" href="<?php echo h(app_url('public/forgot-password.php')); ?>">Request New Link</a>
        <?php endif; ?>
    </section>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
