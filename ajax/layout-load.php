<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
$admin = require_login('admin');
set_exception_handler(function (Throwable $exception): void {
    error_log('Uncaught layout load error: ' . $exception->getMessage());
    json_response(['ok' => false, 'message' => 'The layout could not be loaded. Refresh the page and try again.'], 500);
});

$pdo = db();
ensure_layout_designer_schema((int) $admin['id']);
$layoutId = (int) ($_GET['layout_id'] ?? 0);

$layouts = $pdo->query('SELECT id, name, is_active, updated_at FROM venue_layouts ORDER BY is_active DESC, updated_at DESC, name ASC')->fetchAll();

if ($layoutId <= 0 && $layouts) {
    $layoutId = (int) $layouts[0]['id'];
}

$layout = null;
$elements = [];

if ($layoutId > 0) {
    $statement = $pdo->prepare('SELECT id, name, is_active, updated_at FROM venue_layouts WHERE id = ? LIMIT 1');
    $statement->execute([$layoutId]);
    $layout = $statement->fetch() ?: null;

    if ($layout) {
        $elementsStatement = $pdo->prepare('SELECT id, element_type, tent_group_code, tent_type, stall_count, category, u_zone, x, y, width, height, rotation, label, z_index FROM layout_elements WHERE layout_id = ? ORDER BY z_index ASC, id ASC');
        $elementsStatement->execute([$layoutId]);
        $elements = $elementsStatement->fetchAll();
    }
}

$rules = $pdo->query('SELECT tent_code, arrangement_key, arrangement_name, number_of_stalls, suitable_exhibitors, stall_class FROM tent_arrangement_rules ORDER BY tent_code ASC, number_of_stalls ASC')->fetchAll();
$zones = $pdo->query('SELECT zone_key, zone_name, notes, map_x, map_y, map_width, map_height FROM venue_layout_zones ORDER BY id ASC')->fetchAll();
$tentBookings = [];
$bookingRows = $pdo->query(
    'SELECT tent_group_code,
            COUNT(*) AS total,
            SUM(CASE WHEN is_allocated = 1 THEN 1 ELSE 0 END) AS booked
     FROM stalls
     WHERE tent_group_code IS NOT NULL AND tent_group_code <> ""
     GROUP BY tent_group_code'
)->fetchAll();
foreach ($bookingRows as $row) {
    $tentBookings[(string) $row['tent_group_code']] = [
        'booked' => (int) $row['booked'],
        'total' => (int) $row['total'],
    ];
}

json_response([
    'ok' => true,
    'layouts' => $layouts,
    'layout' => $layout,
    'elements' => $elements,
    'arrangementRules' => $rules,
    'zones' => $zones,
    'tentBookings' => $tentBookings,
]);
