<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

$providedToken = (string) ($_GET['token'] ?? $_POST['token'] ?? '');
$storedToken = setting('google_sheet_cron_token', '');

if ($storedToken === '' || $providedToken === '' || !hash_equals($storedToken, $providedToken)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Invalid sync token.']);
    exit;
}

if (setting('google_sheet_auto_sync_enabled', '0') !== '1') {
    http_response_code(409);
    echo json_encode(['ok' => false, 'message' => 'Google Sheet automatic sync is disabled.']);
    exit;
}

$summary = sync_google_sheet_responses(null);
$ok = empty($summary['errors']);
echo json_encode([
    'ok' => $ok,
    'message' => $ok ? 'Google Sheet synced successfully.' : 'Google Sheet sync completed with errors.',
    'summary' => $summary,
]);
