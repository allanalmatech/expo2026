<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
$admin = require_login('admin');
$active = 'applications';
$pageTitle = 'Application Review';
$pdo = db();
ensure_payment_upload_schema();
$applicationId = (int) ($_GET['id'] ?? $_POST['application_id'] ?? 0);

if ($applicationId <= 0) {
    set_flash('error', 'Application not found.');
    redirect('admin/applications.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'record_payment') {
        $amount = parse_money_amount((string) ($_POST['paid_amount'] ?? ''));
        $description = trim((string) ($_POST['payment_description'] ?? 'Balance payment'));
        $method = trim((string) ($_POST['payment_method'] ?? ''));
        $transactionId = trim((string) ($_POST['transaction_id'] ?? ''));
        $paidAt = trim((string) ($_POST['paid_at'] ?? ''));

        if ($amount <= 0) {
            set_flash('error', 'Enter the payment amount received.');
            redirect('admin/application-view.php?id=' . $applicationId);
        }

        try {
            $receipt = create_manual_payment_receipt($pdo, $applicationId, $amount, $description, $method, $transactionId, $paidAt !== '' ? $paidAt : null, (int) $admin['id'], $_FILES['payment_proof'] ?? null);
            set_flash('success', 'Payment recorded. Receipt ' . $receipt['receipt_reference'] . ' is ready to print.');
            redirect('receipt.php?ref=' . rawurlencode((string) $receipt['receipt_reference']));
        } catch (Throwable $exception) {
            error_log('Manual payment receipt failed: ' . $exception->getMessage());
            set_flash('error', $exception instanceof RuntimeException ? $exception->getMessage() : 'Payment could not be recorded.');
            redirect('admin/application-view.php?id=' . $applicationId);
        }
    }

    $paymentVerificationAction = (string) ($_POST['payment_verification_action'] ?? '');
    if (in_array($paymentVerificationAction, ['verify', 'reject', 'flag_reupload'], true)) {
        $source = (string) ($_POST['source'] ?? 'upload');
        $uploadId = (int) ($_POST['upload_id'] ?? 0);
        $comment = trim((string) ($_POST['admin_comment'] ?? ''));
        $verificationStatus = $paymentVerificationAction === 'verify' ? 'Verified' : 'Rejected';
        $paymentStatus = $paymentVerificationAction === 'verify' ? 'Payment Received' : 'Payment Rejected';
        if ($paymentVerificationAction === 'flag_reupload') {
            $comment = payment_reupload_comment($comment);
        }

        try {
            if ($source === 'sheet') {
                if ($paymentVerificationAction === 'verify') {
                    refresh_application_payment_status_from_uploads($pdo, $applicationId);
                } else {
                    $pdo->prepare('UPDATE applications SET payment_status = ?, updated_at = NOW() WHERE id = ?')->execute([$paymentStatus, $applicationId]);
                }
            } else {
                $upload = $pdo->prepare('SELECT * FROM payment_uploads WHERE id = ? AND application_id = ? LIMIT 1');
                $upload->execute([$uploadId, $applicationId]);
                if (!$upload->fetch()) {
                    throw new RuntimeException('Payment upload not found.');
                }
                $pdo->prepare('UPDATE payment_uploads SET verification_status = ?, verified_by = ?, verified_at = NOW(), admin_comment = ? WHERE id = ?')
                    ->execute([$verificationStatus, (int) $admin['id'], $comment, $uploadId]);
                if ($verificationStatus === 'Verified') {
                    sync_upload_payment_receipt($pdo, $uploadId);
                }
                refresh_application_payment_status_from_uploads($pdo, $applicationId);
            }
            set_flash('success', $paymentVerificationAction === 'flag_reupload' ? 'Payment proof flagged for re-upload.' : 'Payment proof ' . strtolower($verificationStatus) . '.');
        } catch (Throwable $exception) {
            error_log('Application payment verification failed: ' . $exception->getMessage());
            set_flash('error', $exception instanceof RuntimeException ? $exception->getMessage() : 'Payment proof could not be updated.');
        }
        redirect('admin/application-view.php?id=' . $applicationId);
    }

    try {
        $pdo->beginTransaction();

        $currentStatement = $pdo->prepare('SELECT a.*, u.id AS applicant_user_id FROM applications a INNER JOIN users u ON u.id = a.user_id WHERE a.id = ? LIMIT 1');
        $currentStatement->execute([$applicationId]);
        $current = $currentStatement->fetch();
        if (!$current) {
            throw new RuntimeException('Application missing.');
        }

        $formResponseId = (int) ($_POST['form_response_id'] ?? $current['form_response_id']);
        if ($formResponseId > 0) {
            $responseCheck = $pdo->prepare('SELECT id FROM form_responses WHERE id = ? LIMIT 1');
            $responseCheck->execute([$formResponseId]);
            if (!$responseCheck->fetch()) {
                throw new RuntimeException('Selected form response does not exist.');
            }
            $linkedCheck = $pdo->prepare('SELECT id FROM users WHERE form_response_id = ? AND id <> ? LIMIT 1');
            $linkedCheck->execute([$formResponseId, (int) $current['applicant_user_id']]);
            if ($linkedCheck->fetch()) {
                throw new RuntimeException('Selected form response is already linked to another account.');
            }
        }

        $fullName = trim((string) ($_POST['full_name'] ?? ''));
        $email = normalize_email($_POST['email'] ?? null);
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $normalizedPhone = normalize_phone($phone);

        $accountStatus = in_array((string) ($_POST['account_status'] ?? 'active'), ['active', 'pending_approval', 'suspended'], true) ? (string) $_POST['account_status'] : 'active';
        $pdo->prepare('UPDATE users SET form_response_id = ?, full_name = ?, email = ?, phone = ?, normalized_phone = ?, account_status = ?, updated_at = NOW() WHERE id = ?')
            ->execute([$formResponseId ?: null, $fullName ?: 'Applicant', $email, $phone ?: null, $normalizedPhone, $accountStatus, (int) $current['applicant_user_id']]);

        $pdo->prepare('UPDATE form_responses SET full_name = ?, email = ?, phone = ?, normalized_phone = ?, business_name = ?, business_nature = ?, business_description = ?, stall_type = ?, updated_at = NOW() WHERE id = ?')
            ->execute([
                $fullName ?: 'Applicant',
                $email,
                $phone ?: null,
                $normalizedPhone,
                trim((string) ($_POST['business_name'] ?? '')),
                trim((string) ($_POST['business_nature'] ?? '')),
                trim((string) ($_POST['business_description'] ?? '')),
                trim((string) ($_POST['stall_type'] ?? '')),
                $formResponseId,
            ]);

        $assignedStalls = refresh_application_stall_assignment($pdo, (int) $current['applicant_user_id']);
        $stallNumbers = [];
        $stallLocations = [];
        foreach ($assignedStalls as $assignedStall) {
            $stallNumbers[] = (string) $assignedStall['stall_number'];
            $location = layout_stall_location_label($assignedStall);
            if (!in_array($location, $stallLocations, true)) {
                $stallLocations[] = $location;
            }
        }
        $stallNumber = $stallNumbers ? implode(', ', $stallNumbers) : null;
        $stallLocation = $stallLocations ? implode('; ', $stallLocations) : null;

        $pdo->prepare(
            'UPDATE applications SET form_response_id = ?, application_status = ?, payment_status = ?, compliance_status = ?, assigned_stall_number = ?, assigned_stall_location = ?, admin_notes = ?, updated_at = NOW() WHERE id = ?'
        )->execute([
            $formResponseId ?: null,
            $_POST['application_status'] ?? 'Pending Review',
            $_POST['payment_status'] ?? 'Not Paid',
            $_POST['compliance_status'] ?? 'Not Signed',
            $stallNumber,
            $stallLocation,
            trim((string) ($_POST['admin_notes'] ?? '')),
            $applicationId,
        ]);

        $pdo->commit();
        set_flash('success', 'Application updated successfully.');
        redirect('admin/application-view.php?id=' . $applicationId);
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Application update failed: ' . $exception->getMessage());
        set_flash('error', $exception instanceof RuntimeException ? $exception->getMessage() : 'Unable to update application.');
        redirect('admin/application-view.php?id=' . $applicationId);
    }
}

