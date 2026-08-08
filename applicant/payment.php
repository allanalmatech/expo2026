<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
$user = require_login('applicant');
$active = 'payment';
$pageTitle = 'Payment';
$pdo = db();
ensure_payment_upload_schema();

$statement = $pdo->prepare('SELECT a.*, fr.proof_of_payment_url, fr.preferred_payment_method FROM applications a LEFT JOIN form_responses fr ON fr.id = a.form_response_id WHERE a.user_id = ? LIMIT 1');
$statement->execute([(int) $user['id']]);
$application = $statement->fetch();
$pricing = $application ? calculate_application_pricing($pdo, (int) $application['id']) : null;
$paymentTotals = $application ? payment_upload_totals($pdo, (int) $application['id'], $pricing) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    if (!$application) {
        set_flash('error', 'No application record was found for your account.');
        redirect('applicant/payment.php');
    }

    $paymentAmount = (float) str_replace(',', '', (string) ($_POST['payment_amount'] ?? '0'));
    $paymentDescription = trim((string) ($_POST['payment_description'] ?? ''));
    if ($paymentAmount <= 0) {
        set_flash('error', 'Enter the amount paid for this proof.');
        redirect('applicant/payment.php');
    }
    if ($paymentDescription === '') {
        set_flash('error', 'Describe what this payment is for, for example booking payment or balance payment.');
        redirect('applicant/payment.php');
    }

    $error = '';
    if (!isset($_FILES['proof']) || !validate_uploaded_file($_FILES['proof'], allowed_upload_extensions(), $error)) {
        set_flash('error', $error ?: 'Please upload a valid payment proof.');
        redirect('applicant/payment.php');
    }

    $dir = ensure_upload_dir('payments');
    $name = secure_upload_name((string) $_FILES['proof']['name']);
    $target = $dir . '/' . $name;
    if (!move_uploaded_file((string) $_FILES['proof']['tmp_name'], $target)) {
        set_flash('error', 'The payment proof could not be saved.');
        redirect('applicant/payment.php');
    }

    $relativePath = 'uploads/payments/' . $name;
    $insert = $pdo->prepare(
        'INSERT INTO payment_uploads (user_id, application_id, file_path, original_filename, file_type, file_size, payment_amount, payment_description, verification_status, uploaded_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, "Pending", NOW())'
    );
    $insert->execute([
        (int) $user['id'],
        (int) $application['id'],
        $relativePath,
        $_FILES['proof']['name'],
        strtolower(pathinfo((string) $_FILES['proof']['name'], PATHINFO_EXTENSION)),
        (int) $_FILES['proof']['size'],
        $paymentAmount,
        $paymentDescription,
    ]);

    refresh_application_payment_status_from_uploads($pdo, (int) $application['id']);

    set_flash('success', 'Payment proof uploaded. The committee can now verify it.');
    redirect('applicant/payment.php');
}

