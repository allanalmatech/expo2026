<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
$user = require_login('applicant');
$active = 'dashboard';
$pageTitle = 'Applicant Dashboard';

$pdo = db();
$statement = $pdo->prepare(
    'SELECT a.*, fr.business_name, fr.business_nature, fr.stall_type, fr.electricity_needed, fr.number_of_stalls, fr.proof_of_payment_url
     FROM applications a
     LEFT JOIN form_responses fr ON fr.id = a.form_response_id
     WHERE a.user_id = ? LIMIT 1'
);
$statement->execute([(int) $user['id']]);
$application = $statement->fetch() ?: null;
$pricing = $application ? calculate_application_pricing($pdo, (int) $application['id']) : null;
$paymentTotals = $application ? payment_upload_totals($pdo, (int) $application['id'], $pricing) : null;

$direct = $pdo->prepare('SELECT m.*, u.full_name AS sender_name, m.is_read AS read_state FROM messages m LEFT JOIN users u ON u.id = m.sender_id WHERE m.receiver_id = ? ORDER BY m.created_at DESC LIMIT 4');
$direct->execute([(int) $user['id']]);
$messages = $direct->fetchAll();

$announcements = $pdo->prepare('SELECT m.*, u.full_name AS sender_name, ar.is_read AS read_state FROM announcement_recipients ar INNER JOIN messages m ON m.id = ar.announcement_id LEFT JOIN users u ON u.id = m.sender_id WHERE ar.user_id = ? ORDER BY m.created_at DESC LIMIT 4');
$announcements->execute([(int) $user['id']]);
$messages = array_merge($messages, $announcements->fetchAll());
usort($messages, function ($a, $b) {
    return strcmp((string) $b['created_at'], (string) $a['created_at']);
});
$messages = array_slice($messages, 0, 3);

$status = $application['application_status'] ?? 'Pending Review';
$progress = ['Pending Review' => 35, 'Needs Correction' => 45, 'Approved' => 80, 'Rejected' => 20][$status] ?? 35;
if (($application['compliance_status'] ?? '') === 'Signed' && ($application['assigned_stall_number'] ?? '') !== '') {
    $progress = 100;
}

require_once __DIR__ . '/../includes/header.php';
?>
<div class="app-shell">
    <?php require __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="app-main">
        <div class="page-header">
            <div>
                <h1>Applicant Dashboard</h1>
                <p>Welcome back, <?php echo h($user['full_name']); ?>. Track your stall progress below.</p>
            </div>
            <div class="identity-cell">
                <span class="avatar"><?php echo h(initials($user['full_name'])); ?></span>
                <div><strong><?php echo h($application['business_name'] ?? $user['full_name']); ?></strong><br><small>Applicant ID: #EXP26-<?php echo str_pad((string) $user['id'], 3, '0', STR_PAD_LEFT); ?></small></div>
            </div>
        </div>

        <div class="dashboard-grid">
            <section class="panel">
                <div class="page-header">
                    <h2>Application Summary</h2>
                    <?php echo badge($application['application_status'] ?? 'Pending Review'); ?>
                </div>
                <div class="summary-grid">
                    <div class="summary-item"><span>Business Name</span><strong><?php echo h($application['business_name'] ?? 'Not provided'); ?></strong></div>
                    <div class="summary-item"><span>Stall Type</span><strong><?php echo h($application['stall_type'] ?? 'Not selected'); ?></strong></div>
                    <div class="summary-item"><span>Electricity Required</span><strong><?php echo h($application['electricity_needed'] ?? 'Not provided'); ?></strong></div>
                    <div class="summary-item"><span>Stall Assignment</span><strong><?php echo h($application['assigned_stall_number'] ?: 'Awaiting approval'); ?></strong></div>
                    <div class="summary-item"><span>Amount Due</span><strong><?php echo h(ugx_money((float) ($paymentTotals['total_due'] ?? ($pricing['total'] ?? 0)))); ?></strong></div>
                    <div class="summary-item"><span>Verified Paid</span><strong><?php echo h(ugx_money((float) ($paymentTotals['verified'] ?? 0))); ?></strong></div>
                    <div class="summary-item"><span>Balance</span><strong><?php echo h(ugx_money((float) ($paymentTotals['balance'] ?? 0))); ?></strong></div>
                </div>
                <div class="divider"></div>
                <span class="field-label">Application Progress</span>
                <div class="progress"><span style="width: <?php echo (int) $progress; ?>%"></span></div>
                <p class="help-text">Current step: <?php echo h($application['application_status'] ?? 'Pending Review'); ?>.</p>
                <?php if (!empty($application['assigned_stall_number'])): ?>
                    <a class="button button-secondary" href="<?php echo h(app_url('applicant/stall.php')); ?>">View Stall Location</a>
                <?php endif; ?>
            </section>

            <aside class="panel dark-card">
                <h2>Payment Status</h2>
                <p><?php echo badge($application['payment_status'] ?? 'Not Paid'); ?></p>
                <?php if (!empty($application['proof_of_payment_url'])): ?>
                    <p class="help-text">Imported proof from Google Form:</p>
                    <a class="button button-ghost" href="<?php echo h($application['proof_of_payment_url']); ?>" target="_blank" rel="noopener">View Payment Proof</a>
                <?php else: ?>
                    <p class="help-text">Upload or replace your payment document for committee verification.</p>
                <?php endif; ?>
                <a class="button button-primary" href="<?php echo h(app_url('applicant/payment.php')); ?>">Update Payment Details</a>
            </aside>
        </div>

        <div class="dashboard-grid" style="margin-top: 22px;">
            <section class="panel">
                <div class="page-header">
                    <h2>Committee Messages</h2>
                    <a class="link-strong" href="<?php echo h(app_url('applicant/messages.php')); ?>">View All</a>
                </div>
                <div class="stack">
                    <?php foreach ($messages as $message): ?>
                        <article class="message-card <?php echo !empty($message['read_state']) ? 'is-read' : ''; ?>">
                            <div class="page-header">
                                <strong><?php echo h($message['title']); ?></strong>
                                <small><?php echo h(format_date($message['created_at'])); ?></small>
                            </div>
                            <p><?php echo h($message['body']); ?></p>
                        </article>
                    <?php endforeach; ?>
                    <?php if (!$messages): ?>
                        <div class="empty-state">No committee messages yet.</div>
                    <?php endif; ?>
                </div>
            </section>

            <aside class="panel">
                <h2>Quick Actions</h2>
                <div class="stack">
                    <a class="button button-secondary" href="<?php echo h(app_url('applicant/profile.php')); ?>">View Imported Details</a>
                    <a class="button button-secondary" href="<?php echo h(app_url('applicant/receipts.php')); ?>">Print Receipts</a>
                    <a class="button button-primary" href="<?php echo h(app_url('applicant/tags.php')); ?>">Generate Staff Tags</a>
                    <a class="button button-ghost" href="<?php echo h(app_url('applicant/compliance.php')); ?>">Review Compliance Rules</a>
                    <a class="button button-primary" href="<?php echo h(app_url('applicant/stall.php')); ?>">View Stall Allocation</a>
                </div>
            </aside>
        </div>
    </main>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
