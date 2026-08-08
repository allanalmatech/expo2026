<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
$user = require_login();
$pdo = db();
ensure_vendor_access_schema($pdo);

$applicationId = (int) ($_GET['application_id'] ?? 0);
if (($user['role'] ?? '') === 'applicant') {
    $lookup = $pdo->prepare('SELECT id FROM applications WHERE user_id = ? LIMIT 1');
    $lookup->execute([(int) $user['id']]);
    $applicationId = (int) ($lookup->fetchColumn() ?: 0);
}

if ($applicationId <= 0) {
    set_flash('error', 'Application not found for tag printing.');
    redirect(($user['role'] ?? '') === 'admin' ? 'admin/applications.php' : 'applicant/tags.php');
}

$statement = $pdo->prepare(
    'SELECT a.*, u.full_name, u.phone, u.email, fr.business_name, fr.business_nature, fr.phone AS sheet_phone
     FROM applications a
     INNER JOIN users u ON u.id = a.user_id
     LEFT JOIN form_responses fr ON fr.id = a.form_response_id
     WHERE a.id = ? LIMIT 1'
);
$statement->execute([$applicationId]);
$application = $statement->fetch() ?: null;

if (!$application) {
    set_flash('error', 'Application not found for tag printing.');
    redirect(($user['role'] ?? '') === 'admin' ? 'admin/applications.php' : 'applicant/tags.php');
}

if (($user['role'] ?? '') !== 'admin' && (int) $application['user_id'] !== (int) $user['id']) {
    set_flash('error', 'You do not have access to those tags.');
    redirect('applicant/tags.php');
}

$tagsStatement = $pdo->prepare('SELECT * FROM attendant_tags WHERE application_id = ? AND is_active = 1 AND revoked_at IS NULL ORDER BY staff_name ASC, id ASC');
$tagsStatement->execute([$applicationId]);
$tags = $tagsStatement->fetchAll();

$pageTitle = 'Print Staff Tags';
$bodyClass = 'tag-print-page';
$extraHead = '<style media="print">@page { size: A4 portrait; margin: 0; }</style>';
$expoName = setting('event_name', APP_EVENT_NAME) ?: APP_EVENT_NAME;
$businessName = trim((string) ($application['business_name'] ?? '')) ?: trim((string) ($application['full_name'] ?? 'Vendor'));
$phone = trim((string) ($application['phone'] ?? $application['sheet_phone'] ?? '')) ?: 'No phone recorded';

require_once __DIR__ . '/includes/header.php';
?>
<main class="tag-print-shell">
    <div class="tag-print-actions no-print">
        <button class="button button-primary" type="button" onclick="window.print()">Print 4 Per A4</button>
        <a class="button button-ghost" href="<?php echo h(($user['role'] ?? '') === 'admin' ? app_url('admin/application-view.php?id=' . $applicationId) : app_url('applicant/tags.php')); ?>">Back</a>
    </div>

    <?php if (!$tags): ?>
        <section class="panel no-print"><div class="empty-state"><h1>No Active Tags</h1><p>Create active staff tags first, then print them here.</p></div></section>
    <?php endif; ?>

    <?php foreach (array_chunk($tags, 4) as $tagPage): ?>
        <section class="a4-tag-sheet" aria-label="Printable staff tags">
            <?php foreach ($tagPage as $tag): ?>
                <?php $tagUrl = tag_verification_url($tag); ?>
                <article class="a4-tag-card">
                    <header>
                        <strong><?php echo h($expoName); ?></strong>
                        <span>Vendor Staff QR Tag</span>
                    </header>
                    <div class="a4-tag-identity">
                        <h2><?php echo h($businessName); ?></h2>
                        <p><?php echo h($phone); ?></p>
                    </div>
                    <img src="<?php echo h(receipt_qr_image_url($tagUrl, 220)); ?>" alt="Staff verification QR code">
                    <footer>
                        <strong><?php echo h($tag['staff_name']); ?></strong>
                        <span><?php echo h($tag['staff_role'] ?: 'Stall Staff'); ?></span>
                    </footer>
                </article>
            <?php endforeach; ?>
        </section>
        <?php endforeach; ?>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