$uploads = [];
$latestRejectedComment = '';
if ($application) {
    $uploadsStatement = $pdo->prepare('SELECT * FROM payment_uploads WHERE application_id = ? ORDER BY uploaded_at DESC');
    $uploadsStatement->execute([(int) $application['id']]);
    $uploads = $uploadsStatement->fetchAll();
    foreach ($uploads as $upload) {
        if (($upload['verification_status'] ?? '') === 'Rejected') {
            $latestRejectedComment = trim((string) ($upload['admin_comment'] ?? ''));
            break;
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>
<div class="app-shell">
    <?php require __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="app-main">
        <div class="page-header">
            <div>
                <h1>Payment Proof</h1>
                <p>Upload proof of payment as PDF, JPG, JPEG, or PNG. Maximum file size is 5 MB.</p>
            </div>
            <?php echo badge($application['payment_status'] ?? 'Not Paid'); ?>
        </div>

        <div class="dashboard-grid">
            <section class="panel">
                <h2>Upload Payment Proof</h2>
                <?php if (($application['payment_status'] ?? '') === 'Payment Rejected'): ?>
                    <div class="notice error">
                        Your payment proof was flagged and a replacement upload is required.
                        <?php if ($latestRejectedComment !== ''): ?><br>Admin note: <?php echo h($latestRejectedComment); ?><?php endif; ?>
                    </div>
                <?php endif; ?>
                <form method="post" enctype="multipart/form-data" class="form-grid" data-confirm="Upload this payment proof for committee verification?">
                    <?php echo csrf_field(); ?>
                    <div class="field">
                        <label for="payment_amount">Amount Paid (UGX)</label>
                        <?php $suggestedPaymentAmount = (int) round((float) ($paymentTotals['balance'] ?? 0)); ?>
                        <input type="number" id="payment_amount" name="payment_amount" min="1" step="1" value="<?php echo $suggestedPaymentAmount > 0 ? h((string) $suggestedPaymentAmount) : ''; ?>" required>
                        <small>Enter only the amount covered by this proof. Multiple uploads are accumulated after verification.</small>
                    </div>
                    <div class="field">
                        <label for="payment_description">Payment Description</label>
                        <input id="payment_description" name="payment_description" maxlength="255" placeholder="Booking payment, balance payment, extra stall payment" required>
                    </div>
                    <div class="field">
                        <label for="proof">Payment Proof File</label>
                        <input type="file" id="proof" name="proof" accept=".pdf,.jpg,.jpeg,.png" required>
                    </div>
                    <button class="button button-primary" type="submit">Upload Payment Proof</button>
                </form>
            </section>

            <aside class="panel dark-card">
                <h2>Amount Due</h2>
                <?php if ($pricing): ?>
                    <div class="summary-grid">
                        <div class="summary-item"><span>Pricing Rule</span><strong><?php echo h($pricing['rule']['rule_name'] ?? 'No rule'); ?></strong></div>
                        <div class="summary-item"><span>Per Stall</span><strong><?php echo h(ugx_money((float) $pricing['price_per_stall'])); ?></strong></div>
                        <div class="summary-item"><span>Discount</span><strong><?php echo h(ugx_money((float) $pricing['discount_amount'])); ?></strong></div>
                        <div class="summary-item"><span>Total Due</span><strong><?php echo h(ugx_money((float) $pricing['total'])); ?></strong></div>
                        <div class="summary-item"><span>Verified Paid</span><strong><?php echo h(ugx_money((float) ($paymentTotals['verified'] ?? 0))); ?></strong></div>
                        <div class="summary-item"><span>Pending Review</span><strong><?php echo h(ugx_money((float) ($paymentTotals['pending'] ?? 0))); ?></strong></div>
                        <div class="summary-item"><span>Balance</span><strong><?php echo h(ugx_money((float) ($paymentTotals['balance'] ?? 0))); ?></strong></div>
                    </div>
                    <?php if ($pricing['discount']): ?><p class="help-text">Special discount: <?php echo h($pricing['discount']['reason'] ?? 'Approved by committee'); ?></p><?php endif; ?>
                    <div class="divider"></div>
                <?php endif; ?>
                <h2>Imported Payment</h2>
                <p><strong>Preferred method:</strong> <?php echo h($application['preferred_payment_method'] ?? 'Not provided'); ?></p>
                <?php if (!empty($application['proof_of_payment_url'])): ?>
                    <a class="button button-ghost" href="<?php echo h($application['proof_of_payment_url']); ?>" target="_blank" rel="noopener">View Google Form Proof</a>
                    <?php if (($application['payment_status'] ?? '') === 'Payment Rejected'): ?><p class="help-text">The committee requires a new upload through this portal.</p><?php endif; ?>
                <?php else: ?>
                    <p class="help-text">No proof was imported from the Google Form.</p>
                <?php endif; ?>
            </aside>
        </div>

        <section class="panel" style="margin-top: 22px;">
            <h2>Upload History</h2>
            <div class="table-scroll">
                <table>
                    <thead><tr><th>File</th><th>Amount</th><th>Description</th><th>Status</th><th>Uploaded</th><th>Admin Comment</th></tr></thead>
                    <tbody>
                    <?php foreach ($uploads as $upload): ?>
                        <tr>
                            <td data-label="File"><a class="link-strong" href="<?php echo h(app_url($upload['file_path'])); ?>" target="_blank" rel="noopener"><?php echo h($upload['original_filename']); ?></a></td>
                            <td data-label="Amount"><?php echo h(ugx_money((float) ($upload['payment_amount'] ?? 0))); ?></td>
                            <td data-label="Description"><?php echo h($upload['payment_description'] ?? 'Not provided'); ?></td>
                            <td data-label="Status"><?php echo badge(payment_verification_label((string) $upload['verification_status'], $upload['admin_comment'] ?? null)); ?></td>
                            <td data-label="Uploaded"><?php echo h(format_date($upload['uploaded_at'])); ?></td>
                            <td data-label="Admin Comment"><?php echo h($upload['admin_comment'] ?? 'No comment'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$uploads): ?><tr><td colspan="6" class="empty-state">No payment uploads yet.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
