<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
$admin = require_login('admin');
$active = 'messages';
$pageTitle = 'Messages';
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $mode = (string) ($_POST['mode'] ?? 'direct');
    $title = trim((string) ($_POST['title'] ?? ''));
    $body = trim((string) ($_POST['body'] ?? ''));

    if ($title === '' || $body === '') {
        set_flash('error', 'Message title and body are required.');
        redirect('admin/messages.php');
    }

    if ($mode === 'direct') {
        $receiverId = (int) ($_POST['receiver_id'] ?? 0);
        if ($receiverId <= 0) {
            set_flash('error', 'Please select an applicant.');
            redirect('admin/messages.php');
        }
        $statement = $pdo->prepare('INSERT INTO messages (sender_id, receiver_id, title, body, message_type, is_read, created_at) VALUES (?, ?, ?, ?, "direct", 0, NOW())');
        $statement->execute([(int) $admin['id'], $receiverId, $title, $body]);
        set_flash('success', 'Direct message sent.');
        redirect('admin/messages.php');
    }

    $group = (string) ($_POST['group'] ?? 'all');
    $conditions = ['u.role = "applicant"'];
    if ($group === 'approved') {
        $conditions[] = 'a.application_status = "Approved"';
    } elseif ($group === 'unpaid') {
        $conditions[] = 'a.payment_status <> "Payment Received"';
    } elseif ($group === 'students') {
        $conditions[] = 'LOWER(COALESCE(fr.student_status, fr.applicant_type, "")) LIKE "%student%" AND LOWER(COALESCE(fr.student_status, fr.applicant_type, "")) NOT LIKE "%non%"';
    } elseif ($group === 'non_students') {
        $conditions[] = 'LOWER(COALESCE(fr.student_status, fr.applicant_type, "")) LIKE "%non%"';
    } elseif ($group === 'food_vendors') {
        $conditions[] = 'LOWER(COALESCE(fr.business_nature, "")) LIKE "%food%"';
    } elseif ($group === 'pending') {
        $conditions[] = 'a.application_status = "Pending Review"';
    }

    $sql = 'SELECT DISTINCT u.id FROM users u LEFT JOIN applications a ON a.user_id = u.id LEFT JOIN form_responses fr ON fr.id = a.form_response_id WHERE ' . implode(' AND ', $conditions);
    $recipients = array_column($pdo->query($sql)->fetchAll(), 'id');
    if (!$recipients) {
        set_flash('error', 'No applicants match the selected announcement group.');
        redirect('admin/messages.php');
    }

    $pdo->beginTransaction();
    $pdo->prepare('INSERT INTO messages (sender_id, receiver_id, title, body, message_type, is_read, created_at) VALUES (?, NULL, ?, ?, "announcement", 0, NOW())')
        ->execute([(int) $admin['id'], $title, $body]);
    $announcementId = (int) $pdo->lastInsertId();
    $recipientInsert = $pdo->prepare('INSERT INTO announcement_recipients (announcement_id, user_id, is_read) VALUES (?, ?, 0)');
    foreach ($recipients as $recipientId) {
        $recipientInsert->execute([$announcementId, (int) $recipientId]);
    }
    $pdo->commit();
    set_flash('success', 'Announcement sent to ' . count($recipients) . ' applicant(s).');
    redirect('admin/messages.php');
}

