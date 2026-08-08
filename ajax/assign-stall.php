<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
$admin = require_login('admin');
require_csrf();

$stallId = (int) ($_POST['stall_id'] ?? 0);
$applicationId = (int) ($_POST['application_id'] ?? 0);

$pdo = db();
ensure_layout_designer_schema((int) $admin['id']);
$activeLayout = sync_active_layout_stalls($pdo);
if (!$activeLayout) {
    json_response(['ok' => false, 'message' => 'Save and activate a layout before assigning tent slots.'], 422);
}
$stall = fetch_layout_stall($pdo, (int) $activeLayout['id'], $stallId);

$applicationStatement = $pdo->prepare('SELECT * FROM applications WHERE id = ? LIMIT 1');
$applicationStatement->execute([$applicationId]);
$application = $applicationStatement->fetch();

if (!$stall || !$application) {
    json_response(['ok' => false, 'message' => 'Select a valid saved-layout tent slot and application.'], 422);
}

if ((int) $stall['is_allocated'] === 1 && (int) $stall['allocated_to_user_id'] !== (int) $application['user_id']) {
    json_response(['ok' => false, 'message' => 'This stall is already allocated.'], 409);
}

try {
    $pdo->beginTransaction();
    $pdo->prepare('UPDATE stalls SET is_allocated = 1, allocated_to_user_id = ?, updated_at = NOW() WHERE id = ?')->execute([(int) $application['user_id'], $stallId]);
    refresh_application_stall_assignment($pdo, (int) $application['user_id']);
    $pdo->commit();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('AJAX layout stall assignment failed: ' . $exception->getMessage());
    json_response(['ok' => false, 'message' => 'The tent slot could not be assigned.'], 500);
}

json_response(['ok' => true, 'message' => 'Saved-layout tent slot assigned.']);
