<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
$user = require_login('applicant');
$active = 'receipts';
$pageTitle = 'Receipts';
$pdo = db();
ensure_vendor_access_schema($pdo);

$applicationStatement = $pdo->prepare('SELECT id, form_response_id FROM applications WHERE user_id = ? LIMIT 1');
$applicationStatement->execute([(int) $user['id']]);
$application = $applicationStatement->fetch() ?: null;
if ($application && (int) ($application['form_response_id'] ?? 0) > 0) {
    sync_sheet_payment_receipt($pdo, (int) $application['form_response_id']);
}

$receipts = [];
if ($application) {
    $statement = $pdo->prepare('SELECT * FROM payment_receipts WHERE user_id = ? OR application_id = ? OR form_response_id = ? ORDER BY paid_at DESC, created_at DESC');
    $statement->execute([(int) $user['id'], (int) $application['id'], (int) ($application['form_response_id'] ?? 0)]);
    $receipts = $statement->fetchAll();
}

require_once __DIR__ . '/../includes/header.php';
?>
<div class="app-shell">
    <?php require __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="app-main">
        <div class="page-header">
            <div>
                <h1>Payment Receipts</h1>
                <p>Print 57mm receipts for payments already confirmed by the committee.</p>
            </div>
        </div>
        <section class="panel">
            <div class="table-scroll">
                <table>
                    <thead><tr><th>Receipt</th><th>Paid</th><th>Balance</th><th>Timestamp</th><th>Action</th></tr></thead>
                    <tbody>
                    <?php foreach ($receipts as $receipt): ?>
                        <tr>
                            <td data-label="Receipt"><strong><?php echo h($receipt['receipt_reference']); ?></strong><br><small><?php echo h(ucfirst((string) $receipt['source_type'])); ?> / <?php echo h($receipt['payment_description'] ?? 'Payment'); ?></small></td>
                            <td data-label="Paid"><?php echo h(ugx_money((float) $receipt['paid_amount'])); ?></td>
                            <td data-label="Balance"><?php echo h(ugx_money((float) $receipt['balance_amount'])); ?></td>
                            <td data-label="Timestamp"><?php echo h(format_date($receipt['paid_at'])); ?></td>
                            <td data-label="Action"><a class="button button-secondary" href="<?php echo h(app_url('receipt.php?ref=' . rawurlencode((string) $receipt['receipt_reference']))); ?>" target="_blank" rel="noopener">Print 57mm</a></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$receipts): ?><tr><td colspan="5" class="empty-state">No confirmed payment receipts are available yet.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
