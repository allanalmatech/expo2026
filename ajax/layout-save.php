<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
$admin = require_login('admin');
set_exception_handler(function (Throwable $exception): void {
    error_log('Uncaught layout save error: ' . $exception->getMessage());
    json_response([
        'ok' => false,
        'message' => 'The layout could not be saved because a required database structure was unavailable. The system attempted to repair it; refresh the page and try again.'
    ], 500);
});
require_csrf();

$payload = json_decode((string) ($_POST['payload'] ?? ''), true);
if (!is_array($payload)) {
    json_response(['ok' => false, 'message' => 'Invalid layout payload.'], 422);
}

$pdo = db();
ensure_layout_designer_schema((int) $admin['id']);
$layoutId = (int) ($payload['layout_id'] ?? 0);
$layoutName = trim((string) ($payload['name'] ?? ''));
$isActive = !empty($payload['is_active']) ? 1 : 0;
$elements = $payload['elements'] ?? [];

if ($layoutName === '') {
    json_response(['ok' => false, 'message' => 'Layout name is required.'], 422);
}
if (!is_array($elements)) {
    json_response(['ok' => false, 'message' => 'Layout elements are invalid.'], 422);
}

$allowedTypes = ['tent_50', 'tent_100', 'stage', 'reg_desk', 'waste_point', 'toilet_m', 'toilet_f', 'walkway', 'label'];
$allowedCategories = ['student', 'sme', 'ngo_government', 'corporate', 'sponsor', 'food_beverage'];
$validZones = array_column($pdo->query('SELECT zone_key FROM venue_layout_zones')->fetchAll(), 'zone_key');

$rules = layout_arrangement_rules_by_tent($pdo);

$validated = [];
$newTentGroups = [];

foreach ($elements as $index => $element) {
    if (!is_array($element)) {
        json_response(['ok' => false, 'message' => 'Element #' . ($index + 1) . ' is invalid.'], 422);
    }

    $type = (string) ($element['element_type'] ?? '');
    if (!in_array($type, $allowedTypes, true)) {
        json_response(['ok' => false, 'message' => 'Element #' . ($index + 1) . ' has an invalid type.'], 422);
    }

    $isTent = in_array($type, ['tent_50', 'tent_100'], true);
    $tentType = $isTent ? ($type === 'tent_50' ? '50' : '100') : null;
    $stallCount = null;
    $tentGroup = null;
    $category = null;
    $uZone = trim((string) ($element['u_zone'] ?? ''));

    if ($uZone !== '' && !in_array($uZone, $validZones, true)) {
        json_response(['ok' => false, 'message' => 'Element #' . ($index + 1) . ' uses an invalid U-layout zone.'], 422);
    }

    if ($isTent) {
        $tentGroup = strtoupper(trim((string) ($element['tent_group_code'] ?? '')));
        $stallCount = (int) ($element['stall_count'] ?? 0);
        $category = (string) ($element['category'] ?? 'sme');

        if (!isset($rules[$tentType][$stallCount])) {
            json_response(['ok' => false, 'message' => 'Tent ' . ($tentGroup ?: '#' . ($index + 1)) . ' has an invalid stall count for a ' . $tentType . '-seater tent.'], 422);
        }
        if (!in_array($category, $allowedCategories, true)) {
            json_response(['ok' => false, 'message' => 'Tent ' . ($tentGroup ?: '#' . ($index + 1)) . ' has an invalid category.'], 422);
        }
        if ($tentGroup !== '') {
            $newTentGroups[$tentGroup] = ['tent_type' => $tentType, 'stall_count' => $stallCount, 'category' => $category, 'u_zone' => $uZone ?: null];
        }
    }

    $rotation = (int) ($element['rotation'] ?? 0);
    $rotation = in_array($rotation, [0, 90, 180, 270], true) ? $rotation : 0;

    $validated[] = [
        'element_type' => $type,
        'tent_group_code' => $tentGroup,
        'tent_type' => $tentType,
        'stall_count' => $stallCount,
        'category' => $category,
        'u_zone' => $uZone ?: null,
        'x' => max(0, min(5000, (int) ($element['x'] ?? 0))),
        'y' => max(0, min(5000, (int) ($element['y'] ?? 0))),
        'width' => max(20, min(2000, (int) ($element['width'] ?? 100))),
        'height' => max(20, min(2000, (int) ($element['height'] ?? 80))),
        'rotation' => $rotation,
        'label' => trim((string) ($element['label'] ?? '')),
        'z_index' => max(1, min(10000, (int) ($element['z_index'] ?? ($index + 1)))),
    ];
}

