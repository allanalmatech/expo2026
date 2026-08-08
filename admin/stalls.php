<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/tent_simulation.php';
$admin = require_login('admin');
$active = 'stalls';
$pageTitle = 'Stalls';
$pdo = db();

ensure_layout_designer_schema((int) $admin['id']);
$tentTypes = tent_types();
$layoutZones = venue_layout_zones();
$syncError = '';
$activeLayout = null;

try {
    $activeLayout = sync_active_layout_stalls($pdo);
} catch (Throwable $exception) {
    error_log('Active layout stall sync failed: ' . $exception->getMessage());
    $syncError = $exception instanceof RuntimeException ? $exception->getMessage() : 'Unable to sync stalls from the active saved layout.';
    $activeLayout = active_venue_layout($pdo);
}

$activeLayoutId = $activeLayout ? (int) $activeLayout['id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'assign_stall') {
        if ($activeLayoutId <= 0 || $syncError !== '') {
            set_flash('error', 'Save and activate a layout before assigning tent slots.');
            redirect('admin/stalls.php');
        }

        $stallId = (int) ($_POST['stall_id'] ?? 0);
        $applicationId = (int) ($_POST['application_id'] ?? 0);
        $stallRow = fetch_layout_stall($pdo, $activeLayoutId, $stallId);
        $application = $pdo->prepare(
            'SELECT a.*, u.full_name
             FROM applications a
             INNER JOIN users u ON u.id = a.user_id
             WHERE a.id = ? LIMIT 1'
        );
        $application->execute([$applicationId]);
        $applicationRow = $application->fetch();

        if (!$stallRow || !$applicationRow) {
            set_flash('error', 'Select a valid saved-layout tent slot and applicant.');
            redirect('admin/stalls.php');
        }
        if ((int) $stallRow['is_allocated'] === 1 && (int) $stallRow['allocated_to_user_id'] !== (int) $applicationRow['user_id']) {
            set_flash('error', 'This saved-layout tent slot is already allocated.');
            redirect('admin/stalls.php');
        }

        $assignedLocation = layout_stall_location_label($stallRow);
        try {
            $pdo->beginTransaction();
            $pdo->prepare('UPDATE stalls SET is_allocated = 1, allocated_to_user_id = ?, updated_at = NOW() WHERE id = ?')->execute([(int) $applicationRow['user_id'], $stallId]);
            refresh_application_stall_assignment($pdo, (int) $applicationRow['user_id']);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Layout stall assignment failed: ' . $exception->getMessage());
            set_flash('error', 'The tent slot could not be assigned.');
            redirect('admin/stalls.php');
        }

        set_flash('success', 'Assigned ' . $applicationRow['full_name'] . ' to ' . $stallRow['tent_group_code'] . ' / ' . $stallRow['stall_number'] . ' (' . $assignedLocation . ').');
        redirect('admin/stalls.php');
    }

    if ($action === 'release_stall') {
        $stallId = (int) ($_POST['stall_id'] ?? 0);
        $stallRow = $activeLayoutId > 0 ? fetch_layout_stall($pdo, $activeLayoutId, $stallId) : null;
        if (!$stallRow) {
            $fallback = $pdo->prepare('SELECT * FROM stalls WHERE id = ? LIMIT 1');
            $fallback->execute([$stallId]);
            $stallRow = $fallback->fetch() ?: null;
        }

        if ($stallRow) {
            $releasedUserId = (int) ($stallRow['allocated_to_user_id'] ?? 0);
            try {
                $pdo->beginTransaction();
                $pdo->prepare('UPDATE stalls SET is_allocated = 0, allocated_to_user_id = NULL, updated_at = NOW() WHERE id = ?')->execute([$stallId]);
                if ($releasedUserId > 0) {
                    refresh_application_stall_assignment($pdo, $releasedUserId);
                }
                $pdo->commit();
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('Layout stall release failed: ' . $exception->getMessage());
                set_flash('error', 'The tent slot allocation could not be released.');
                redirect('admin/stalls.php');
            }
        }
        set_flash('success', 'Tent slot released.');
        redirect('admin/stalls.php');
    }
}

$stalls = $activeLayoutId > 0 && $syncError === '' ? fetch_layout_stalls($pdo, $activeLayoutId) : [];
$stallsByTent = [];
foreach ($stalls as $stall) {
    $group = (string) ($stall['tent_group_code'] ?? 'Unassigned Tent');
    $stallsByTent[$group][] = $stall;
}