$statement = $pdo->prepare(
    'SELECT a.*, u.full_name, u.email, u.phone, u.normalized_phone, u.account_status, fr.*,
            a.id AS application_id, u.id AS applicant_user_id
     FROM applications a
     INNER JOIN users u ON u.id = a.user_id
     LEFT JOIN form_responses fr ON fr.id = a.form_response_id
     WHERE a.id = ? LIMIT 1'
);
$statement->execute([$applicationId]);
$application = $statement->fetch();

if (!$application) {
    set_flash('error', 'Application not found.');
    redirect('admin/applications.php');
}

if ((float) ($application['sheet_paid_amount'] ?? 0) > 0) {
    sync_sheet_payment_receipt($pdo, (int) $application['form_response_id']);
}

$uploads = $pdo->prepare('SELECT * FROM payment_uploads WHERE application_id = ? ORDER BY uploaded_at DESC');
$uploads->execute([$applicationId]);
$paymentUploads = $uploads->fetchAll();
$pricing = calculate_application_pricing($pdo, $applicationId);
$paymentTotals = payment_upload_totals($pdo, $applicationId, $pricing);
$receiptsStatement = $pdo->prepare('SELECT * FROM payment_receipts WHERE application_id = ? OR form_response_id = ? ORDER BY paid_at DESC, created_at DESC');
$receiptsStatement->execute([$applicationId, (int) ($application['form_response_id'] ?? 0)]);
$paymentReceipts = $receiptsStatement->fetchAll();