try {
    $pdo->beginTransaction();

    $oldGroups = [];
    if ($layoutId > 0) {
        $oldStatement = $pdo->prepare('SELECT DISTINCT tent_group_code FROM layout_elements WHERE layout_id = ? AND tent_group_code IS NOT NULL AND tent_group_code <> ""');
        $oldStatement->execute([$layoutId]);
        $oldGroups = array_column($oldStatement->fetchAll(), 'tent_group_code');
    }

    if ($isActive) {
        $pdo->exec('UPDATE venue_layouts SET is_active = 0');
    }

    if ($layoutId <= 0) {
        $nameCheck = $pdo->prepare('SELECT id FROM venue_layouts WHERE name = ? LIMIT 1');
        $nameCheck->execute([$layoutName]);
        $existingNamedLayout = $nameCheck->fetchColumn();
        if ($existingNamedLayout) {
            $layoutId = (int) $existingNamedLayout;
        }
    }

    if ($layoutId > 0) {
        $exists = $pdo->prepare('SELECT id FROM venue_layouts WHERE id = ? LIMIT 1');
        $exists->execute([$layoutId]);
        if (!$exists->fetch()) {
            $layoutId = 0;
        }
    }

    if ($layoutId > 0) {
        $pdo->prepare('UPDATE venue_layouts SET name = ?, is_active = ?, updated_at = NOW() WHERE id = ?')->execute([$layoutName, $isActive, $layoutId]);
    } else {
        $pdo->prepare('INSERT INTO venue_layouts (name, is_active, created_by, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())')->execute([$layoutName, $isActive, (int) $admin['id']]);
        $layoutId = (int) $pdo->lastInsertId();
    }

    $removedGroups = array_values(array_diff($oldGroups, array_keys($newTentGroups)));
    $blocked = layout_blocked_stalls($pdo, $removedGroups);
    if ($blocked) {
        throw new RuntimeException('Release assigned stalls before deleting these tent groups: ' . implode('; ', $blocked));
    }
    foreach ($removedGroups as $removedGroup) {
        $pdo->prepare('DELETE FROM stalls WHERE tent_group_code = ? AND allocated_to_user_id IS NULL')->execute([$removedGroup]);
    }

    $pdo->prepare('DELETE FROM layout_elements WHERE layout_id = ?')->execute([$layoutId]);
    $insertElement = $pdo->prepare(
        'INSERT INTO layout_elements (layout_id, element_type, tent_group_code, tent_type, stall_count, category, u_zone, x, y, width, height, rotation, label, z_index, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
    );
    foreach ($validated as $element) {
        $insertElement->execute([
            $layoutId,
            $element['element_type'],
            $element['tent_group_code'],
            $element['tent_type'],
            $element['stall_count'],
            $element['category'],
            $element['u_zone'],
            $element['x'],
            $element['y'],
            $element['width'],
            $element['height'],
            $element['rotation'],
            $element['label'],
            $element['z_index'],
        ]);
    }

    if ($isActive) {
        sync_layout_stalls($pdo, $newTentGroups, $rules);
    }

    $pdo->commit();
    json_response(['ok' => true, 'message' => 'Layout saved successfully.', 'layout_id' => $layoutId]);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Layout save failed: ' . $exception->getMessage());
    json_response(['ok' => false, 'message' => $exception instanceof RuntimeException ? $exception->getMessage() : 'Unable to save layout.'], 422);
}
