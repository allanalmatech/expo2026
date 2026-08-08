<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
$admin = require_login('admin');
require_csrf();

$applicationId = (int) ($_POST['application_id'] ?? 0);
$field = (string) ($_POST['field'] ?? '');
$value = trim((string) ($_POST['value'] ?? ''));

$allowed = [
    'application_status' => ['Pending Review', 'Needs Correction', 'Approved', 'Rejected'],
    'payment_status' => ['Not Paid', 'Pending Verification', 'Payment Received', 'Payment Rejected'],
    'compliance_status' => ['Not Signed', 'Signed', 'Pending Review'],
];

if ($applicationId <= 0 || !isset($allowed[$field]) || !in_array($value, $allowed[$field], true)) {
    json_response(['ok' => false, 'message' => 'Invalid status update.'], 422);
}

db()->prepare('UPDATE applications SET ' . $field . ' = ?, updated_at = NOW() WHERE id = ?')->execute([$value, $applicationId]);
json_response(['ok' => true, 'message' => 'Status updated.']);
