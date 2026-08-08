<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
$admin = require_login('admin');
require_csrf();

$receiverId = (int) ($_POST['receiver_id'] ?? 0);
$title = trim((string) ($_POST['title'] ?? ''));
$body = trim((string) ($_POST['body'] ?? ''));

if ($receiverId <= 0 || $title === '' || $body === '') {
    json_response(['ok' => false, 'message' => 'Select an applicant and enter a message.'], 422);
}

db()->prepare('INSERT INTO messages (sender_id, receiver_id, title, body, message_type, is_read, created_at) VALUES (?, ?, ?, ?, "direct", 0, NOW())')
    ->execute([(int) $admin['id'], $receiverId, $title, $body]);

json_response(['ok' => true, 'message' => 'Message sent.']);
