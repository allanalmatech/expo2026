<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

$pageTitle = 'Create First Admin';
$adminExists = count_rows('SELECT COUNT(*) FROM users WHERE role = "admin"') > 0;
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$adminExists) {
    require_csrf();
    $fullName = trim((string) ($_POST['full_name'] ?? ''));
    $email = normalize_email($_POST['email'] ?? null);
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    if ($fullName === '' || !$email || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 10) {
        $message = 'Provide a name, valid email, and password of at least 10 characters.';
    } else {
        db()->prepare('INSERT INTO users (full_name, email, phone, normalized_phone, password_hash, role, is_verified, account_status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, "admin", 1, "active", NOW(), NOW())')
            ->execute([$fullName, $email, $phone ?: null, normalize_phone($phone), password_hash($password, PASSWORD_DEFAULT)]);
        set_flash('success', 'Admin account created. Please log in.');
        redirect('public/login.php');
    }
}

require_once __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/public-nav.php';
?>
<main class="auth-wrap">
    <section class="auth-card">
        <h1>Create First Admin</h1>
        <?php if ($adminExists): ?>
            <div class="notice error">An admin account already exists. This setup page is locked.</div>
            <a class="button button-primary" href="<?php echo h(app_url('public/login.php')); ?>">Go to Login</a>
        <?php else: ?>
            <?php if ($message): ?><div class="notice error"><?php echo h($message); ?></div><?php endif; ?>
            <form method="post" class="form-grid">
                <?php echo csrf_field(); ?>
                <div class="field"><label>Full Name</label><input name="full_name" required></div>
                <div class="field"><label>Email</label><input type="email" name="email" required></div>
                <div class="field"><label>Phone</label><input name="phone"></div>
                <div class="field"><label>Password</label><input type="password" name="password" minlength="10" required></div>
                <button class="button button-primary" type="submit">Create Admin Account</button>
            </form>
        <?php endif; ?>
    </section>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
