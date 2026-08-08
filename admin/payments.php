<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
$admin = require_login('admin');
$active = 'payments';
$pageTitle = 'Payments';
$pdo = db();
ensure_payment_upload_schema();

$backfillReceipts = $pdo->query(
    'SELECT fr.id
     FROM form_responses fr
     LEFT JOIN payment_receipts pr ON pr.source_type = "sheet" AND pr.form_response_id = fr.id
     WHERE COALESCE(fr.sheet_paid_amount, 0) > 0 AND pr.id IS NULL
     LIMIT 200'
)->fetchAll();
foreach ($backfillReceipts as $row) {
    sync_sheet_payment_receipt($pdo, (int) $row['id']);
}

$backfillUploadReceipts = $pdo->query(
    'SELECT pu.id
     FROM payment_uploads pu
     LEFT JOIN payment_receipts pr ON pr.payment_upload_id = pu.id
     WHERE pu.verification_status = "Verified" AND pr.id IS NULL
     LIMIT 200'
)->fetchAll();
foreach ($backfillUploadReceipts as $row) {
    sync_upload_payment_receipt($pdo, (int) $row['id']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $uploadId = (int) ($_POST['upload_id'] ?? 0);
    $applicationId = (int) ($_POST['application_id'] ?? 0);
    $source = (string) ($_POST['source'] ?? 'upload');
    $action = (string) ($_POST['action'] ?? '');
    $comment = trim((string) ($_POST['admin_comment'] ?? ''));

    if ($action === 'bulk_verify') {
        $selectedPayments = $_POST['selected_payments'] ?? [];
        if (!is_array($selectedPayments) || !$selectedPayments) {
            set_flash('error', 'Select at least one payment to verify.');
            redirect('admin/payments.php');
        }

        $verified = 0;
        $skipped = 0;
        try {
            $pdo->beginTransaction();
            foreach (array_unique(array_map('strval', $selectedPayments)) as $rowKey) {
                if (preg_match('/^upload:(\d+)$/', $rowKey, $matches)) {
                    $bulkUploadId = (int) $matches[1];
                    $upload = $pdo->prepare('SELECT * FROM payment_uploads WHERE id = ? LIMIT 1');
                    $upload->execute([$bulkUploadId]);
                    $uploadRow = $upload->fetch();
                    if (!$uploadRow) {
                        $skipped++;
                        continue;
                    }

                    $pdo->prepare('UPDATE payment_uploads SET verification_status = "Verified", verified_by = ?, verified_at = NOW() WHERE id = ?')
                        ->execute([(int) $admin['id'], $bulkUploadId]);
                    sync_upload_payment_receipt($pdo, $bulkUploadId);
                    refresh_application_payment_status_from_uploads($pdo, (int) $uploadRow['application_id']);
                    $verified++;
                    continue;
                }

                if (preg_match('/^sheet:(\d+)$/', $rowKey, $matches)) {
                    $responseId = (int) $matches[1];
                    $receipt = sync_sheet_payment_receipt($pdo, $responseId);
                    sync_form_response_linked_portal_records($pdo, $responseId);

                    $apps = $pdo->prepare('SELECT id FROM applications WHERE form_response_id = ?');
                    $apps->execute([$responseId]);
                    foreach ($apps->fetchAll() as $app) {
                        refresh_application_payment_status_from_uploads($pdo, (int) $app['id']);
                    }

                    if ($receipt) {
                        $verified++;
                    } else {
                        $skipped++;
                    }
                    continue;
                }

                if (preg_match('/^receipt:(\d+)$/', $rowKey, $matches)) {
                    $receiptCheck = $pdo->prepare('SELECT id FROM payment_receipts WHERE id = ? LIMIT 1');
                    $receiptCheck->execute([(int) $matches[1]]);
                    if ($receiptCheck->fetch()) {
                        $verified++;
                    } else {
                        $skipped++;
                    }
                    continue;
                }

                $skipped++;
            }
            $pdo->commit();
            set_flash('success', 'Verified ' . number_format($verified) . ' selected payment' . ($verified === 1 ? '' : 's') . ($skipped > 0 ? '. Skipped ' . number_format($skipped) . '.' : '.'));
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Bulk payment verification failed: ' . $exception->getMessage());
            set_flash('error', 'Selected payments could not be verified.');
        }
        redirect('admin/payments.php');
    }

    if ($action === 'attach_proof') {
        $receiptReference = trim((string) ($_POST['receipt_reference'] ?? ''));
        $rowKey = trim((string) ($_POST['row_key'] ?? ''));
        $receipt = $receiptReference !== '' ? fetch_payment_receipt($pdo, 'receipt_reference', $receiptReference) : null;
        if (!$receipt && preg_match('/^sheet:(\d+)$/', $rowKey, $matches)) {
            $receipt = sync_sheet_payment_receipt($pdo, (int) $matches[1]);
        }

        if (!$receipt) {
            set_flash('error', 'Receipt not found for proof attachment.');
            redirect('admin/payments.php');
        }

        try {
            [$proofPath, $proofOriginalName, $proofType, $proofSize] = save_admin_payment_proof_file($_FILES['payment_proof'] ?? null);
            if (!$proofPath) {
                throw new RuntimeException('Choose or snap a proof image/file first.');
            }
            $pdo->prepare('UPDATE payment_receipts SET proof_file_path = ?, proof_original_filename = ?, proof_file_type = ?, proof_file_size = ?, updated_at = NOW() WHERE id = ?')
                ->execute([$proofPath, $proofOriginalName, $proofType, $proofSize, (int) $receipt['id']]);
            set_flash('success', 'Payment proof attached to receipt ' . $receipt['receipt_reference'] . '.');
        } catch (Throwable $exception) {
            error_log('Attach payment proof failed: ' . $exception->getMessage());
            set_flash('error', $exception instanceof RuntimeException ? $exception->getMessage() : 'Payment proof could not be attached.');
        }
        redirect('admin/payments.php');
    }

    if ($action === 'record_payment') {
        $amount = parse_money_amount((string) ($_POST['paid_amount'] ?? ''));
        $description = trim((string) ($_POST['payment_description'] ?? 'Balance payment'));
        $method = trim((string) ($_POST['payment_method'] ?? ''));
        $transactionId = trim((string) ($_POST['transaction_id'] ?? ''));
        $paidAt = trim((string) ($_POST['paid_at'] ?? ''));

        if ($applicationId <= 0) {
            set_flash('error', 'Select the applicant receiving this payment.');
            redirect('admin/payments.php');
        }
        if ($amount <= 0) {
            set_flash('error', 'Enter the payment amount received.');
            redirect('admin/payments.php');
        }

        try {
            $receipt = create_manual_payment_receipt($pdo, $applicationId, $amount, $description, $method, $transactionId, $paidAt !== '' ? $paidAt : null, (int) $admin['id'], $_FILES['payment_proof'] ?? null);
            set_flash('success', 'Payment received. Receipt ' . $receipt['receipt_reference'] . ' is ready to print.');
            redirect('receipt.php?ref=' . rawurlencode((string) $receipt['receipt_reference']));
        } catch (Throwable $exception) {
            error_log('Receive payment failed: ' . $exception->getMessage());
            set_flash('error', $exception instanceof RuntimeException ? $exception->getMessage() : 'Payment could not be recorded.');
            redirect('admin/payments.php');
        }
    }

    if (!in_array($action, ['verify', 'reject', 'flag_reupload'], true)) {
        set_flash('error', 'Invalid payment action.');
        redirect('admin/payments.php');
    }
    $status = $action === 'verify' ? 'Verified' : 'Rejected';
    $paymentStatus = $action === 'verify' ? 'Payment Received' : 'Payment Rejected';
    if ($action === 'flag_reupload') {
        $comment = payment_reupload_comment($comment);
    }

    if ($source === 'sheet') {
        if ($applicationId <= 0) {
            set_flash('error', 'This imported payment proof is not linked to a portal application yet.');
            redirect('admin/payments.php');
        }
        if ($action === 'verify') {
            refresh_application_payment_status_from_uploads($pdo, $applicationId);
        } else {
            $pdo->prepare('UPDATE applications SET payment_status = ?, updated_at = NOW() WHERE id = ?')
                ->execute([$paymentStatus, $applicationId]);
        }
        set_flash('success', $action === 'flag_reupload' ? 'Imported payment proof flagged for re-upload.' : 'Imported payment proof status updated.');
    } else {
        $upload = $pdo->prepare('SELECT * FROM payment_uploads WHERE id = ? LIMIT 1');
        $upload->execute([$uploadId]);
        $row = $upload->fetch();
        if ($row) {
            $pdo->prepare('UPDATE payment_uploads SET verification_status = ?, verified_by = ?, verified_at = NOW(), admin_comment = ? WHERE id = ?')
                ->execute([$status, (int) $admin['id'], $comment, $uploadId]);
            if ($status === 'Verified') {
                sync_upload_payment_receipt($pdo, $uploadId);
            }
            refresh_application_payment_status_from_uploads($pdo, (int) $row['application_id']);
            set_flash('success', $action === 'flag_reupload' ? 'Payment proof flagged for re-upload.' : 'Payment proof updated.');
        } else {
            set_flash('error', 'Payment upload not found.');
        }
    }
    redirect('admin/payments.php');
}

$statement = $pdo->query(
    'SELECT * FROM (
        SELECT CONCAT("upload:", pu.id) AS row_key,
               "upload" AS source,
               pu.id AS upload_id,
               a.id AS application_id,
               fr.id AS form_response_id,
               u.full_name,
               u.email,
               fr.business_name,
                pu.file_path AS proof_url,
                pu.original_filename AS file_label,
                pu.payment_amount,
                pu.payment_description,
                pr.receipt_reference,
                pu.uploaded_at AS event_at,
                CAST(pu.verification_status AS CHAR(40)) AS verification_status,
               pu.admin_comment,
               CAST(a.payment_status AS CHAR(40)) AS payment_status,
               1 AS can_verify
        FROM payment_uploads pu
        INNER JOIN users u ON u.id = pu.user_id
        INNER JOIN applications a ON a.id = pu.application_id
        LEFT JOIN form_responses fr ON fr.id = a.form_response_id
        LEFT JOIN payment_receipts pr ON pr.payment_upload_id = pu.id
        UNION ALL
        SELECT CONCAT("receipt:", pr.id) AS row_key,
               "admin" AS source,
               NULL AS upload_id,
               a.id AS application_id,
               fr.id AS form_response_id,
               COALESCE(NULLIF(u.full_name, ""), NULLIF(fr.full_name, ""), "Applicant") AS full_name,
               COALESCE(NULLIF(u.email, ""), NULLIF(fr.email, "")) AS email,
               fr.business_name,
               pr.proof_file_path AS proof_url,
               COALESCE(NULLIF(pr.proof_original_filename, ""), "Admin received payment") AS file_label,
               pr.paid_amount AS payment_amount,
               COALESCE(NULLIF(pr.payment_description, ""), "Admin received payment") AS payment_description,
               pr.receipt_reference,
               COALESCE(pr.paid_at, pr.created_at) AS event_at,
               "Verified" AS verification_status,
               NULL AS admin_comment,
               COALESCE(CAST(a.payment_status AS CHAR(40)), "Pending Verification") AS payment_status,
               1 AS can_verify
        FROM payment_receipts pr
        LEFT JOIN applications a ON a.id = pr.application_id
        LEFT JOIN users u ON u.id = COALESCE(pr.user_id, a.user_id)
        LEFT JOIN form_responses fr ON fr.id = COALESCE(pr.form_response_id, a.form_response_id)
        WHERE pr.source_type = "admin"
        UNION ALL
        SELECT CONCAT("sheet:", fr.id) AS row_key,
               "sheet" AS source,
               NULL AS upload_id,
               a.id AS application_id,
               fr.id AS form_response_id,
               COALESCE(NULLIF(u.full_name, ""), NULLIF(fr.full_name, ""), "Applicant") AS full_name,
               COALESCE(NULLIF(u.email, ""), NULLIF(fr.email, "")) AS email,
               fr.business_name,
                 fr.proof_of_payment_url AS proof_url,
                 CASE WHEN COALESCE(fr.sheet_paid_amount, 0) > 0 THEN "Paid register payment" ELSE "Imported payment proof" END AS file_label,
                 COALESCE(fr.sheet_paid_amount, 0) AS payment_amount,
                 CASE WHEN COALESCE(fr.sheet_paid_amount, 0) > 0 THEN "Paid register payment" ELSE "Imported payment proof" END AS payment_description,
                 pr.receipt_reference,
                 COALESCE(fr.payment_recorded_at, fr.submitted_at, fr.updated_at, fr.created_at) AS event_at,
                CASE
                    WHEN COALESCE(fr.sheet_paid_amount, 0) > 0 THEN "Verified"
                    WHEN COALESCE(CAST(a.payment_status AS CHAR(40)), "Pending Verification") = "Payment Received" THEN "Verified"
                    WHEN COALESCE(CAST(a.payment_status AS CHAR(40)), "Pending Verification") = "Payment Rejected" THEN "Rejected"
                    ELSE "Pending"
                END AS verification_status,
               NULL AS admin_comment,
               COALESCE(CAST(a.payment_status AS CHAR(40)), "Pending Verification") AS payment_status,
                1 AS can_verify
        FROM form_responses fr
        LEFT JOIN applications a ON a.form_response_id = fr.id
        LEFT JOIN users u ON u.id = a.user_id
        LEFT JOIN payment_receipts pr ON pr.source_type = "sheet" AND pr.form_response_id = fr.id
        WHERE COALESCE(fr.proof_of_payment_url, "") <> "" OR COALESCE(fr.sheet_paid_amount, 0) > 0
     ) payment_rows
     ORDER BY event_at DESC, row_key DESC'
);
$payments = $statement->fetchAll();

