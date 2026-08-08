<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
$admin = require_login('admin');
require_csrf();

$uploadId = (int) ($_POST['upload_id'] ?? 0);
$status = (string) ($_POST['status'] ?? '');
$comment = trim((string) ($_POST['admin_comment'] ?? ''));

if ($uploadId <= 0 || !in_array($status, ['Verified', 'Rejected'], true)) {
    json_response(['ok' => false, 'message' => 'Invalid payment verification request.'], 422);
}

$pdo = db();
ensure_payment_upload_schema();
$statement = $pdo->prepare('SELECT * FROM payment_uploads WHERE id = ? LIMIT 1');
$statement->execute([$uploadId]);
$upload = $statement->fetch();
if (!$upload) {
    json_response(['ok' => false, 'message' => 'Payment upload not found.'], 404);
}

$pdo->prepare('UPDATE payment_uploads SET verification_status = ?, verified_by = ?, verified_at = NOW(), admin_comment = ? WHERE id = ?')
    ->execute([$status, (int) $admin['id'], $comment, $uploadId]);
refresh_application_payment_status_from_uploads($pdo, (int) $upload['application_id']);

json_response(['ok' => true, 'message' => 'Payment verification updated.']);
