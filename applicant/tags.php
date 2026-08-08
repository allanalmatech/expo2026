<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
$user = require_login('applicant');
$active = 'tags';
$pageTitle = 'Staff Tags';
$pdo = db();
ensure_vendor_access_schema($pdo);

$statement = $pdo->prepare(
    'SELECT a.*, fr.business_name, fr.business_nature, fr.business_description, fr.number_of_stalls, fr.max_staff
     FROM applications a
     LEFT JOIN form_responses fr ON fr.id = a.form_response_id
     WHERE a.user_id = ? LIMIT 1'
);
$statement->execute([(int) $user['id']]);
$application = $statement->fetch() ?: null;

if (!$application) {
    set_flash('error', 'No application record was found for your account.');
    redirect('applicant/dashboard.php');
}

$maxStaff = max_staff_for_application($application);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'create_tag') {
        $activeCount = active_attendant_count($pdo, (int) $application['id']);
        $staffName = trim((string) ($_POST['staff_name'] ?? ''));
        $staffRole = trim((string) ($_POST['staff_role'] ?? ''));
        if ($staffName === '') {
            set_flash('error', 'Enter the staff member name.');
        } elseif ($activeCount >= $maxStaff) {
            set_flash('error', 'You have reached your maximum staff cap of ' . $maxStaff . '.');
        } else {
            $insert = $pdo->prepare('INSERT INTO attendant_tags (user_id, application_id, tag_token, staff_name, staff_role, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 1, NOW(), NOW())');
            $insert->execute([(int) $user['id'], (int) $application['id'], bin2hex(random_bytes(32)), $staffName, $staffRole ?: null]);
            set_flash('success', 'Staff QR tag generated.');
        }
        redirect('applicant/tags.php');
    }

    if ($action === 'revoke_tag') {
        $tagId = (int) ($_POST['tag_id'] ?? 0);
        $pdo->prepare('UPDATE attendant_tags SET is_active = 0, revoked_at = NOW(), updated_at = NOW() WHERE id = ? AND user_id = ?')->execute([$tagId, (int) $user['id']]);
        set_flash('success', 'Staff tag revoked.');
        redirect('applicant/tags.php');
    }
}

$tagsStatement = $pdo->prepare('SELECT * FROM attendant_tags WHERE application_id = ? ORDER BY is_active DESC, created_at DESC');
$tagsStatement->execute([(int) $application['id']]);
$tags = $tagsStatement->fetchAll();
$activeCount = active_attendant_count($pdo, (int) $application['id']);
$paymentTotals = payment_upload_totals($pdo, (int) $application['id']);

require_once __DIR__ . '/../includes/header.php';
?>
<div class="app-shell">
    <?php require __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="app-main">
        <div class="page-header">
            <div>
                <h1>Staff QR Tags</h1>
                <p>Generate QR tags only for staff who will work inside your stall.</p>
            </div>
            <div class="header-actions">
                <a class="button button-secondary" href="<?php echo h(app_url('tags-print.php')); ?>" target="_blank" rel="noopener">Print Tags 4 Per A4</a>
                <?php echo badge($activeCount . '/' . $maxStaff . ' Staff Used'); ?>
            </div>
        </div>

        <div class="dashboard-grid">
            <section class="panel dark-card">
                <h2>Your Constraints</h2>
                <div class="summary-grid">
                    <div class="summary-item"><span>Business</span><strong><?php echo h($application['business_name'] ?? 'Not provided'); ?></strong></div>
                    <div class="summary-item"><span>Business Nature</span><strong><?php echo h($application['business_nature'] ?? 'Not provided'); ?></strong></div>
                    <div class="summary-item"><span>Max Staff</span><strong><?php echo (int) $maxStaff; ?></strong></div>
                    <div class="summary-item"><span>Active Tags</span><strong><?php echo (int) $activeCount; ?></strong></div>
                    <div class="summary-item"><span>Verified Paid</span><strong><?php echo h(ugx_money((float) ($paymentTotals['verified'] ?? 0))); ?></strong></div>
                    <div class="summary-item"><span>Balance</span><strong><?php echo h(ugx_money((float) ($paymentTotals['balance'] ?? 0))); ?></strong></div>
                </div>
                <?php if ((float) ($paymentTotals['balance'] ?? 0) > 0): ?><p class="danger-text">A balance is still outstanding and will be visible to admin when your tag is scanned.</p><?php endif; ?>
            </section>

            <aside class="panel">
                <h2>Create Staff Tag</h2>
                <?php if ($activeCount >= $maxStaff): ?>
                    <div class="empty-state"><h3>Staff Cap Reached</h3><p>Revoke an unused tag or contact admin if your cap is incorrect.</p></div>
                <?php else: ?>
                    <form method="post" class="form-grid">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="create_tag">
                        <div class="field"><label>Staff Name</label><input name="staff_name" maxlength="190" required></div>
                        <div class="field"><label>Role</label><input name="staff_role" maxlength="120" placeholder="Owner, attendant, cashier"></div>
                        <button class="button button-primary" type="submit">Generate QR Tag</button>
                    </form>
                <?php endif; ?>
            </aside>
        </div>

        <section class="panel" style="margin-top: 22px;">
            <h2>Generated Tags</h2>
            <div class="tag-grid">
                <?php foreach ($tags as $tag): ?>
                    <?php $tagUrl = tag_verification_url($tag); ?>
                    <article class="tag-card <?php echo (int) $tag['is_active'] === 1 ? '' : 'is-revoked'; ?>">
                        <div>
                            <strong><?php echo h($tag['staff_name']); ?></strong>
                            <span><?php echo h($tag['staff_role'] ?: 'Stall staff'); ?></span>
                            <?php echo badge((int) $tag['is_active'] === 1 ? 'Active' : 'Revoked'); ?>
                        </div>
                        <img src="<?php echo h(receipt_qr_image_url($tagUrl, 170)); ?>" alt="Staff verification QR code">
                        <small>Scan verifies identity, business nature, payment balance, and staff cap.</small>
                        <?php if ((int) $tag['is_active'] === 1): ?>
                            <form method="post" data-confirm="Revoke this staff tag?">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="action" value="revoke_tag">
                                <input type="hidden" name="tag_id" value="<?php echo (int) $tag['id']; ?>">
                                <button class="button button-ghost" type="submit">Revoke</button>
                            </form>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
                <?php if (!$tags): ?><div class="empty-state"><h3>No Staff Tags Yet</h3><p>Create tags for the staff who should be allowed through verification points.</p></div><?php endif; ?>
            </div>
        </section>
    </main>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
