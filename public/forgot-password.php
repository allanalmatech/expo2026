<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_guest();
ensure_vendor_access_schema();

$pageTitle = 'Recover Password';
$bodyClass = 'auth-page';
$message = '';
$messageType = 'success';
$adminPhone = setting('contact_phone', '');
$adminEmail = setting('contact_email', '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $message = 'Security token expired. Please refresh and try again.';
        $messageType = 'error';
    } else {
        $identifier = trim((string) ($_POST['identifier'] ?? ''));
        if ($identifier === '') {
            $message = 'Enter your phone number or email address.';
            $messageType = 'error';
        } else {
            try {
                $pdo = db();
                $user = find_user_by_identifier($pdo, $identifier);
                $email = normalize_email($user['email'] ?? null);
                if ($user && !$email && !empty($user['form_response_id'])) {
                    $lookup = $pdo->prepare('SELECT email FROM form_responses WHERE id = ? LIMIT 1');
                    $lookup->execute([(int) $user['form_response_id']]);
                    $email = normalize_email($lookup->fetchColumn() ?: null);
                }

                if ($user && $email) {
                    $token = bin2hex(random_bytes(32));
                    $pdo->prepare('INSERT INTO password_reset_tokens (user_id, token_hash, expires_at, created_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR), NOW())')
                        ->execute([(int) $user['id'], hash('sha256', $token)]);
                    $resetUrl = absolute_app_url('public/reset-password.php?token=' . rawurlencode($token));
                    $subject = APP_EVENT_NAME . ' password recovery';
                    $body = "Use this link to reset your portal password. The link expires in 1 hour:\n\n" . $resetUrl;
                    @mail($email, $subject, $body, 'From: ' . (setting('contact_email', 'no-reply@example.com') ?: 'no-reply@example.com'));
                }

                $message = 'If an account with a registration email exists, a reset link has been sent. If you do not receive it, contact admin.';
                $messageType = 'success';
            } catch (Throwable $exception) {
                error_log('Password recovery failed: ' . $exception->getMessage());
                $message = 'Password recovery is temporarily unavailable. Contact admin for help.';
                $messageType = 'error';
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/public-nav.php';
?>
<main class="auth-wrap">
    <section class="auth-card">
        <h1>Recover Password</h1>
        <p class="lead">We send recovery links to the email address on your registration/payment sheet record.</p>
        <?php if ($message): ?><div class="notice <?php echo h($messageType); ?>"><?php echo h($message); ?></div><?php endif; ?>
        <form method="post" class="form-grid" novalidate>
            <?php echo csrf_field(); ?>
            <div class="field">
                <label for="identifier">Phone or Email</label>
                <input id="identifier" name="identifier" value="<?php echo h($_POST['identifier'] ?? ''); ?>" required>
            </div>
            <button class="button button-primary" type="submit">Send Recovery Link</button>
        </form>
        <div class="divider"></div>
        <p class="help-text">No email access? Contact admin<?php echo $adminPhone !== '' ? ' on ' . h($adminPhone) : ''; ?><?php echo $adminEmail !== '' ? ' or ' . h($adminEmail) : ''; ?>.</p>
        <a class="link-strong" href="<?php echo h(app_url('public/login.php')); ?>">Back to Login</a>
    </section>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
