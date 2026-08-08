<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_guest();
ensure_vendor_access_schema();

$pageTitle = 'Create Portal Account';
$bodyClass = 'auth-page';
$message = '';
$messageType = '';
$adminPhone = setting('contact_phone', '');
$adminEmail = setting('contact_email', '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $message = 'Security token expired. Please refresh and try again.';
        $messageType = 'error';
    } else {
        $identifier = trim((string) ($_POST['identifier'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

        if ($identifier === '' || $password === '' || $confirmPassword === '') {
            $message = 'Please provide your email or phone number and password.';
            $messageType = 'error';
        } elseif (strlen($password) < 8) {
            $message = 'Your password must be at least 8 characters long.';
            $messageType = 'error';
        } elseif ($password !== $confirmPassword) {
            $message = 'The password confirmation does not match.';
            $messageType = 'error';
        } else {
            try {
                $pdo = db();
                $lookup = find_form_response_for_identifier($pdo, $identifier);

                if ($lookup['status'] === 'not_found' || $lookup['status'] === 'invalid') {
                    $message = 'We could not find your stall application. Please first fill in the stall registration Google Form or use the same email address or phone number you used during submission.';
                    $messageType = 'error';
                } elseif ($lookup['status'] === 'multiple') {
                    $message = 'More than one application uses that phone number. Please contact the organizing committee for help linking your account.';
                    $messageType = 'error';
                } else {
                    $response = $lookup['response'];
                    $email = normalize_email($response['email'] ?? null);
                    $phone = trim((string) ($response['phone'] ?? ''));
                    $normalizedPhone = normalize_phone($response['normalized_phone'] ?? $response['phone'] ?? '');

                    $duplicateSql = 'SELECT id FROM users WHERE form_response_id = ?';
                    $duplicateParams = [(int) $response['id']];
                    if ($email) {
                        $duplicateSql .= ' OR LOWER(email) = ?';
                        $duplicateParams[] = $email;
                    }
                    if ($normalizedPhone) {
                        $duplicateSql .= ' OR normalized_phone = ?';
                        $duplicateParams[] = $normalizedPhone;
                    }
                    $duplicateSql .= ' LIMIT 1';

                    $duplicate = $pdo->prepare($duplicateSql);
                    $duplicate->execute($duplicateParams);
                    if ($duplicate->fetch()) {
                        $message = 'An account already exists for this application. Please log in instead.';
                        $messageType = 'error';
                    } else {
                        $baseline = form_response_payment_baseline($response);
                        $accountStatus = $baseline['paid'] > 0 ? 'active' : 'pending_approval';
                        $pdo->beginTransaction();

                        $insertUser = $pdo->prepare(
                            'INSERT INTO users (form_response_id, full_name, email, phone, normalized_phone, password_hash, role, is_verified, account_status, created_at, updated_at)
                             VALUES (?, ?, ?, ?, ?, ?, "applicant", 1, ?, NOW(), NOW())'
                        );
                        $insertUser->execute([
                            (int) $response['id'],
                            trim((string) ($response['full_name'] ?? 'Applicant')) ?: 'Applicant',
                            $email,
                            $phone !== '' ? $phone : null,
                            $normalizedPhone,
                            password_hash($password, PASSWORD_DEFAULT),
                            $accountStatus,
                        ]);
                        $userId = (int) $pdo->lastInsertId();

                        $complianceStatus = trim((string) ($response['rules_agreement'] ?? '')) !== '' ? 'Pending Review' : 'Not Signed';

                        $insertApplication = $pdo->prepare(
                            'INSERT INTO applications (user_id, form_response_id, application_status, payment_status, compliance_status, created_at, updated_at)
                             VALUES (?, ?, ?, "Not Paid", ?, NOW(), NOW())'
                        );
                        $insertApplication->execute([$userId, (int) $response['id'], $baseline['paid'] > 0 ? 'Approved' : 'Pending Review', $complianceStatus]);
                        sync_form_response_linked_portal_records($pdo, (int) $response['id']);

                        $pdo->commit();
                        if ($accountStatus === 'active') {
                            set_flash('success', 'Your portal account has been created. Please log in with your phone number and password.');
                            redirect('public/login.php');
                        }
                        $message = 'Your account was created but is pending approval because no paid amount is recorded for your phone number yet. Contact the admin after payment is confirmed.';
                        $messageType = 'warning';
                    }
                }
            } catch (Throwable $exception) {
                if (isset($pdo) && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('Account creation failed: ' . $exception->getMessage());
                $message = 'We could not create your account right now. Please try again or contact support.';
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
        <h1>Create Portal Account</h1>
        <p class="lead">Use the phone number on the paid vendor register. Vendors with any recorded payment can log in immediately.</p>

        <?php if ($message): ?>
            <div class="notice <?php echo h($messageType); ?>"><?php echo h($message); ?></div>
        <?php endif; ?>

        <form method="post" class="form-grid" novalidate>
            <?php echo csrf_field(); ?>
            <div class="field">
                <label for="identifier">Phone Number</label>
                <input type="text" id="identifier" name="identifier" placeholder="e.g. 0772 000000" value="<?php echo h($_POST['identifier'] ?? ''); ?>" required>
                <small>Use the same phone number on the payment register. Uganda numbers are normalized to +256 before matching.</small>
            </div>
            <div class="field">
                <label for="password">Preferred Password</label>
                <input type="password" id="password" name="password" placeholder="Create a strong password" required minlength="8">
            </div>
            <div class="field">
                <label for="confirm_password">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" placeholder="Repeat your password" required minlength="8">
            </div>
            <button class="button button-primary" type="submit">Verify and Create Account</button>
        </form>

        <div class="divider"></div>
        <p class="help-text">If your phone number cannot be matched or your account stays pending, contact admin<?php echo $adminPhone !== '' ? ' on ' . h($adminPhone) : ''; ?><?php echo $adminEmail !== '' ? ' or ' . h($adminEmail) : ''; ?>.</p>
        <p class="help-text">Haven't applied for a stall yet?</p>
        <a class="link-strong" href="<?php echo h(setting('google_form_url', '#')); ?>" target="_blank" rel="noopener">Complete Stall Application Form</a>
    </section>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