$paymentProofs = [];
if (!empty($application['proof_of_payment_url'])) {
    $paymentProofs[] = [
        'row_key' => 'sheet:' . (int) $applicationId,
        'source' => 'sheet',
        'upload_id' => 0,
        'application_id' => (int) $applicationId,
        'proof_url' => (string) $application['proof_of_payment_url'],
        'file_label' => 'Imported payment proof',
        'payment_amount' => 0.0,
        'payment_description' => 'Imported payment proof',
        'event_at' => $application['updated_at'] ?? $application['created_at'] ?? null,
        'verification_status' => match ((string) ($application['payment_status'] ?? '')) {
            'Payment Received' => 'Verified',
            'Payment Rejected' => 'Rejected',
            default => 'Pending',
        },
        'admin_comment' => ($application['payment_status'] ?? '') === 'Payment Rejected' ? payment_reupload_comment() : '',
        'can_verify' => 1,
    ];
}
foreach ($paymentUploads as $upload) {
    $paymentProofs[] = [
        'row_key' => 'upload:' . (int) $upload['id'],
        'source' => 'upload',
        'upload_id' => (int) $upload['id'],
        'application_id' => (int) $applicationId,
        'proof_url' => app_url((string) $upload['file_path']),
        'file_label' => (string) $upload['original_filename'],
        'payment_amount' => (float) ($upload['payment_amount'] ?? 0),
        'payment_description' => (string) ($upload['payment_description'] ?? ''),
        'event_at' => $upload['uploaded_at'] ?? null,
        'verification_status' => (string) $upload['verification_status'],
        'admin_comment' => (string) ($upload['admin_comment'] ?? ''),
        'can_verify' => 1,
    ];
}

$contactDigits = normalize_phone($application['phone'] ?? $application['normalized_phone'] ?? '');
$contactDisplay = 'Not available';
$telHref = '';
$whatsappHref = '';
if ($contactDigits) {
    $telHref = 'tel:+' . $contactDigits;
    $whatsappHref = 'https://wa.me/' . $contactDigits;
    if (str_starts_with($contactDigits, '256') && strlen($contactDigits) === 12) {
        $contactDisplay = '+256 ' . substr($contactDigits, 3, 3) . ' ' . substr($contactDigits, 6, 3) . ' ' . substr($contactDigits, 9);
    } else {
        $contactDisplay = '+' . $contactDigits;
    }
}

