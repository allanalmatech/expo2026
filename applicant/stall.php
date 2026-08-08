<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
$user = require_login('applicant');
$active = 'stall';
$pageTitle = 'Stall Allocation';
$pdo = db();

$statement = $pdo->prepare(
    'SELECT a.*, fr.stall_type, fr.number_of_stalls, fr.electricity_needed, fr.business_name
     FROM applications a
     LEFT JOIN form_responses fr ON fr.id = a.form_response_id
     WHERE a.user_id = ? LIMIT 1'
);
$statement->execute([(int) $user['id']]);
$application = $statement->fetch() ?: null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'request_extra_stall') {
        if (!$application || empty($application['form_response_id'])) {
            set_flash('error', 'No application record was found for your account.');
            redirect('applicant/stall.php');
        }

        $additionalStalls = max(1, min(10, (int) ($_POST['additional_stalls'] ?? 1)));
        $currentRequest = max(1, (int) ($application['number_of_stalls'] ?? 1));
        $newRequest = $currentRequest + $additionalStalls;
        $update = $pdo->prepare('UPDATE form_responses SET number_of_stalls = ?, updated_at = NOW() WHERE id = ?');
        $update->execute([$newRequest, (int) $application['form_response_id']]);
        refresh_application_payment_status_from_uploads($pdo, (int) $application['id']);

        set_flash('success', 'Your stall request was updated to ' . $newRequest . ' stalls. The committee can now assign another available slot.');
        redirect('applicant/stall.php');
    }
}

$activeLayout = null;
$assignedStall = null;
$assignedStalls = [];
$assignedTentGroups = [];
$layoutElements = [];
$tentBookings = [];
$tentSlotsByGroup = [];

if ($application) {
    try {
        ensure_layout_designer_schema();
        $activeLayout = active_venue_layout($pdo);
        if ($activeLayout) {
            $stallStatement = $pdo->prepare(
                'SELECT s.*, le.id AS layout_element_id, le.label AS tent_label, le.element_type AS layout_element_type,
                        le.stall_count AS layout_stall_count, le.category AS layout_category, le.u_zone AS layout_u_zone,
                        le.x, le.y, le.width, le.height, le.rotation,
                        vlz.zone_name, vlz.notes AS zone_description
                 FROM stalls s
                 LEFT JOIN layout_elements le ON le.layout_id = ?
                    AND le.tent_group_code = s.tent_group_code
                    AND le.element_type IN ("tent_50", "tent_100")
                  LEFT JOIN venue_layout_zones vlz ON vlz.zone_key = COALESCE(le.u_zone, s.layout_zone)
                  WHERE s.allocated_to_user_id = ?
                  ORDER BY s.tent_group_code ASC, s.stall_number ASC'
            );
            $stallStatement->execute([(int) $activeLayout['id'], (int) $user['id']]);
            $assignedStalls = $stallStatement->fetchAll();
            $assignedStall = $assignedStalls[0] ?? null;

            foreach ($assignedStalls as $stall) {
                $group = trim((string) ($stall['tent_group_code'] ?? ''));
                if ($group !== '' && !in_array($group, $assignedTentGroups, true)) {
                    $assignedTentGroups[] = $group;
                }
            }

            if ($assignedTentGroups) {
                $elementsStatement = $pdo->prepare(
                    'SELECT element_type, tent_group_code, tent_type, stall_count, category, u_zone, x, y, width, height, rotation, label, z_index
                     FROM layout_elements
                     WHERE layout_id = ?
                     ORDER BY z_index ASC, id ASC'
                );
                $elementsStatement->execute([(int) $activeLayout['id']]);
                $layoutElements = $elementsStatement->fetchAll();

                $placeholders = implode(',', array_fill(0, count($assignedTentGroups), '?'));
                $bookingStatement = $pdo->prepare(
                    'SELECT tent_group_code,
                            COUNT(*) AS total,
                            SUM(CASE WHEN is_allocated = 1 THEN 1 ELSE 0 END) AS booked
                     FROM stalls
                     WHERE tent_group_code IN (' . $placeholders . ')
                     GROUP BY tent_group_code'
                );
                $bookingStatement->execute($assignedTentGroups);
                $bookingRows = $bookingStatement->fetchAll();
                foreach ($bookingRows as $row) {
                    $tentBookings[(string) $row['tent_group_code']] = [
                        'booked' => (int) $row['booked'],
                        'total' => (int) $row['total'],
                    ];
                }

                $slotsStatement = $pdo->prepare(
                    'SELECT id, stall_number, stall_type, tent_group_code, arrangement_key, is_allocated, allocated_to_user_id
                     FROM stalls
                     WHERE tent_group_code IN (' . $placeholders . ')
                     ORDER BY tent_group_code ASC, stall_number ASC'
                );
                $slotsStatement->execute($assignedTentGroups);
                foreach ($slotsStatement->fetchAll() as $slot) {
                    $tentSlotsByGroup[(string) $slot['tent_group_code']][] = $slot;
                }
            }
        }
    } catch (Throwable $exception) {
        error_log('Applicant stall layout lookup failed: ' . $exception->getMessage());
        $activeLayout = null;
        $assignedStall = null;
        $assignedStalls = [];
        $assignedTentGroups = [];
        $layoutElements = [];
        $tentBookings = [];
        $tentSlotsByGroup = [];
    }
}

