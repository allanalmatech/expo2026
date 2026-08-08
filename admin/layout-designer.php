<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

$admin = require_login('admin');
$active = 'layout_designer';
$pageTitle = 'Venue Layout Designer';
$bodyClass = 'layout-designer-page';
$extraCss = ['assets/css/layout-designer.css'];
$extraJs = ['assets/js/layout-designer.js'];

$pdo = db();
ensure_layout_designer_schema((int) $admin['id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'save_zone' || $action === 'update_zone') {
        $zoneKey = trim((string) ($_POST['zone_key'] ?? ''));
        $zoneName = trim((string) ($_POST['zone_name'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $mapX = isset($_POST['map_x']) && $_POST['map_x'] !== '' ? max(0, min(5000, (int) $_POST['map_x'])) : null;
        $mapY = isset($_POST['map_y']) && $_POST['map_y'] !== '' ? max(0, min(5000, (int) $_POST['map_y'])) : null;
        $mapWidth = isset($_POST['map_width']) && $_POST['map_width'] !== '' ? max(0, min(5000, (int) $_POST['map_width'])) : null;
        $mapHeight = isset($_POST['map_height']) && $_POST['map_height'] !== '' ? max(0, min(5000, (int) $_POST['map_height'])) : null;

        if ($mapWidth === null || $mapHeight === null || $mapWidth <= 0 || $mapHeight <= 0) {
            $mapX = null;
            $mapY = null;
            $mapWidth = null;
            $mapHeight = null;
        }

        try {
            if ($zoneName === '') {
                throw new RuntimeException('Enter a zone name.');
            }

            $existingName = $pdo->prepare('SELECT zone_key FROM venue_layout_zones WHERE LOWER(zone_name) = LOWER(?) AND zone_key <> ? LIMIT 1');
            $existingName->execute([$zoneName, $action === 'update_zone' ? $zoneKey : '']);
            if ($existingName->fetchColumn()) {
                throw new RuntimeException('A zone with that name already exists.');
            }

            if ($action === 'update_zone') {
                if ($zoneKey === '') {
                    throw new RuntimeException('Select a valid zone to edit.');
                }

                $exists = $pdo->prepare('SELECT zone_key FROM venue_layout_zones WHERE zone_key = ? LIMIT 1');
                $exists->execute([$zoneKey]);
                if (!$exists->fetchColumn()) {
                    throw new RuntimeException('The selected zone no longer exists.');
                }

                $statement = $pdo->prepare('UPDATE venue_layout_zones SET zone_name = ?, u_position = ?, traffic_level = ?, notes = ?, map_x = ?, map_y = ?, map_width = ?, map_height = ? WHERE zone_key = ?');
                $statement->execute([$zoneName, 'Custom layout zone', 'Not specified', $description ?: null, $mapX, $mapY, $mapWidth, $mapHeight, $zoneKey]);
                set_flash('success', 'Zone updated.');
                redirect('admin/layout-designer.php');
            }

            $baseKey = strtolower((string) preg_replace('/[^a-z0-9]+/i', '_', $zoneName));
            $baseKey = trim($baseKey, '_') ?: 'zone';
            $zoneKey = $baseKey;
            $suffix = 2;
            $existingKey = $pdo->prepare('SELECT id FROM venue_layout_zones WHERE zone_key = ? LIMIT 1');
            while (true) {
                $existingKey->execute([$zoneKey]);
                if (!$existingKey->fetchColumn()) {
                    break;
                }
                $zoneKey = $baseKey . '_' . $suffix;
                $suffix++;
            }

            $statement = $pdo->prepare('INSERT INTO venue_layout_zones (zone_key, zone_name, u_position, traffic_level, notes, map_x, map_y, map_width, map_height) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $statement->execute([$zoneKey, $zoneName, 'Custom layout zone', 'Not specified', $description ?: null, $mapX, $mapY, $mapWidth, $mapHeight]);
            set_flash('success', 'Zone created. It is now available in the Layout Designer.');
        } catch (Throwable $exception) {
            set_flash('error', $exception instanceof RuntimeException ? $exception->getMessage() : 'The zone could not be saved.');
        }

        redirect('admin/layout-designer.php');
    }

    if ($action === 'delete_zone') {
        $zoneKey = trim((string) ($_POST['zone_key'] ?? ''));

        try {
            if ($zoneKey === '') {
                throw new RuntimeException('Select a valid zone to delete.');
            }

            $exists = $pdo->prepare('SELECT zone_name FROM venue_layout_zones WHERE zone_key = ? LIMIT 1');
            $exists->execute([$zoneKey]);
            $zoneName = (string) ($exists->fetchColumn() ?: '');
            if ($zoneName === '') {
                throw new RuntimeException('The selected zone no longer exists.');
            }

            $usage = $pdo->prepare(
                'SELECT
                    (SELECT COUNT(*) FROM layout_elements WHERE u_zone = ?) AS layout_count,
                    (SELECT COUNT(*) FROM stalls WHERE layout_zone = ?) AS stall_count'
            );
            $usage->execute([$zoneKey, $zoneKey]);
            $usageRow = $usage->fetch() ?: ['layout_count' => 0, 'stall_count' => 0];
            $usageCount = (int) $usageRow['layout_count'] + (int) $usageRow['stall_count'];
            if ($usageCount > 0) {
                throw new RuntimeException('This zone is used by layout elements or stall slots. Reassign those tents before deleting it.');
            }

            $pdo->prepare('DELETE FROM venue_layout_zones WHERE zone_key = ?')->execute([$zoneKey]);
            set_flash('success', 'Zone deleted: ' . $zoneName . '.');
        } catch (Throwable $exception) {
            set_flash('error', $exception instanceof RuntimeException ? $exception->getMessage() : 'The zone could not be deleted.');
        }

        redirect('admin/layout-designer.php');
    }
}

try {
    $layouts = $pdo->query('SELECT id, name, is_active, updated_at FROM venue_layouts ORDER BY is_active DESC, updated_at DESC, name ASC')->fetchAll();
} catch (Throwable $exception) {
    $layouts = [];
}

try {
    $rules = $pdo->query('SELECT tent_code, arrangement_key, arrangement_name, number_of_stalls, suitable_exhibitors, stall_class FROM tent_arrangement_rules ORDER BY tent_code ASC, number_of_stalls ASC')->fetchAll();
} catch (Throwable $exception) {
    $rules = [];
}

try {
    $zones = $pdo->query('SELECT zone_key, zone_name, notes, map_x, map_y, map_width, map_height FROM venue_layout_zones ORDER BY id ASC')->fetchAll();
} catch (Throwable $exception) {
    $zones = [];
}

$tentBookings = [];
try {
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
} catch (Throwable $exception) {
    $tentBookings = [];
}

$editZone = null;
if (!empty($_GET['edit_zone'])) {
    $statement = $pdo->prepare('SELECT zone_key, zone_name, notes, map_x, map_y, map_width, map_height FROM venue_layout_zones WHERE zone_key = ? LIMIT 1');
    $statement->execute([(string) $_GET['edit_zone']]);
    $editZone = $statement->fetch() ?: null;
}

$config = [
    'loadUrl' => app_url('ajax/layout-load.php'),
    'saveUrl' => app_url('ajax/layout-save.php'),
    'stallCsvUrl' => app_url('admin/reports.php?export=stalls'),
    'csrfToken' => csrf_token(),
    'initialLayoutId' => (int) ($layouts[0]['id'] ?? 0),
    'initialEditMode' => $editZone ? 1 : 0,
    'arrangementRules' => $rules,
    'zones' => $zones,
    'tentBookings' => $tentBookings,
    'defaultCanvas' => ['width' => 1200, 'height' => 1600, 'grid' => 40],
];

require_once __DIR__ . '/../includes/header.php';
?>
<div class="app-shell layout-designer-shell <?php echo $editZone ? 'is-edit-mode' : 'is-viewer-mode'; ?>">
    <?php require __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="app-main layout-main">
        <div class="layout-topbar">
            <div>
                <div class="layout-title-row">
                    <h1>Venue Layout Designer</h1>
                    <span class="badge <?php echo $editZone ? 'badge-success' : 'badge-muted'; ?>" id="layout-mode-badge"><?php echo $editZone ? 'Live Edit Mode' : 'Viewer Mode'; ?></span>
                </div>
                <p>View the MUST Pitch layout, then switch into live edit mode when changes are needed.</p>
            </div>
            <div class="layout-actions">
                <button class="button <?php echo $editZone ? 'button-secondary' : 'button-primary'; ?>" id="layout-edit-mode" type="button"><?php echo $editZone ? 'Viewer Mode' : '&#9998; Live Edit Mode'; ?></button>
                <input id="layout-name" data-edit-only type="text" value="<?php echo h($layouts[0]['name'] ?? 'Default U-Shape MUST Pitch'); ?>" placeholder="Layout name" aria-label="Layout name">
                <select id="layout-select" aria-label="Saved layouts">
                    <?php foreach ($layouts as $layout): ?>
                        <option value="<?php echo (int) $layout['id']; ?>" <?php echo (int) $layout['is_active'] === 1 ? 'selected' : ''; ?>><?php echo h($layout['name']); ?><?php echo (int) $layout['is_active'] === 1 ? ' (Active)' : ''; ?></option>
                    <?php endforeach; ?>
                    <?php if (!$layouts): ?><option value="0">New Layout</option><?php endif; ?>
                </select>
                <label class="designer-switch" data-edit-only><input id="layout-active" type="checkbox" checked> Active</label>
                <button class="button button-ghost" id="new-layout" type="button" data-edit-only>New</button>
                <button class="button button-ghost" type="button" data-proof-modal-open="layout-zone-modal" data-edit-only>+ Zone</button>
                <button class="button button-secondary" id="export-layout" type="button">Export PNG</button>
                <a class="button button-ghost" href="<?php echo h(app_url('admin/reports.php?export=stalls')); ?>">Export CSV</a>
                <button class="button button-primary" id="save-layout" type="button" data-edit-only>Save Layout</button>
            </div>
        </div>

        <div class="designer-workspace">
            <aside class="designer-palette">
                <div class="palette-header">
                    <h2>Element Palette</h2>
                    <p>Drag onto canvas or click to add.</p>
                </div>
                <div class="palette-list" id="palette-list">
                    <button class="palette-item" draggable="true" data-type="tent_50" type="button"><strong>50-Seater Tent</strong><small>Single canopy, max 5 stalls</small></button>
                    <button class="palette-item" draggable="true" data-type="tent_100" type="button"><strong>100-Seater Tent</strong><small>Double canopy, max 10 stalls</small></button>
                    <button class="palette-item" draggable="true" data-type="stage" type="button"><strong>Main Stage</strong><small>Top-center U focal point</small></button>
                    <button class="palette-item" draggable="true" data-type="reg_desk" type="button"><strong>Registration Desk</strong><small>Entry support point</small></button>
                    <button class="palette-item" draggable="true" data-type="waste_point" type="button"><strong>Waste Collection Point</strong><small>Service marker</small></button>
                    <button class="palette-item" draggable="true" data-type="toilet_m" type="button"><strong>Mobile Toilet (M)</strong><small>Male toilet unit</small></button>
                    <button class="palette-item" draggable="true" data-type="toilet_f" type="button"><strong>Mobile Toilet (F)</strong><small>Female toilet unit</small></button>
                    <button class="palette-item" draggable="true" data-type="walkway" type="button"><strong>Walkway Marker</strong><small>Path marker</small></button>
                    <button class="palette-item" draggable="true" data-type="label" type="button"><strong>Custom Label</strong><small>Free text box</small></button>
                </div>

                <div class="designer-stats">
                    <h3>Live Totals</h3>
                    <div id="layout-totals" class="totals-grid"></div>
                </div>
            </aside>

            <section class="designer-canvas-wrap">
                <div class="canvas-toolbar">
                    <label data-edit-only><input type="checkbox" id="snap-toggle" checked> Snap to grid</label>
                    <button class="button button-ghost" id="zoom-out" type="button">-</button>
                    <span id="zoom-label">100%</span>
                    <button class="button button-ghost" id="zoom-in" type="button">+</button>
                    <button class="button button-ghost" id="zoom-fit" type="button">Fit</button>
                    <label><input type="checkbox" id="zones-only-toggle"> Show zones only</label>
                </div>
                <div class="canvas-scroll" id="canvas-scroll">
                    <div class="designer-canvas canvas-grid" id="layout-canvas" aria-label="Venue layout canvas"></div>
                </div>
            </section>

            <aside class="designer-properties" id="properties-panel">
                <div class="properties-header">
                    <h2>Properties</h2>
                    <button class="icon-button" id="clear-selection" type="button" aria-label="Clear selection">x</button>
                </div>

                <form id="element-form" class="form-grid">
                    <div class="notice">Select an element to edit its properties.</div>
                    <input type="hidden" id="element-id">
                    <div class="field"><label>Label Text</label><input id="prop-label" type="text"></div>
                    <div class="form-grid two">
                        <div class="field"><label>X</label><input id="prop-x" type="number" min="0"></div>
                        <div class="field"><label>Y</label><input id="prop-y" type="number" min="0"></div>
                        <div class="field"><label>Width</label><input id="prop-width" type="number" min="20"></div>
                        <div class="field"><label>Height</label><input id="prop-height" type="number" min="20"></div>
                    </div>
                    <div class="header-actions">
                        <button class="button button-ghost" id="rotate-element" type="button">Rotate 90</button>
                        <button class="button button-ghost" id="duplicate-element" type="button">Duplicate</button>
                    </div>

                    <div class="tent-only">
                        <div class="field"><label>Tent Group Code</label><input id="prop-tent-group" type="text" placeholder="TENT-A"></div>
                        <div class="field"><label>Tent Type</label><select id="prop-tent-type"><option value="50">50-Seater</option><option value="100">100-Seater</option></select></div>
                        <div class="field"><label>Number of Stalls</label><select id="prop-stall-count"></select></div>
                        <div class="field"><label>Category</label><select id="prop-category"><option value="student">Student / Startup</option><option value="sme">SME / Retail</option><option value="ngo_government">NGO / Government</option><option value="corporate">Corporate</option><option value="sponsor">Sponsor</option><option value="food_beverage">Food & Beverage</option></select></div>
                        <div class="field"><label>U-Layout Zone</label><select id="prop-zone"></select></div>
                    </div>

                    <button class="button button-danger" id="delete-element" type="button" aria-label="Delete selected element" title="Delete selected element">&#128465;</button>
                </form>
            </aside>
        </div>
    </main>
</div>

<div class="proof-modal" id="layout-zone-modal" <?php echo $editZone ? '' : 'hidden'; ?> data-proof-modal>
    <div class="proof-modal-backdrop" data-proof-modal-close></div>
    <section class="proof-modal-panel" role="dialog" aria-modal="true" aria-labelledby="layout-zone-modal-title">
        <div class="proof-modal-header">
            <div>
                <h2 id="layout-zone-modal-title"><?php echo $editZone ? 'Edit Layout Zone' : 'Create Layout Zone'; ?></h2>
                <p>Add a named zone that can be assigned to tents in the Layout Designer.</p>
            </div>
            <?php if ($editZone): ?>
                <a class="icon-button" href="<?php echo h(app_url('admin/layout-designer.php')); ?>" aria-label="Close zone form">x</a>
            <?php else: ?>
                <button class="icon-button" type="button" data-proof-modal-close aria-label="Close zone form">x</button>
            <?php endif; ?>
        </div>
        <form method="post" class="form-grid" data-zone-form>
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="<?php echo $editZone ? 'update_zone' : 'save_zone'; ?>">
            <input type="hidden" name="zone_key" value="<?php echo h($editZone['zone_key'] ?? ''); ?>">
            <input type="hidden" name="map_x" value="<?php echo h(isset($editZone['map_x']) ? (string) $editZone['map_x'] : ''); ?>" data-zone-map-x>
            <input type="hidden" name="map_y" value="<?php echo h(isset($editZone['map_y']) ? (string) $editZone['map_y'] : ''); ?>" data-zone-map-y>
            <input type="hidden" name="map_width" value="<?php echo h(isset($editZone['map_width']) ? (string) $editZone['map_width'] : ''); ?>" data-zone-map-width>
            <input type="hidden" name="map_height" value="<?php echo h(isset($editZone['map_height']) ? (string) $editZone['map_height'] : ''); ?>" data-zone-map-height>
            <div class="field"><label>Map Area</label><button class="button button-secondary" type="button" data-zone-map-select>Select Area On Map</button><small data-zone-area-summary>No map area selected yet.</small></div>
            <div class="field"><label>Zone Name</label><input name="zone_name" placeholder="e.g. Sponsor Row" value="<?php echo h($editZone['zone_name'] ?? ''); ?>" required></div>
            <div class="field"><label>Description</label><textarea name="description" placeholder="Describe what this zone is for"><?php echo h($editZone['notes'] ?? ''); ?></textarea></div>
            <div class="proof-modal-actions"><button class="button button-primary" type="submit">Save Zone</button><?php if ($editZone): ?><a class="button button-ghost" href="<?php echo h(app_url('admin/layout-designer.php')); ?>">Cancel Edit</a><?php endif; ?></div>
        </form>
        <div class="table-scroll" style="margin-top: 18px;">
            <table>
                <thead><tr><th>Zone</th><th>Description</th><th>Action</th></tr></thead>
                <tbody>
                <?php foreach ($zones as $zone): ?>
                    <tr>
                        <td data-label="Zone"><strong><?php echo h($zone['zone_name']); ?></strong><br><small><?php echo h($zone['zone_key']); ?></small></td>
                        <td data-label="Description"><?php echo h($zone['notes'] ?? 'No description'); ?></td>
                        <td data-label="Action"><div class="icon-action-row"><a class="table-icon-button" href="<?php echo h(app_url('admin/layout-designer.php?edit_zone=' . rawurlencode((string) $zone['zone_key']))); ?>" aria-label="Edit zone" title="Edit">&#9998;</a><form method="post" data-confirm="Delete this layout zone? Tents must be reassigned first if the zone is in use."><?php echo csrf_field(); ?><input type="hidden" name="action" value="delete_zone"><input type="hidden" name="zone_key" value="<?php echo h($zone['zone_key']); ?>"><button class="table-icon-button danger" type="submit" aria-label="Delete zone" title="Delete">&#128465;</button></form></div></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$zones): ?><tr><td colspan="3" class="empty-state">No layout zones have been created yet.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<script>
window.LayoutDesignerConfig = <?php echo json_encode($config, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