$paymentApplicants = $pdo->query(
    'SELECT a.id, u.full_name, u.email, u.phone, fr.business_name, fr.business_nature
     FROM applications a
     INNER JOIN users u ON u.id = a.user_id
     LEFT JOIN form_responses fr ON fr.id = a.form_response_id
     ORDER BY u.full_name ASC'
)->fetchAll();
foreach ($paymentApplicants as &$applicant) {
    $pricing = calculate_application_pricing($pdo, (int) $applicant['id']);
    $totals = payment_upload_totals($pdo, (int) $applicant['id'], $pricing);
    $applicant['balance'] = (float) ($totals['balance'] ?? 0);
    $applicant['verified_paid'] = (float) ($totals['verified'] ?? 0);
    $applicant['total_due'] = (float) ($totals['total_due'] ?? 0);
}
unset($applicant);

require_once __DIR__ . '/../includes/header.php';
?>
<div class="app-shell">
    <?php require __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="app-main">
        <div class="page-header">
            <div>
                <h1>Payment Verification</h1>
                <p>Review portal uploads and payment proof links imported from the Google Sheet.</p>
            </div>
            <div class="header-actions">
                <a class="button button-secondary" href="<?php echo h(app_url('admin/reports.php?export=payments_xls')); ?>">Export Updated XLS</a>
                <button class="button button-primary" type="button" data-proof-modal-open="receive-payment-modal">Receive Payment</button>
            </div>
        </div>

        <section class="panel">
            <form method="post" data-bulk-payments-form data-confirm="Verify the selected payments?">
                <?php echo csrf_field(); ?>
                <div class="bulk-action-bar">
                    <span><strong data-payment-selected-count>0</strong> selected</span>
                    <button class="button button-primary" name="action" value="bulk_verify" type="submit">Bulk Verify Selected</button>
                </div>
                <div class="table-scroll">
                    <table>
                        <thead><tr><th><input type="checkbox" data-select-all-payments aria-label="Select all visible payments"></th><th>Applicant</th><th>Business</th><th>Payment</th><th>Proof</th><th>Source</th><th>Status</th><th>Comment</th><th>Action</th></tr></thead>
                        <tbody>
                        <?php foreach ($payments as $payment): ?>
                        <?php
                        $isUpload = $payment['source'] === 'upload';
                        $isLocalProof = in_array((string) $payment['source'], ['upload', 'admin'], true);
                        $rawProofUrl = trim((string) ($payment['proof_url'] ?? ''));
                        $proofUrl = $rawProofUrl !== '' && $isLocalProof ? app_url($rawProofUrl) : $rawProofUrl;
                        $hasProofUrl = trim($proofUrl) !== '';
                        $modalId = payment_proof_modal_id((string) $payment['row_key']);
                        $isImage = $hasProofUrl && payment_proof_is_image($proofUrl);
                        $statusLabel = payment_verification_label((string) $payment['verification_status'], $payment['admin_comment'] ?? null);
                        if (!$isUpload && ($payment['payment_status'] ?? '') === 'Payment Rejected' && $statusLabel === 'Rejected') {
                            $statusLabel = 'Re-upload Required';
                        }
                        ?>
                        <tr>
                            <td data-label="Select"><input type="checkbox" name="selected_payments[]" value="<?php echo h($payment['row_key']); ?>" data-payment-row-select aria-label="Select payment for <?php echo h($payment['full_name'] ?? 'applicant'); ?>"></td>
                            <td data-label="Applicant"><strong><?php echo h($payment['full_name']); ?></strong><br><small><?php echo h($payment['email']); ?></small></td>
                            <td data-label="Business"><?php echo h($payment['business_name'] ?? 'Not provided'); ?></td>
                            <td data-label="Payment"><strong><?php echo h((float) ($payment['payment_amount'] ?? 0) > 0 ? ugx_money((float) $payment['payment_amount']) : 'Not entered'); ?></strong><br><small><?php echo h($payment['payment_description'] ?? 'No description'); ?></small></td>
                            <td data-label="Proof">
                                <button class="proof-thumb" type="button" data-proof-modal-open="<?php echo h($modalId); ?>">
                                    <?php if ($isImage): ?>
                                        <img src="<?php echo h($proofUrl); ?>" alt="Payment proof thumbnail">
                                    <?php else: ?>
                                        <span class="proof-file-icon"><?php echo $hasProofUrl ? 'Proof' : 'Record'; ?></span>
                                    <?php endif; ?>
                                    <span><?php echo h($payment['file_label']); ?></span>
                                </button>
                                <small><?php echo h(format_date($payment['event_at'])); ?></small>
                            </td>
                            <td data-label="Source"><?php echo badge(match ((string) $payment['source']) { 'upload' => 'Portal Upload', 'admin' => 'Admin Received', default => 'Google Sheet' }); ?><br><small><?php echo h($payment['payment_status']); ?></small></td>
                            <td data-label="Status"><?php echo badge($statusLabel); ?></td>
                            <td data-label="Comment"><?php echo h($payment['admin_comment'] ?? ''); ?></td>
                            <td data-label="Action">
                                <?php if ((int) $payment['can_verify'] === 1): ?>
                                    <div class="icon-action-row">
                                        <button class="table-icon-button" type="button" data-proof-modal-open="<?php echo h($modalId); ?>" title="Review payment" aria-label="Review payment for <?php echo h($payment['full_name']); ?>">
                                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5C6.7 5 2.6 9.4 1.2 12c1.4 2.6 5.5 7 10.8 7s9.4-4.4 10.8-7C21.4 9.4 17.3 5 12 5Zm0 11a4 4 0 1 1 0-8 4 4 0 0 1 0 8Zm0-2.2a1.8 1.8 0 1 0 0-3.6 1.8 1.8 0 0 0 0 3.6Z"/></svg>
                                        </button>
                                        <?php if (!$hasProofUrl && !empty($payment['receipt_reference'])): ?>
                                            <button class="table-icon-button" type="button" data-proof-modal-open="attach-proof-<?php echo h(preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) $payment['row_key'])); ?>" title="Attach proof photo" aria-label="Attach proof photo for <?php echo h($payment['full_name']); ?>">
                                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 4 7.2 6H4a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-3.2L15 4H9Zm3 14a5 5 0 1 1 0-10 5 5 0 0 1 0 10Zm0-2a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z"/></svg>
                                            </button>
                                        <?php endif; ?>
                                    <?php if (!empty($payment['receipt_reference'])): ?>
                                            <a class="table-icon-button" href="<?php echo h(app_url('receipt.php?ref=' . rawurlencode((string) $payment['receipt_reference']))); ?>" target="_blank" rel="noopener" title="Print receipt" aria-label="Print receipt for <?php echo h($payment['full_name']); ?>">
                                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 3h10v5H7V3Zm-2 7h14a3 3 0 0 1 3 3v5h-4v3H6v-3H2v-5a3 3 0 0 1 3-3Zm3 9h8v-5H8v5Zm11-4v-2h-2v2h2Z"/></svg>
                                            </a>
                                    <?php else: ?>
                                            <span class="table-icon-button is-disabled" title="Receipt appears after verification" aria-label="Receipt appears after verification">
                                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a10 10 0 1 0 10 10h-2a8 8 0 1 1-8-8V2Zm1 5h-2v6l5 3 .9-1.6-3.9-2.3V7Z"/></svg>
                                            </span>
                                    <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (!$payments): ?><tr><td colspan="9" class="empty-state">No payment uploads or imported payment proof links yet.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </form>
        </section>

        <div class="proof-modal" id="receive-payment-modal" hidden data-proof-modal>
            <div class="proof-modal-backdrop" data-proof-modal-close></div>
            <section class="proof-modal-panel" role="dialog" aria-modal="true" aria-labelledby="receive-payment-modal-title">
                <div class="proof-modal-header">
                    <div>
                        <h2 id="receive-payment-modal-title">Receive Balance Payment</h2>
                        <p>Record cash, mobile money, or bank payments and print a 57mm receipt immediately.</p>
                    </div>
                    <button class="icon-button" type="button" data-proof-modal-close aria-label="Close receive payment form">x</button>
                </div>
                <form method="post" enctype="multipart/form-data" class="form-grid two" data-confirm="Record this received payment and generate a receipt?">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="record_payment">
                    <input type="hidden" name="application_id" value="" data-applicant-id>
                    <div class="field" style="grid-column: 1 / -1;">
                        <label for="payment_applicant_search">Applicant / Vendor</label>
                        <input id="payment_applicant_search" name="applicant_search" list="payment-applicant-options" data-applicant-search placeholder="Search name, phone, or business" required>
                        <datalist id="payment-applicant-options">
                            <?php foreach ($paymentApplicants as $applicant): ?>
                                <?php
                                $label = trim((string) $applicant['full_name']) . ' - ' . trim((string) ($applicant['business_name'] ?: 'No business')) . ' - ' . trim((string) ($applicant['phone'] ?: $applicant['email'] ?: 'No contact')) . ' - balance ' . ugx_money((float) $applicant['balance']);
                                ?>
                                <option value="<?php echo h($label); ?>" data-application-id="<?php echo (int) $applicant['id']; ?>"></option>
                            <?php endforeach; ?>
                        </datalist>
                        <small>Select the applicant from the search results so the payment is linked correctly.</small>
                    </div>
                    <div class="field"><label>Paid Amount (UGX)</label><input name="paid_amount" type="number" min="1" step="1" required></div>
                    <div class="field"><label>Payment Description</label><input name="payment_description" value="Balance payment" maxlength="255" required></div>
                    <div class="field"><label>Payment Method</label><input name="payment_method" placeholder="Mobile Money, Bank, Cash"></div>
                    <div class="field"><label>Transaction ID / Reference</label><input name="transaction_id" maxlength="120" placeholder="Reference number"></div>
                    <div class="field"><label>Payment Time</label><input name="paid_at" type="datetime-local" value="<?php echo h(date('Y-m-d\TH:i')); ?>"></div>
                    <div class="field" style="grid-column: 1 / -1;"><label>Proof of Payment Photo</label><input name="payment_proof" type="file" accept=".pdf,.jpg,.jpeg,.png,image/*" capture="environment"><small>Use the camera to snap mobile money/cash proof, or attach an existing proof file.</small></div>
                    <div class="proof-modal-actions" style="grid-column: 1 / -1;">
                        <button class="button button-primary" type="submit">Receive Payment and Print Receipt</button>
                    </div>
                </form>
            </section>
        </div>

        <?php foreach ($payments as $payment): ?>
            <?php
            $isLocalProof = in_array((string) $payment['source'], ['upload', 'admin'], true);
            $rawProofUrl = trim((string) ($payment['proof_url'] ?? ''));
            $proofUrl = $rawProofUrl !== '' && $isLocalProof ? app_url($rawProofUrl) : $rawProofUrl;
            if (trim($proofUrl) !== '' || empty($payment['receipt_reference'])) {
                continue;
            }
            $attachModalId = 'attach-proof-' . preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) $payment['row_key']);
            ?>
            <div class="proof-modal" id="<?php echo h($attachModalId); ?>" hidden data-proof-modal>
                <div class="proof-modal-backdrop" data-proof-modal-close></div>
                <section class="proof-modal-panel" role="dialog" aria-modal="true" aria-labelledby="<?php echo h($attachModalId); ?>-title">
                    <div class="proof-modal-header">
                        <div>
                            <h2 id="<?php echo h($attachModalId); ?>-title">Attach Payment Proof</h2>
                            <p><?php echo h($payment['full_name']); ?> - <?php echo h($payment['receipt_reference']); ?></p>
                        </div>
                        <button class="icon-button" type="button" data-proof-modal-close aria-label="Close attach proof form">x</button>
                    </div>
                    <form method="post" enctype="multipart/form-data" class="form-grid" data-confirm="Attach this proof to the existing receipt?">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="attach_proof">
                        <input type="hidden" name="row_key" value="<?php echo h($payment['row_key']); ?>">
                        <input type="hidden" name="receipt_reference" value="<?php echo h($payment['receipt_reference']); ?>">
                        <div class="field">
                            <label>Proof Photo / File</label>
                            <input name="payment_proof" type="file" accept=".pdf,.jpg,.jpeg,.png,image/*" capture="environment" required>
                            <small>Use the camera to snap old proof, or upload an existing image/PDF.</small>
                        </div>
                        <div class="proof-modal-actions">
                            <button class="button button-primary" type="submit">Attach Proof</button>
                        </div>
                    </form>
                </section>
            </div>
        <?php endforeach; ?>

        <?php foreach ($payments as $payment): ?>
            <?php
            $isUpload = $payment['source'] === 'upload';
            $isLocalProof = in_array((string) $payment['source'], ['upload', 'admin'], true);
            $rawProofUrl = trim((string) ($payment['proof_url'] ?? ''));
            $proofUrl = $rawProofUrl !== '' && $isLocalProof ? app_url($rawProofUrl) : $rawProofUrl;
            $hasProofUrl = trim($proofUrl) !== '';
            $modalId = payment_proof_modal_id((string) $payment['row_key']);
            $isImage = $hasProofUrl && payment_proof_is_image($proofUrl);
            $statusLabel = payment_verification_label((string) $payment['verification_status'], $payment['admin_comment'] ?? null);
            if (!$isUpload && ($payment['payment_status'] ?? '') === 'Payment Rejected' && $statusLabel === 'Rejected') {
                $statusLabel = 'Re-upload Required';
            }
            $showVerificationForm = $payment['source'] === 'upload' || ($payment['source'] === 'sheet' && (int) ($payment['application_id'] ?? 0) > 0 && $statusLabel !== 'Verified');
            ?>
            <div class="proof-modal" id="<?php echo h($modalId); ?>" hidden data-proof-modal>
                <div class="proof-modal-backdrop" data-proof-modal-close></div>
                <section class="proof-modal-panel" role="dialog" aria-modal="true" aria-labelledby="<?php echo h($modalId); ?>-title">
                    <div class="proof-modal-header">
                        <div>
                            <h2 id="<?php echo h($modalId); ?>-title">Payment Proof</h2>
                            <p><?php echo h($payment['full_name']); ?> - <?php echo h($payment['file_label']); ?></p>
                        </div>
                        <button class="icon-button" type="button" data-proof-modal-close aria-label="Close payment proof preview">x</button>
                    </div>
                    <div class="proof-modal-preview">
                        <?php if ($isImage): ?>
                            <img src="<?php echo h($proofUrl); ?>" alt="Payment proof preview">
                        <?php elseif ($hasProofUrl): ?>
                            <a class="button button-secondary" href="<?php echo h($proofUrl); ?>" target="_blank" rel="noopener">Open Payment Proof</a>
                        <?php else: ?>
                            <p class="help-text">This payment came from the paid vendor register. No proof file link was provided in the sheet.</p>
                        <?php endif; ?>
                    </div>
                    <div class="proof-modal-status">
                        <?php echo badge($statusLabel); ?>
                        <span><?php echo h(format_date($payment['event_at'])); ?></span>
                    </div>
                    <div class="summary-grid">
                        <div class="summary-item"><span>Amount</span><strong><?php echo h((float) ($payment['payment_amount'] ?? 0) > 0 ? ugx_money((float) $payment['payment_amount']) : 'Not entered'); ?></strong></div>
                        <div class="summary-item"><span>Description</span><strong><?php echo h($payment['payment_description'] ?? 'No description'); ?></strong></div>
                    </div>
                    <?php if ($showVerificationForm): ?>
                        <form method="post" class="form-grid proof-review-form">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="source" value="<?php echo h($payment['source']); ?>">
                            <input type="hidden" name="upload_id" value="<?php echo (int) ($payment['upload_id'] ?? 0); ?>">
                            <input type="hidden" name="application_id" value="<?php echo (int) ($payment['application_id'] ?? 0); ?>">
                            <?php if ($isUpload): ?><textarea name="admin_comment" placeholder="Admin comment"><?php echo h($payment['admin_comment'] ?? ''); ?></textarea><?php endif; ?>
                            <div class="proof-modal-actions">
                                <button class="button button-primary" name="action" value="verify" type="submit">Verify</button>
                                <button class="button button-danger" name="action" value="reject" type="submit">Reject</button>
                                <button class="button button-secondary" name="action" value="flag_reupload" type="submit">Flag for Re-upload</button>
                            </div>
                        </form>
                    <?php else: ?>
                        <p class="help-text">This payment record is already admin-confirmed. Use the receipt action to print or verify it.</p>
                    <?php endif; ?>
                </section>
            </div>
        <?php endforeach; ?>
    </main>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