$applicants = $pdo->query('SELECT id, full_name, email, phone FROM users WHERE role = "applicant" ORDER BY full_name ASC')->fetchAll();
$messages = $pdo->query('SELECT m.*, u.full_name AS sender_name, r.full_name AS receiver_name FROM messages m LEFT JOIN users u ON u.id = m.sender_id LEFT JOIN users r ON r.id = m.receiver_id ORDER BY m.created_at DESC LIMIT 40')->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>
<div class="app-shell">
    <?php require __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="app-main">
        <div class="page-header">
            <div>
                <h1>Messaging</h1>
                <p>Send direct messages or announcements to applicant groups.</p>
            </div>
            <div class="header-actions">
                <button class="button button-secondary" type="button" data-proof-modal-open="direct-message-modal">Send Direct Message</button>
                <button class="button button-primary" type="button" data-proof-modal-open="bulk-announcement-modal">Bulk Announcement</button>
            </div>
        </div>

        <div class="proof-modal" id="direct-message-modal" hidden data-proof-modal>
            <div class="proof-modal-backdrop" data-proof-modal-close></div>
            <section class="proof-modal-panel" role="dialog" aria-modal="true" aria-labelledby="direct-message-modal-title">
                <div class="proof-modal-header">
                    <div>
                        <h2 id="direct-message-modal-title">Send Direct Message</h2>
                        <p>Send a message to one applicant.</p>
                    </div>
                    <button class="icon-button" type="button" data-proof-modal-close aria-label="Close direct message form">x</button>
                </div>
                <form method="post" class="form-grid">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="mode" value="direct">
                    <div class="field"><label>Applicant</label><select name="receiver_id" required><option value="">Select applicant</option><?php foreach ($applicants as $applicant): ?><option value="<?php echo (int) $applicant['id']; ?>"><?php echo h($applicant['full_name'] . ' - ' . ($applicant['email'] ?: $applicant['phone'])); ?></option><?php endforeach; ?></select></div>
                    <div class="field"><label>Title</label><input name="title" required></div>
                    <div class="field"><label>Message</label><textarea name="body" required></textarea></div>
                    <div class="proof-modal-actions"><button class="button button-primary" type="submit">Send Message</button></div>
                </form>
            </section>
        </div>

        <div class="proof-modal" id="bulk-announcement-modal" hidden data-proof-modal>
            <div class="proof-modal-backdrop" data-proof-modal-close></div>
            <section class="proof-modal-panel" role="dialog" aria-modal="true" aria-labelledby="bulk-announcement-modal-title">
                <div class="proof-modal-header">
                    <div>
                        <h2 id="bulk-announcement-modal-title">Bulk Announcement</h2>
                        <p>Send one announcement to an applicant group.</p>
                    </div>
                    <button class="icon-button" type="button" data-proof-modal-close aria-label="Close announcement form">x</button>
                </div>
                <form method="post" class="form-grid" data-confirm="Send this announcement to the selected group?">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="mode" value="announcement">
                    <div class="field"><label>Recipient Group</label><select name="group"><option value="all">All applicants</option><option value="approved">Approved applicants</option><option value="unpaid">Unpaid applicants</option><option value="students">Students</option><option value="non_students">Non-students</option><option value="food_vendors">Food vendors</option><option value="pending">Pending applicants</option></select></div>
                    <div class="field"><label>Title</label><input name="title" required></div>
                    <div class="field"><label>Announcement</label><textarea name="body" required></textarea></div>
                    <div class="proof-modal-actions"><button class="button button-primary" type="submit">Send Announcement</button></div>
                </form>
            </section>
        </div>

        <section class="panel" style="margin-top: 22px;">
            <h2>Recent Messages</h2>
            <div class="stack">
                <?php foreach ($messages as $message): ?>
                    <article class="message-card">
                        <div class="page-header">
                            <div>
                                <strong><?php echo h($message['title']); ?></strong>
                                <div class="meta-row"><span><?php echo h($message['message_type']); ?></span><span>To: <?php echo h($message['receiver_name'] ?? 'Announcement group'); ?></span><span><?php echo h(format_date($message['created_at'])); ?></span></div>
                            </div>
                        </div>
                        <p><?php echo nl2br(h($message['body'])); ?></p>
                    </article>
                <?php endforeach; ?>
                <?php if (!$messages): ?><div class="empty-state">No messages sent yet.</div><?php endif; ?>
            </div>
        </section>
    </main>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