$requestedStallCount = $application ? max(1, (int) ($application['number_of_stalls'] ?? 1)) : 0;
$assignedCount = count($assignedStalls);
$legacyAssignedNumbers = trim((string) ($application['assigned_stall_number'] ?? ''));
$assignedNumbers = $assignedCount > 0 ? implode(', ', array_map(function (array $stall): string {
    return (string) $stall['stall_number'];
}, $assignedStalls)) : $legacyAssignedNumbers;
$displayAssignedCount = $assignedCount > 0 ? $assignedCount : ($legacyAssignedNumbers !== '' ? max(1, count(array_filter(array_map('trim', explode(',', $legacyAssignedNumbers))))) : 0);
$assignedZoneNames = [];
foreach ($assignedStalls as $stall) {
    $zoneName = trim((string) ($stall['zone_name'] ?? $stall['layout_zone'] ?? ''));
    if ($zoneName !== '' && !in_array($zoneName, $assignedZoneNames, true)) {
        $assignedZoneNames[] = $zoneName;
    }
}

$elementLabels = [
    'stage' => 'Stage',
    'reg_desk' => 'Registration',
    'waste_point' => 'Waste',
    'toilet_m' => 'Toilet M',
    'toilet_f' => 'Toilet F',
    'walkway' => 'Walkway',
    'label' => 'Label',
];

