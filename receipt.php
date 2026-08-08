<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
$user = require_login();
$pdo = db();
ensure_vendor_access_schema($pdo);

$reference = trim((string) ($_GET['ref'] ?? ''));
$token = trim((string) ($_GET['token'] ?? ''));
$receipt = null;
if ($reference !== '') {
    $receipt = fetch_payment_receipt($pdo, 'receipt_reference', $reference);
} elseif ($token !== '') {
    $receipt = fetch_payment_receipt($pdo, 'receipt_token', $token);
}

if (!$receipt) {
    set_flash('error', 'Receipt not found.');
    redirect(($user['role'] ?? '') === 'admin' ? 'admin/payments.php' : 'applicant/dashboard.php');
}

$isAdmin = ($user['role'] ?? '') === 'admin';
$ownsReceipt = (int) ($receipt['user_id'] ?? 0) === (int) $user['id'];
if (!$isAdmin && !$ownsReceipt) {
    set_flash('error', 'You do not have access to that receipt.');
    redirect('applicant/dashboard.php');
}

$pageTitle = 'Receipt ' . $receipt['receipt_reference'];
$bodyClass = 'receipt-page';
$extraHead = '<style media="print">@page { size: 57mm auto; margin: 0; }</style>';
$verifyUrl = receipt_verification_url($receipt);
$qrUrl = receipt_qr_image_url($verifyUrl, 150);
$vendorName = payment_receipt_identity($receipt);
$phone = trim((string) ($receipt['user_phone'] ?? $receipt['sheet_phone'] ?? ''));
$email = trim((string) ($receipt['user_email'] ?? $receipt['sheet_email'] ?? ''));
$inquiryPhone = trim(setting('contact_phone', ''));
$inquiryEmail = trim(setting('contact_email', ''));
$method = trim((string) ($receipt['payment_method'] ?? ''));
$transactionId = trim((string) ($receipt['transaction_id'] ?? ''));
$receiptDescription = trim($method . ($method !== '' && $transactionId !== '' ? ' / ' : '') . ($transactionId !== '' ? 'Txn: ' . $transactionId : ''));
$receiptDescription = $receiptDescription !== '' ? $receiptDescription : 'Payment';

require_once __DIR__ . '/includes/header.php';
?>
<main class="receipt-shell">
    <div class="receipt-actions no-print">
        <button class="button button-primary" type="button" onclick="window.print()">Print 57mm Receipt</button>
        <a class="button button-ghost" href="<?php echo h($isAdmin ? app_url('admin/payments.php') : app_url('applicant/dashboard.php')); ?>">Back</a>
    </div>

    <article class="receipt-paper" aria-label="Payment receipt">
        <header class="receipt-header">
            <strong><?php echo h(setting('event_name', APP_EVENT_NAME)); ?></strong>
            <span>Payment Receipt</span>
        </header>

        <div class="receipt-row"><span>Ref</span><strong><?php echo h($receipt['receipt_reference']); ?></strong></div>
        <div class="receipt-row"><span>Date</span><strong><?php echo h(format_date($receipt['paid_at'] ?? $receipt['created_at'])); ?></strong></div>
        <div class="receipt-row"><span>Vendor</span><strong><?php echo h($vendorName); ?></strong></div>
        <?php if ($phone !== ''): ?><div class="receipt-row"><span>Phone</span><strong><?php echo h($phone); ?></strong></div><?php endif; ?>
        <?php if ($email !== ''): ?><div class="receipt-row"><span>Email</span><strong><?php echo h($email); ?></strong></div><?php endif; ?>
        <div class="receipt-row"><span>Business</span><strong><?php echo h($receipt['business_name'] ?? 'Not provided'); ?></strong></div>
        <div class="receipt-row"><span>Nature</span><strong><?php echo h($receipt['business_nature'] ?? 'Not provided'); ?></strong></div>
        <div class="receipt-separator"></div>
        <div class="receipt-row"><span>Description</span><strong><?php echo h($receiptDescription); ?></strong></div>
        <div class="receipt-row total"><span>Paid</span><strong><?php echo h(ugx_money((float) $receipt['paid_amount'])); ?></strong></div>
        <div class="receipt-row"><span>Total Due</span><strong><?php echo h(ugx_money((float) $receipt['total_amount'])); ?></strong></div>
        <div class="receipt-row"><span>Balance</span><strong><?php echo h(ugx_money((float) $receipt['balance_amount'])); ?></strong></div>
        <?php if (!empty($receipt['received_by'])): ?><div class="receipt-row"><span>Received By</span><strong><?php echo h($receipt['received_by']); ?></strong></div><?php endif; ?>

        <div class="receipt-separator"></div>
        <div class="receipt-qr">
            <img src="<?php echo h($qrUrl); ?>" alt="Receipt verification QR code">
            <strong>Scan to verify receipt</strong>
        </div>
        <p class="receipt-footer-text">This receipt is valid only when the QR verification profile matches the vendor details above.</p>
        <?php if ($inquiryPhone !== '' || $inquiryEmail !== ''): ?>
            <p class="receipt-footer-text">Inquiries: <?php echo h(trim($inquiryPhone . ($inquiryPhone !== '' && $inquiryEmail !== '' ? ' / ' : '') . $inquiryEmail)); ?></p>
        <?php endif; ?>
    </article>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