$allocatedCount = 0;
foreach ($stalls as $stall) {
    if ((int) $stall['is_allocated'] === 1) {
        $allocatedCount++;
    }
}
$availableCount = count($stalls) - $allocatedCount;

$applications = $pdo->query(
    'SELECT a.id, a.user_id, a.assigned_stall_number, a.application_status, a.payment_status, a.compliance_status,
            u.full_name, u.email, fr.business_name, fr.number_of_stalls, fr.stall_type, fr.applicant_type,
            COALESCE(sa.assigned_count, 0) AS assigned_count,
            sa.assigned_numbers
     FROM applications a
     INNER JOIN users u ON u.id = a.user_id
     LEFT JOIN form_responses fr ON fr.id = a.form_response_id
     LEFT JOIN (
         SELECT allocated_to_user_id, COUNT(*) AS assigned_count, GROUP_CONCAT(stall_number ORDER BY stall_number ASC SEPARATOR ", ") AS assigned_numbers
         FROM stalls
         WHERE allocated_to_user_id IS NOT NULL
         GROUP BY allocated_to_user_id
     ) sa ON sa.allocated_to_user_id = a.user_id
     ORDER BY CASE WHEN COALESCE(fr.number_of_stalls, 1) > COALESCE(sa.assigned_count, 0) THEN 0 ELSE 1 END,
              u.full_name ASC'
)->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>
<div class="app-shell">
    <?php require __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="app-main">
        <div class="page-header">
            <div><h1>Stall Allocation</h1><p>Assign applicants to tent slots generated from the active saved venue layout.</p></div>
            <div class="header-actions">
                <a class="button button-secondary" href="<?php echo h(app_url('admin/layout-designer.php')); ?>">Open Layout Designer</a>
                <a class="button button-primary" href="<?php echo h(app_url('admin/reports.php?export=stalls')); ?>">Export Stall List</a>
            </div>
        </div>

        <div class="dashboard-grid">
            <section class="panel">
                <h2>Saved Layout Source</h2>
                <?php if ($syncError !== ''): ?>
                    <div class="empty-state"><h3>Layout sync needs attention</h3><p><?php echo h($syncError); ?></p><a class="button button-primary" href="<?php echo h(app_url('admin/layout-designer.php')); ?>">Review Layout</a></div>
                <?php elseif ($activeLayout): ?>
                    <p><strong><?php echo h($activeLayout['name']); ?></strong> is the active allocation layout. Tent slots below are generated from its saved tent elements.</p>
                    <div class="summary-grid">
                        <div class="summary-item"><span>Layout Tents</span><strong><?php echo number_format(count($stallsByTent)); ?></strong></div>
                        <div class="summary-item"><span>Total Slots</span><strong><?php echo number_format(count($stalls)); ?></strong></div>
                        <div class="summary-item"><span>Allocated</span><strong><?php echo number_format($allocatedCount); ?></strong></div>
                        <div class="summary-item"><span>Available</span><strong><?php echo number_format($availableCount); ?></strong></div>
                    </div>
                    <p class="help-text">Add, remove, rename, or move tents in the Layout Designer. Saving an active layout updates this allocation list.</p>
                <?php else: ?>
                    <div class="empty-state"><h3>No active layout</h3><p>Create or activate a venue layout before assigning tent slots.</p><a class="button button-primary" href="<?php echo h(app_url('admin/layout-designer.php')); ?>">Create Layout</a></div>
                <?php endif; ?>
            </section>

            <section class="panel dark-card">
                <h2>Assign User to Tent</h2>
                <p class="help-text">Choose a specific tent slot from the active saved layout, then select the applicant user.</p>
                <?php if ($availableCount <= 0): ?>
                    <div class="empty-state"><h3>No available layout slots</h3><p>All active-layout slots are allocated, or the active layout has no tent elements.</p></div>
                <?php else: ?>
                    <form method="post" class="form-grid" data-confirm="Assign this applicant to the selected saved-layout tent slot?">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="assign_stall">
                        <div class="field">
                            <label>Saved-Layout Tent Slot</label>
                            <select name="stall_id" required>
                                <option value="">Select tent slot</option>
                                <?php foreach ($stallsByTent as $group => $tentStalls): ?>
                                    <?php
                                    $first = $tentStalls[0];
                                    $tentName = $tentTypes[$first['tent_code'] ?? '']['name'] ?? (($first['tent_code'] ?? '') !== '' ? $first['tent_code'] . '-seater Tent' : 'Tent');
                                    $zoneName = $first['zone_name'] ?? ($layoutZones[$first['layout_zone'] ?? '']['name'] ?? ($first['layout_zone'] ?? 'No zone'));
                                    ?>
                                    <optgroup label="<?php echo h($group . ' - ' . $tentName . ' - ' . $zoneName); ?>">
                                        <?php foreach ($tentStalls as $stall): ?>
                                            <?php $isAllocated = (int) $stall['is_allocated'] === 1; ?>
                                            <option value="<?php echo (int) $stall['id']; ?>" <?php echo $isAllocated ? 'disabled' : ''; ?>>
                                                <?php echo h($stall['stall_number'] . ' - ' . ($stall['arrangement_name'] ?: $stall['stall_type']) . ($isAllocated ? ' - allocated to ' . ($stall['full_name'] ?: 'applicant') : '')); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field">
                            <label>Applicant User</label>
                            <select name="application_id" required>
                                <option value="">Select applicant</option>
                                <?php foreach ($applications as $application): ?>
                                    <?php
                                    $requested = trim((string) ($application['stall_type'] ?? ''));
                                    $requestedCount = (int) ($application['number_of_stalls'] ?? 0);
                                    $requestLabel = $requestedCount > 0 ? $requested . ' x ' . $requestedCount : $requested;
                                    $assignedCount = (int) ($application['assigned_count'] ?? 0);
                                    $currentAssignment = trim((string) ($application['assigned_numbers'] ?? $application['assigned_stall_number'] ?? ''));
                                    ?>
                                    <option value="<?php echo (int) $application['id']; ?>">
                                        <?php echo h($application['full_name'] . ' - ' . ($application['business_name'] ?: 'No business') . ($requestLabel !== '' ? ' - requested ' . $requestLabel : '') . ' - assigned ' . $assignedCount . '/' . max(1, $requestedCount) . ($currentAssignment !== '' ? ' - current ' . $currentAssignment : '')); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button class="button button-primary" type="submit">Assign to Tent</button>
                    </form>
                <?php endif; ?>
            </section>
        </div>

        <section class="panel" style="margin-top: 22px;">
            <h2>Active Layout Tent Slots</h2>
            <div class="table-scroll">
                <table>
                    <thead><tr><th>Tent</th><th>Slot</th><th>Zone</th><th>Status</th><th>Allocated To</th><th>Action</th></tr></thead>
                    <tbody>
                    <?php foreach ($stalls as $stall): ?>
                        <?php
                        $tentName = $tentTypes[$stall['tent_code'] ?? '']['name'] ?? (($stall['tent_code'] ?? '') !== '' ? $stall['tent_code'] . '-seater Tent' : 'Tent');
                        $zoneName = $stall['zone_name'] ?? ($layoutZones[$stall['layout_zone'] ?? '']['name'] ?? ($stall['layout_zone'] ?? 'No zone'));
                        ?>
                        <tr>
                            <td data-label="Tent"><strong><?php echo h($stall['tent_group_code'] ?? 'Not set'); ?></strong><br><small><?php echo h($tentName); ?><?php echo !empty($stall['tent_label']) ? ' / ' . h($stall['tent_label']) : ''; ?></small></td>
                            <td data-label="Slot"><strong><?php echo h($stall['stall_number']); ?></strong><br><small><?php echo h($stall['arrangement_name'] ?: ($stall['stall_type'] ?? 'Layout slot')); ?></small></td>
                            <td data-label="Zone"><?php echo h($zoneName); ?></td>
                            <td data-label="Status"><?php echo badge((int) $stall['is_allocated'] === 1 ? 'Allocated' : 'Available'); ?></td>
                            <td data-label="Allocated To"><?php echo h($stall['full_name'] ?? 'Not allocated'); ?><br><small><?php echo h($stall['business_name'] ?? ''); ?></small></td>
                            <td data-label="Action">
                                <?php if ((int) $stall['is_allocated'] === 1): ?>
                                    <form method="post" data-confirm="Release this saved-layout tent slot allocation?">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="action" value="release_stall">
                                        <input type="hidden" name="stall_id" value="<?php echo (int) $stall['id']; ?>">
                                        <button class="button button-ghost" type="submit">Release</button>
                                    </form>
                                <?php else: ?>
                                    <span class="help-text">Ready</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$stalls): ?><tr><td colspan="6" class="empty-state">No saved-layout tent slots are available. Open the Layout Designer, add tents, and save the layout as active.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
