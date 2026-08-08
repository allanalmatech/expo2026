<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
$admin = require_login('admin');
$active = 'payments';
$pageTitle = 'Verify Receipt';
$pdo = db();
ensure_vendor_access_schema($pdo);

$token = trim((string) ($_GET['token'] ?? ''));
$receipt = $token !== '' ? fetch_payment_receipt($pdo, 'receipt_token', $token) : null;

if (!$receipt) {
    require_once __DIR__ . '/../includes/header.php';
    ?>
    <div class="app-shell">
        <?php require __DIR__ . '/../includes/sidebar.php'; ?>
        <main class="app-main">
            <section class="panel">
                <div class="empty-state"><h1>Receipt Not Verified</h1><p>The scanned QR code does not match a receipt in this portal.</p></div>
            </section>
        </main>
    </div>
    <?php require __DIR__ . '/../includes/footer.php';
    exit;
}

$applicationId = (int) ($receipt['application_id'] ?? 0);
$totals = $applicationId > 0 ? payment_upload_totals($pdo, $applicationId) : [
    'verified' => (float) ($receipt['sheet_paid_amount'] ?? $receipt['paid_amount'] ?? 0),
    'balance' => (float) ($receipt['sheet_balance_due'] ?? $receipt['balance_amount'] ?? 0),
    'total_due' => (float) ($receipt['sheet_total_due'] ?? $receipt['total_amount'] ?? 0),
];
$maxStaff = max_staff_for_application($receipt);
$vendorName = payment_receipt_identity($receipt);

require_once __DIR__ . '/../includes/header.php';
?>
<div class="app-shell">
    <?php require __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="app-main">
        <div class="page-header">
            <div>
                <h1>Receipt Verified</h1>
                <p>Reference <?php echo h($receipt['receipt_reference']); ?> belongs to <?php echo h($vendorName); ?>.</p>
            </div>
            <?php echo badge((float) ($totals['balance'] ?? 0) <= 0 ? 'Fully Paid' : 'Balance Due'); ?>
        </div>

        <div class="dashboard-grid">
            <section class="panel dark-card">
                <h2>Payment Security Check</h2>
                <div class="summary-grid">
                    <div class="summary-item"><span>Receipt Paid</span><strong><?php echo h(ugx_money((float) $receipt['paid_amount'])); ?></strong></div>
                    <div class="summary-item"><span>Total Verified Paid</span><strong><?php echo h(ugx_money((float) ($totals['verified'] ?? 0))); ?></strong></div>
                    <div class="summary-item"><span>Balance</span><strong><?php echo h(ugx_money((float) ($totals['balance'] ?? 0))); ?></strong></div>
                    <div class="summary-item"><span>Payment Status</span><strong><?php echo h($receipt['payment_status'] ?? 'Sheet-only payment'); ?></strong></div>
                    <div class="summary-item"><span>Payment Method</span><strong><?php echo h($receipt['payment_method'] ?? 'Not recorded'); ?></strong></div>
                    <div class="summary-item"><span>Transaction ID</span><strong><?php echo h($receipt['transaction_id'] ?? 'Not recorded'); ?></strong></div>
                </div>
                <?php if ((float) ($totals['balance'] ?? 0) > 0): ?><p class="danger-text">Do not treat this vendor as fully paid. Balance remains outstanding.</p><?php endif; ?>
            </section>

            <aside class="panel">
                <h2>Vendor Profile</h2>
                <p><strong>Name:</strong> <?php echo h($vendorName); ?></p>
                <p><strong>Phone:</strong> <?php echo h($receipt['user_phone'] ?? $receipt['sheet_phone'] ?? 'Not provided'); ?></p>
                <p><strong>Business:</strong> <?php echo h($receipt['business_name'] ?? 'Not provided'); ?></p>
                <p><strong>Business Nature:</strong> <?php echo h($receipt['business_nature'] ?? 'Not provided'); ?></p>
                <p><strong>Max Staff:</strong> <?php echo (int) $maxStaff; ?></p>
                <p><strong>Stalls:</strong> <?php echo h((string) ($receipt['assigned_stall_number'] ?? 'Not assigned')); ?></p>
                <?php if ($applicationId > 0): ?><a class="button button-secondary" href="<?php echo h(app_url('admin/application-view.php?id=' . $applicationId)); ?>">Open Application</a><?php endif; ?>
            </aside>
        </div>
    </main>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