require_once __DIR__ . '/../includes/header.php';
?>
<div class="app-shell">
    <?php require __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="app-main">
        <div class="page-header">
            <div>
                <h1>Stall Allocation</h1>
                <p>Your assigned stall number and location will appear here after approval.</p>
            </div>
            <?php echo badge($application['application_status'] ?? 'Pending Review'); ?>
        </div>

        <section class="panel <?php echo $displayAssignedCount > 0 ? 'dark-card' : ''; ?>">
            <?php if ($displayAssignedCount > 0): ?>
                <span class="eyebrow">Assigned Stalls</span>
                <h2><?php echo h($assignedNumbers); ?></h2>
                <p><strong>Location:</strong> <?php echo h($application['assigned_stall_location'] ?: 'Location to be confirmed'); ?></p>
                <?php if ($assignedStall): ?>
                    <div class="summary-grid">
                        <div class="summary-item"><span>Requested</span><strong><?php echo (int) $requestedStallCount; ?></strong></div>
                        <div class="summary-item"><span>Assigned</span><strong><?php echo (int) $displayAssignedCount; ?></strong></div>
                        <div class="summary-item"><span>Tent</span><strong><?php echo h(implode(', ', $assignedTentGroups) ?: 'Not set'); ?></strong></div>
                        <div class="summary-item"><span>Zone</span><strong><?php echo h(implode(', ', $assignedZoneNames) ?: 'Not set'); ?></strong></div>
                        <div class="summary-item"><span>Tent Type</span><strong><?php echo h(($assignedStall['tent_code'] ?? '') !== '' ? $assignedStall['tent_code'] . '-seater' : 'Not set'); ?></strong></div>
                        <div class="summary-item"><span>Description</span><strong><?php echo h($assignedStall['zone_description'] ?? 'To be confirmed'); ?></strong></div>
                    </div>
                <?php endif; ?>
                <p><strong>Business:</strong> <?php echo h($application['business_name'] ?? 'Not provided'); ?></p>
                <p><strong>Stall type:</strong> <?php echo h($application['stall_type'] ?? 'Not provided'); ?></p>
            <?php else: ?>
                <div class="empty-state">
                    <h2>Awaiting Allocation</h2>
                    <p>Stall allocation is completed by the committee after application, payment, and compliance review.</p>
                    <a class="button button-primary" href="<?php echo h(app_url('applicant/dashboard.php')); ?>">Return to Dashboard</a>
                </div>
            <?php endif; ?>
        </section>

        <?php if ($application): ?>
            <section class="panel" style="margin-top: 22px;">
                <div class="page-header">
                    <div>
                        <h2>Request Another Stall</h2>
                        <p>Your current request is <?php echo (int) $requestedStallCount; ?> stall<?php echo $requestedStallCount === 1 ? '' : 's'; ?>. Assigned so far: <?php echo (int) $displayAssignedCount; ?>.</p>
                    </div>
                </div>
                <form method="post" class="form-grid two" data-confirm="Request additional stall space? This may increase your payment balance.">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="request_extra_stall">
                    <div class="field">
                        <label for="additional_stalls">Additional Stalls</label>
                        <input type="number" id="additional_stalls" name="additional_stalls" min="1" max="10" value="1" required>
                        <small>The committee will assign any approved extra stall from available slots.</small>
                    </div>
                    <button class="button button-primary" type="submit">Request Additional Stall</button>
                </form>
            </section>
        <?php endif; ?>

        <?php if ($displayAssignedCount > 0): ?>
            <section class="panel" style="margin-top: 22px;">
                <div class="page-header">
                    <div>
                        <h2>Your Location On The Layout</h2>
                        <p><?php echo $activeLayout ? 'Layout: ' . h($activeLayout['name']) : 'The committee has not published a matching layout location yet.'; ?></p>
                    </div>
                    <?php if ($assignedStall): ?><?php echo badge($assignedStall['tent_group_code'] ?: 'Assigned'); ?><?php endif; ?>
                </div>

                <?php if ($assignedTentGroups && $layoutElements): ?>
                    <div class="applicant-map-wrap">
                        <div class="applicant-layout-map" aria-label="Venue layout map showing your assigned tent" data-applicant-tent-map>
                            <?php foreach ($layoutElements as $element): ?>
                                <?php
                                $type = (string) ($element['element_type'] ?? 'label');
                                $isTent = in_array($type, ['tent_50', 'tent_100'], true);
                                $tentGroup = trim((string) ($element['tent_group_code'] ?? ''));
                                $isAssigned = $isTent && $tentGroup !== '' && in_array($tentGroup, $assignedTentGroups, true);
                                if ($isTent) {
                                    $label = $tentGroup !== '' ? $tentGroup : 'Tent';
                                    if ($isAssigned) {
                                        $booking = $tentBookings[$label] ?? [];
                                        $total = (int) ($booking['total'] ?? $element['stall_count'] ?? 0);
                                        $booked = min((int) ($booking['booked'] ?? 0), $total > 0 ? $total : (int) ($booking['booked'] ?? 0));
                                        $label .= $total > 0 ? ' (' . $booked . '/' . $total . ')' : '';
                                    }
                                } else {
                                    $label = trim((string) ($element['label'] ?? '')) ?: ($elementLabels[$type] ?? 'Element');
                                }
                                $left = max(0, min(100, ((int) $element['x'] / 1200) * 100));
                                $top = max(0, min(100, ((int) $element['y'] / 1600) * 100));
                                $width = max(1, min(100, ((int) $element['width'] / 1200) * 100));
                                $height = max(1, min(100, ((int) $element['height'] / 1600) * 100));
                                $style = 'left: ' . number_format($left, 4, '.', '') . '%; top: ' . number_format($top, 4, '.', '') . '%; width: ' . number_format($width, 4, '.', '') . '%; height: ' . number_format($height, 4, '.', '') . '%; transform: rotate(' . (int) $element['rotation'] . 'deg); z-index: ' . (int) $element['z_index'] . ';';
                                ?>
                                <?php if ($isAssigned): ?>
                                    <button class="applicant-map-element <?php echo h($type); ?> is-assigned is-clickable" type="button" style="<?php echo h($style); ?>" data-applicant-tent-open="<?php echo h($tentGroup); ?>" aria-label="View slots inside tent <?php echo h($tentGroup); ?>">
                                        <?php echo h('YOU: ' . $label); ?>
                                    </button>
                                <?php else: ?>
                                    <div class="applicant-map-element <?php echo h($type); ?> <?php echo $isTent ? 'is-other-tent' : ''; ?>" style="<?php echo h($style); ?>">
                                        <?php echo h($label); ?>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="applicant-tent-slots" data-applicant-tent-panels>
                        <?php foreach ($assignedTentGroups as $index => $tentGroup): ?>
                            <section class="tent-slot-panel" data-applicant-tent-panel="<?php echo h($tentGroup); ?>" <?php echo $index === 0 ? '' : 'hidden'; ?>>
                                <div class="page-header">
                                    <div>
                                        <h3>Inside Tent <?php echo h($tentGroup); ?></h3>
                                        <p>Only slots inside your assigned tent are shown. Other applicants are not identified.</p>
                                    </div>
                                    <?php echo badge($tentGroup); ?>
                                </div>
                                <div class="tent-slot-grid">
                                    <?php foreach (($tentSlotsByGroup[$tentGroup] ?? []) as $slot): ?>
                                        <?php
                                        $isMine = (int) ($slot['allocated_to_user_id'] ?? 0) === (int) $user['id'];
                                        $slotStatus = $isMine ? 'Your slot' : ((int) ($slot['is_allocated'] ?? 0) === 1 ? 'Reserved' : 'Available');
                                        $slotClass = $isMine ? 'is-mine' : ((int) ($slot['is_allocated'] ?? 0) === 1 ? 'is-reserved' : 'is-available');
                                        ?>
                                        <div class="tent-slot-card <?php echo h($slotClass); ?>">
                                            <strong><?php echo h($slot['stall_number']); ?></strong>
                                            <span><?php echo h($slotStatus); ?></span>
                                            <small><?php echo h($slot['stall_type'] ?? 'Stall slot'); ?></small>
                                        </div>
                                    <?php endforeach; ?>
                                    <?php if (empty($tentSlotsByGroup[$tentGroup])): ?><p class="help-text">No internal slot records are available for this tent yet.</p><?php endif; ?>
                                </div>
                            </section>
                        <?php endforeach; ?>
                    </div>
                    <p class="help-text">Click your highlighted tent to view the internal stall slots for that tent only.</p>
                <?php else: ?>
                    <div class="empty-state">
                        <h2>Map Location Pending</h2>
                        <p>Your stall number has been assigned, but it has not been matched to the saved layout map yet.</p>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </main>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