require_once __DIR__ . '/../includes/header.php';
?>
<div class="app-shell">
    <?php require __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="app-main">
        <div class="page-header">
            <div>
                <h1><?php echo h($application['full_name']); ?></h1>
                <p><?php echo h($application['business_name'] ?? 'No business name'); ?> - <?php echo h($application['email'] ?? 'No email'); ?></p>
            </div>
            <a class="button button-ghost" href="<?php echo h(app_url('admin/applications.php')); ?>">Back to Applications</a>
        </div>

        <form method="post" class="dashboard-grid">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="application_id" value="<?php echo (int) $applicationId; ?>">
            <section class="panel">
                <h2>Applicant and Business Details</h2>
                <div class="form-grid two">
                    <div class="field"><label>Form Response ID</label><input type="number" name="form_response_id" value="<?php echo (int) ($application['form_response_id'] ?? 0); ?>" min="0"><small>Use this only to manually relink an applicant to an imported response.</small></div>
                    <div class="field"><label>Full Name</label><input name="full_name" value="<?php echo h($application['full_name']); ?>" required></div>
                    <div class="field"><label>Email</label><input type="email" name="email" value="<?php echo h($application['email']); ?>"></div>
                    <div class="field"><label>Phone</label><input name="phone" value="<?php echo h($application['phone']); ?>"></div>
                    <div class="field"><label>Account Status</label><select name="account_status"><?php foreach (['active' => 'Active', 'pending_approval' => 'Pending Approval', 'suspended' => 'Suspended'] as $value => $label): ?><option value="<?php echo h($value); ?>" <?php echo ($application['account_status'] ?? 'active') === $value ? 'selected' : ''; ?>><?php echo h($label); ?></option><?php endforeach; ?></select></div>
                    <div class="field"><label>Business Name</label><input name="business_name" value="<?php echo h($application['business_name'] ?? ''); ?>"></div>
                    <div class="field"><label>Business Nature</label><input name="business_nature" value="<?php echo h($application['business_nature'] ?? ''); ?>"></div>
                    <div class="field"><label>Stall Type</label><input name="stall_type" value="<?php echo h($application['stall_type'] ?? ''); ?>"></div>
                </div>
                <div class="field" style="margin-top: 18px;"><label>Business Description</label><textarea name="business_description"><?php echo h($application['business_description'] ?? ''); ?></textarea></div>

                <h2>Review Status</h2>
                <div class="form-grid two">
                    <div class="field"><label>Application Status</label><select name="application_status"><?php foreach (['Pending Review', 'Needs Correction', 'Approved', 'Rejected'] as $option): ?><option <?php echo ($application['application_status'] === $option) ? 'selected' : ''; ?>><?php echo h($option); ?></option><?php endforeach; ?></select></div>
                    <div class="field"><label>Payment Status</label><select name="payment_status"><?php foreach (['Not Paid', 'Pending Verification', 'Payment Received', 'Payment Rejected'] as $option): ?><option <?php echo ($application['payment_status'] === $option) ? 'selected' : ''; ?>><?php echo h($option); ?></option><?php endforeach; ?></select></div>
                    <div class="field"><label>Compliance Status</label><select name="compliance_status"><?php foreach (['Not Signed', 'Signed', 'Pending Review'] as $option): ?><option <?php echo ($application['compliance_status'] === $option) ? 'selected' : ''; ?>><?php echo h($option); ?></option><?php endforeach; ?></select></div>
                    <div class="field"><label>Assigned Stall Number</label><input name="assigned_stall_number" value="<?php echo h($application['assigned_stall_number'] ?? ''); ?>" readonly><small>Use Stall Allocation to assign or release slots.</small></div>
                    <div class="field"><label>Assigned Stall Location</label><input name="assigned_stall_location" value="<?php echo h($application['assigned_stall_location'] ?? ''); ?>" readonly></div>
                </div>
                <div class="field" style="margin-top: 18px;"><label>Admin Notes</label><textarea name="admin_notes"><?php echo h($application['admin_notes'] ?? ''); ?></textarea></div>
                <a class="button button-secondary" href="<?php echo h(app_url('admin/stalls.php')); ?>">Open Stall Allocation</a>
                <a class="button button-secondary" href="<?php echo h(app_url('tags-print.php?application_id=' . (int) $applicationId)); ?>" target="_blank" rel="noopener">Print Staff Tags</a>
                <button class="button button-primary" type="submit">Save Review</button>
            </section>

            <aside class="stack">
                <section class="panel dark-card">
                    <h2>Current Status</h2>
                    <p><?php echo badge($application['application_status']); ?> <?php echo badge($application['payment_status']); ?> <?php echo badge($application['compliance_status']); ?></p>
                    <div class="contact-card" data-contact-card>
                        <label class="check-row contact-check">
                            <input type="checkbox" data-contact-select <?php echo $contactDigits ? '' : 'disabled'; ?>>
                            <span><strong>Tel:</strong> <?php echo h($contactDisplay); ?></span>
                        </label>
                        <?php if ($contactDigits): ?>
                            <div class="contact-actions">
                                <a class="button button-primary is-disabled" href="<?php echo h($telHref); ?>" data-contact-action>Call</a>
                                <a class="button button-secondary is-disabled" href="<?php echo h($whatsappHref); ?>" target="_blank" rel="noopener" data-contact-action>WhatsApp</a>
                            </div>
                            <small>Select the phone number, then choose Call or WhatsApp.</small>
                        <?php else: ?>
                            <small>No phone number is available for this applicant.</small>
                        <?php endif; ?>
                    </div>
                </section>
                <section class="panel">
                    <h2>Pricing</h2>
                    <?php if ($pricing): ?>
                        <div class="summary-grid">
                            <div class="summary-item"><span>Rule</span><strong><?php echo h($pricing['rule']['rule_name'] ?? 'No rule'); ?></strong></div>
                            <div class="summary-item"><span>Stalls</span><strong><?php echo (int) $pricing['stall_count']; ?></strong></div>
                            <div class="summary-item"><span>Subtotal</span><strong><?php echo h(ugx_money((float) $pricing['subtotal'])); ?></strong></div>
                            <div class="summary-item"><span>Total Due</span><strong><?php echo h(ugx_money((float) $pricing['total'])); ?></strong></div>
                        </div>
                        <?php if ($pricing['discount']): ?><p class="help-text">Discount: <?php echo h(ugx_money((float) $pricing['discount_amount'])); ?> - <?php echo h($pricing['discount']['reason'] ?? 'Special discount'); ?></p><?php endif; ?>
                        <a class="button button-secondary" href="<?php echo h(app_url('admin/pricing.php')); ?>">Manage Pricing</a>
                    <?php else: ?>
                        <p class="help-text">Pricing is not available for this application.</p>
                    <?php endif; ?>
                </section>
                <section class="panel">
                    <h2>Payment Proof</h2>
                    <div class="summary-grid">
                        <div class="summary-item"><span>Total Due</span><strong><?php echo h(ugx_money((float) ($paymentTotals['total_due'] ?? 0))); ?></strong></div>
                        <div class="summary-item"><span>Verified Paid</span><strong><?php echo h(ugx_money((float) ($paymentTotals['verified'] ?? 0))); ?></strong></div>
                        <div class="summary-item"><span>Pending Review</span><strong><?php echo h(ugx_money((float) ($paymentTotals['pending'] ?? 0))); ?></strong></div>
                        <div class="summary-item"><span>Balance</span><strong><?php echo h(ugx_money((float) ($paymentTotals['balance'] ?? 0))); ?></strong></div>
                    </div>
                    <?php foreach ($paymentProofs as $proof): ?>
                        <?php
                        $modalId = payment_proof_modal_id((string) $proof['row_key']);
                        $isImage = payment_proof_is_image((string) $proof['proof_url']);
                        ?>
                        <div class="proof-card">
                            <button class="proof-thumb" type="button" data-proof-modal-open="<?php echo h($modalId); ?>">
                                <?php if ($isImage): ?>
                                    <img src="<?php echo h($proof['proof_url']); ?>" alt="Payment proof thumbnail">
                                <?php else: ?>
                                    <span class="proof-file-icon">Proof</span>
                                <?php endif; ?>
                                <span><?php echo h($proof['file_label']); ?></span>
                            </button>
                            <div>
                                <?php echo badge(payment_verification_label((string) $proof['verification_status'], $proof['admin_comment'] ?? null)); ?><br>
                                <small><?php echo h((float) ($proof['payment_amount'] ?? 0) > 0 ? ugx_money((float) $proof['payment_amount']) : 'Amount not entered'); ?></small><br>
                                <small><?php echo h($proof['payment_description'] ?: 'No description'); ?></small><br>
                                <small><?php echo h(format_date($proof['event_at'])); ?></small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!$paymentProofs): ?><p class="help-text">No payment proof available.</p><?php endif; ?>
                </section>
            </aside>
        </form>

        <div class="dashboard-grid" style="margin-top: 22px;">
            <section class="panel">
                <h2>Record Balance Payment</h2>
                <p class="help-text">Use this when a vendor pays by mobile money, bank, or cash outside the portal. A 57mm receipt with QR verification will be generated immediately.</p>
                <form method="post" enctype="multipart/form-data" class="form-grid two" data-confirm="Record this vendor payment and generate a receipt?">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="application_id" value="<?php echo (int) $applicationId; ?>">
                    <input type="hidden" name="action" value="record_payment">
                    <div class="field"><label>Paid Amount (UGX)</label><input name="paid_amount" type="number" min="1" step="1" value="<?php echo (int) round((float) ($paymentTotals['balance'] ?? 0)); ?>" required></div>
                    <div class="field"><label>Payment Description</label><input name="payment_description" value="Balance payment" maxlength="255" required></div>
                    <div class="field"><label>Payment Method</label><input name="payment_method" placeholder="Mobile Money, Bank, Cash"></div>
                    <div class="field"><label>Transaction ID</label><input name="transaction_id" maxlength="120" placeholder="Reference number"></div>
                    <div class="field"><label>Payment Time</label><input name="paid_at" type="datetime-local" value="<?php echo h(date('Y-m-d\TH:i')); ?>"></div>
                    <div class="field" style="grid-column: 1 / -1;"><label>Proof of Payment Photo</label><input name="payment_proof" type="file" accept=".pdf,.jpg,.jpeg,.png,image/*" capture="environment"><small>Use the camera to snap payment proof, or attach an existing file.</small></div>
                    <button class="button button-primary" type="submit">Record and Print Receipt</button>
                </form>
            </section>

            <aside class="panel">
                <h2>Receipts</h2>
                <div class="stack">
                    <?php foreach ($paymentReceipts as $receipt): ?>
                        <div class="proof-card">
                            <div>
                                <strong><?php echo h($receipt['receipt_reference']); ?></strong><br>
                                <small><?php echo h(ucfirst((string) $receipt['source_type'])); ?> / <?php echo h(format_date($receipt['paid_at'])); ?></small><br>
                                <small>Paid <?php echo h(ugx_money((float) $receipt['paid_amount'])); ?>, balance <?php echo h(ugx_money((float) $receipt['balance_amount'])); ?></small>
                            </div>
                            <a class="button button-secondary" href="<?php echo h(app_url('receipt.php?ref=' . rawurlencode((string) $receipt['receipt_reference']))); ?>" target="_blank" rel="noopener">Print 57mm</a>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!$paymentReceipts): ?><p class="help-text">No receipts have been generated yet.</p><?php endif; ?>
                </div>
            </aside>
        </div>

        <?php foreach ($paymentProofs as $proof): ?>
            <?php
            $modalId = payment_proof_modal_id((string) $proof['row_key']);
            $isImage = payment_proof_is_image((string) $proof['proof_url']);
            ?>
            <div class="proof-modal" id="<?php echo h($modalId); ?>" hidden data-proof-modal>
                <div class="proof-modal-backdrop" data-proof-modal-close></div>
                <section class="proof-modal-panel" role="dialog" aria-modal="true" aria-labelledby="<?php echo h($modalId); ?>-title">
                    <div class="proof-modal-header">
                        <div>
                            <h2 id="<?php echo h($modalId); ?>-title">Payment Proof</h2>
                            <p><?php echo h($application['full_name']); ?> - <?php echo h($proof['file_label']); ?></p>
                        </div>
                        <button class="icon-button" type="button" data-proof-modal-close aria-label="Close payment proof preview">x</button>
                    </div>
                    <div class="proof-modal-preview">
                        <?php if ($isImage): ?>
                            <img src="<?php echo h($proof['proof_url']); ?>" alt="Payment proof preview">
                        <?php else: ?>
                            <a class="button button-secondary" href="<?php echo h($proof['proof_url']); ?>" target="_blank" rel="noopener">Open Payment Proof</a>
                        <?php endif; ?>
                    </div>
                    <div class="proof-modal-status">
                        <?php echo badge(payment_verification_label((string) $proof['verification_status'], $proof['admin_comment'] ?? null)); ?>
                        <span><?php echo h(format_date($proof['event_at'])); ?></span>
                    </div>
                    <div class="summary-grid">
                        <div class="summary-item"><span>Amount</span><strong><?php echo h((float) ($proof['payment_amount'] ?? 0) > 0 ? ugx_money((float) $proof['payment_amount']) : 'Amount not entered'); ?></strong></div>
                        <div class="summary-item"><span>Description</span><strong><?php echo h($proof['payment_description'] ?: 'No description'); ?></strong></div>
                    </div>
                    <form method="post" class="form-grid proof-review-form">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="application_id" value="<?php echo (int) $applicationId; ?>">
                        <input type="hidden" name="source" value="<?php echo h($proof['source']); ?>">
                        <input type="hidden" name="upload_id" value="<?php echo (int) $proof['upload_id']; ?>">
                        <?php if ($proof['source'] === 'upload'): ?><textarea name="admin_comment" placeholder="Admin comment"><?php echo h($proof['admin_comment']); ?></textarea><?php endif; ?>
                        <div class="proof-modal-actions">
                            <button class="button button-primary" name="payment_verification_action" value="verify" type="submit">Verify</button>
                            <button class="button button-danger" name="payment_verification_action" value="reject" type="submit">Reject</button>
                            <button class="button button-secondary" name="payment_verification_action" value="flag_reupload" type="submit">Flag for Re-upload</button>
                        </div>
                    </form>
                </section>
            </div>
        <?php endforeach; ?>
    </main>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
