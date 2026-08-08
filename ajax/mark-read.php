<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
$user = require_login();
require_csrf();

$messageId = (int) ($_POST['message_id'] ?? 0);
if ($messageId <= 0) {
    json_response(['ok' => false, 'message' => 'Invalid message.'], 422);
}

$pdo = db();
if ($user['role'] === 'applicant') {
    $direct = $pdo->prepare('UPDATE messages SET is_read = 1 WHERE id = ? AND receiver_id = ?');
    $direct->execute([$messageId, (int) $user['id']]);
    $announcement = $pdo->prepare('UPDATE announcement_recipients SET is_read = 1, read_at = NOW() WHERE announcement_id = ? AND user_id = ?');
    $announcement->execute([$messageId, (int) $user['id']]);
    json_response(['ok' => true, 'message' => 'Message marked as read.']);
}

$pdo->prepare('UPDATE messages SET is_read = 1 WHERE id = ?')->execute([$messageId]);
json_response(['ok' => true, 'message' => 'Message marked as read.']);
