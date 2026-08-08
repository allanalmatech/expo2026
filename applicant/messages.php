<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
$user = require_login('applicant');
$active = 'messages';
$pageTitle = 'Messages';
$pdo = db();

$direct = $pdo->prepare('SELECT m.*, u.full_name AS sender_name, m.is_read AS read_state FROM messages m LEFT JOIN users u ON u.id = m.sender_id WHERE m.receiver_id = ?');
$direct->execute([(int) $user['id']]);
$messages = $direct->fetchAll();

$announcements = $pdo->prepare('SELECT m.*, u.full_name AS sender_name, ar.is_read AS read_state FROM announcement_recipients ar INNER JOIN messages m ON m.id = ar.announcement_id LEFT JOIN users u ON u.id = m.sender_id WHERE ar.user_id = ?');
$announcements->execute([(int) $user['id']]);
$messages = array_merge($messages, $announcements->fetchAll());
usort($messages, function ($a, $b) {
    return strcmp((string) $b['created_at'], (string) $a['created_at']);
});

require_once __DIR__ . '/../includes/header.php';
?>
<div class="app-shell">
    <?php require __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="app-main">
        <div class="page-header">
            <div>
                <h1>Committee Messages</h1>
                <p>Read official direct messages and announcements from the organizing committee.</p>
            </div>
        </div>

        <div class="stack">
            <?php foreach ($messages as $message): ?>
                <article class="message-card <?php echo !empty($message['read_state']) ? 'is-read' : ''; ?>">
                    <div class="page-header">
                        <div>
                            <h2><?php echo h($message['title']); ?></h2>
                            <div class="meta-row"><span><?php echo h($message['message_type']); ?></span><span><?php echo h(format_date($message['created_at'])); ?></span></div>
                        </div>
                        <?php if (empty($message['read_state'])): ?>
                            <button class="button button-ghost" type="button" data-mark-read data-message-id="<?php echo (int) $message['id']; ?>">Mark Read</button>
                        <?php endif; ?>
                    </div>
                    <p><?php echo nl2br(h($message['body'])); ?></p>
                </article>
            <?php endforeach; ?>
            <?php if (!$messages): ?><div class="panel empty-state">No messages yet.</div><?php endif; ?>
        </div>
    </main>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
