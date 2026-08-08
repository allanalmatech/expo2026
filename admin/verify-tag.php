<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
$admin = require_login('admin');
$active = 'verify_tag';
$pageTitle = 'Verify Staff Tag';
$pdo = db();
ensure_vendor_access_schema($pdo);

$token = trim((string) ($_GET['token'] ?? ''));
$tag = $token !== '' ? fetch_attendant_tag($pdo, $token) : null;

if ($tag && (int) $tag['is_active'] === 1) {
    $pdo->prepare('UPDATE attendant_tags SET verified_count = verified_count + 1, last_verified_at = NOW(), updated_at = NOW() WHERE id = ?')->execute([(int) $tag['id']]);
    $tag = fetch_attendant_tag($pdo, $token) ?: $tag;
}

require_once __DIR__ . '/../includes/header.php';
?>
<div class="app-shell">
    <?php require __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="app-main">
        <?php if (!$tag): ?>
            <section class="panel">
                <div class="empty-state"><h1>Invalid Staff QR</h1><p>This QR code does not match any generated staff tag.</p></div>
            </section>
        <?php else: ?>
            <?php
            $totals = payment_upload_totals($pdo, (int) $tag['application_id']);
            $maxStaff = max_staff_for_application($tag);
            $activeCount = active_attendant_count($pdo, (int) $tag['application_id']);
            $hasBalance = (float) ($totals['balance'] ?? 0) > 0;
            $overCap = $activeCount > $maxStaff;
            $isRevoked = (int) $tag['is_active'] !== 1;
            ?>
            <div class="page-header">
                <div>
                    <h1><?php echo $isRevoked ? 'Staff Tag Revoked' : 'Staff Tag Verified'; ?></h1>
                    <p><?php echo h($tag['staff_name']); ?> is registered under <?php echo h($tag['business_name'] ?? $tag['account_name']); ?>.</p>
                </div>
                <?php echo badge($isRevoked ? 'Do Not Admit' : ($hasBalance || $overCap ? 'Review Required' : 'Admit')); ?>
            </div>

            <?php if ($isRevoked): ?><div class="notice error">This tag has been revoked. Do not admit this person using this QR code.</div><?php endif; ?>
            <?php if ($hasBalance): ?><div class="notice error">Outstanding balance: <?php echo h(ugx_money((float) $totals['balance'])); ?>.</div><?php endif; ?>
            <?php if ($overCap): ?><div class="notice error">Active staff tags exceed the max staff cap. Investigate before entry.</div><?php endif; ?>

            <div class="dashboard-grid">
                <section class="panel dark-card">
                    <h2>Identity and Payment</h2>
                    <div class="summary-grid">
                        <div class="summary-item"><span>Staff Name</span><strong><?php echo h($tag['staff_name']); ?></strong></div>
                        <div class="summary-item"><span>Role</span><strong><?php echo h($tag['staff_role'] ?: 'Stall staff'); ?></strong></div>
                        <div class="summary-item"><span>Vendor</span><strong><?php echo h($tag['account_name']); ?></strong></div>
                        <div class="summary-item"><span>Phone</span><strong><?php echo h($tag['phone'] ?? 'Not provided'); ?></strong></div>
                        <div class="summary-item"><span>Verified Paid</span><strong><?php echo h(ugx_money((float) ($totals['verified'] ?? 0))); ?></strong></div>
                        <div class="summary-item"><span>Balance</span><strong><?php echo h(ugx_money((float) ($totals['balance'] ?? 0))); ?></strong></div>
                    </div>
                </section>

                <aside class="panel">
                    <h2>Business Security Profile</h2>
                    <p><strong>Business:</strong> <?php echo h($tag['business_name'] ?? 'Not provided'); ?></p>
                    <p><strong>Nature:</strong> <?php echo h($tag['business_nature'] ?? 'Not provided'); ?></p>
                    <p><strong>Assigned Stall:</strong> <?php echo h($tag['assigned_stall_number'] ?? 'Not assigned'); ?></p>
                    <p><strong>Max Staff:</strong> <?php echo (int) $maxStaff; ?></p>
                    <p><strong>Active Tags:</strong> <?php echo (int) $activeCount; ?></p>
                    <p><strong>Times Scanned:</strong> <?php echo (int) $tag['verified_count']; ?></p>
                    <p><strong>Last Scan:</strong> <?php echo h(format_date($tag['last_verified_at'])); ?></p>
                    <a class="button button-secondary" href="<?php echo h(app_url('admin/application-view.php?id=' . (int) $tag['application_id'])); ?>">Open Application</a>
                </aside>
            </div>
        <?php endif; ?>
    </main>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
