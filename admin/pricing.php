<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
$admin = require_login('admin');
$active = 'pricing';
$pageTitle = 'Pricing Plans';
$pdo = db();
ensure_pricing_schema();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'save_rule') {
            $ruleId = (int) ($_POST['rule_id'] ?? 0);
            $ruleName = trim((string) ($_POST['rule_name'] ?? ''));
            $businessNature = trim((string) ($_POST['business_nature_match'] ?? ''));
            $studentStatus = trim((string) ($_POST['student_status_match'] ?? ''));
            $price = (float) ($_POST['price_per_stall'] ?? 0);
            $priority = (int) ($_POST['priority'] ?? 100);
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            $notes = trim((string) ($_POST['notes'] ?? ''));

            if ($ruleName === '' || $price < 0) {
                throw new RuntimeException('Provide a rule name and a valid price.');
            }

            if ($ruleId > 0) {
                $pdo->prepare('UPDATE pricing_plan_rules SET rule_name = ?, business_nature_match = ?, student_status_match = ?, price_per_stall = ?, priority = ?, is_active = ?, notes = ?, updated_at = NOW() WHERE id = ?')
                    ->execute([$ruleName, $businessNature ?: null, $studentStatus ?: null, $price, $priority, $isActive, $notes ?: null, $ruleId]);
                set_flash('success', 'Pricing rule updated.');
            } else {
                $pdo->prepare('INSERT INTO pricing_plan_rules (rule_name, business_nature_match, student_status_match, price_per_stall, priority, is_active, notes, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())')
                    ->execute([$ruleName, $businessNature ?: null, $studentStatus ?: null, $price, $priority, $isActive, $notes ?: null]);
                set_flash('success', 'Pricing rule created.');
            }
            redirect('admin/pricing.php');
        }

        if ($action === 'delete_rule') {
            $ruleId = (int) ($_POST['rule_id'] ?? 0);
            $pdo->prepare('DELETE FROM pricing_plan_rules WHERE id = ?')->execute([$ruleId]);
            set_flash('success', 'Pricing rule deleted.');
            redirect('admin/pricing.php');
        }

        if ($action === 'save_discount') {
            $applicationId = (int) ($_POST['application_id'] ?? 0);
            $applicationSearch = trim((string) ($_POST['application_search'] ?? ''));
            $discountType = (string) ($_POST['discount_type'] ?? 'fixed');
            $discountValue = (float) ($_POST['discount_value'] ?? 0);
            $reason = trim((string) ($_POST['reason'] ?? ''));

            if ($applicationId <= 0 && preg_match('/\(#(\d+)\)$/', $applicationSearch, $matches) === 1) {
                $applicationId = (int) $matches[1];
            }

            if ($applicationId <= 0 || !in_array($discountType, ['fixed', 'percent'], true) || $discountValue <= 0) {
                throw new RuntimeException('Select an applicant and enter a valid discount.');
            }
            if ($discountType === 'percent' && $discountValue > 100) {
                throw new RuntimeException('Percent discounts cannot exceed 100%.');
            }

            $applicationExists = $pdo->prepare('SELECT id FROM applications WHERE id = ? LIMIT 1');
            $applicationExists->execute([$applicationId]);
            if (!$applicationExists->fetchColumn()) {
                throw new RuntimeException('Select a valid applicant from the search results.');
            }

            $exists = $pdo->prepare('SELECT id FROM application_special_discounts WHERE application_id = ? LIMIT 1');
            $exists->execute([$applicationId]);
            $discountId = (int) ($exists->fetchColumn() ?: 0);
            if ($discountId > 0) {
                $pdo->prepare('UPDATE application_special_discounts SET discount_type = ?, discount_value = ?, reason = ?, created_by = ?, updated_at = NOW() WHERE id = ?')
                    ->execute([$discountType, $discountValue, $reason ?: null, (int) $admin['id'], $discountId]);
            } else {
                $pdo->prepare('INSERT INTO application_special_discounts (application_id, discount_type, discount_value, reason, created_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())')
                    ->execute([$applicationId, $discountType, $discountValue, $reason ?: null, (int) $admin['id']]);
            }
            set_flash('success', 'Special discount saved.');
            redirect('admin/pricing.php');
        }

        if ($action === 'delete_discount') {
            $discountId = (int) ($_POST['discount_id'] ?? 0);
            $pdo->prepare('DELETE FROM application_special_discounts WHERE id = ?')->execute([$discountId]);
            set_flash('success', 'Special discount removed.');
            redirect('admin/pricing.php');
        }
    } catch (Throwable $exception) {
        error_log('Pricing update failed: ' . $exception->getMessage());
        set_flash('error', $exception instanceof RuntimeException ? $exception->getMessage() : 'Pricing could not be updated.');
        redirect('admin/pricing.php');
    }
}

$editRule = null;
if (!empty($_GET['edit_rule'])) {
    $statement = $pdo->prepare('SELECT * FROM pricing_plan_rules WHERE id = ? LIMIT 1');
    $statement->execute([(int) $_GET['edit_rule']]);
    $editRule = $statement->fetch() ?: null;
}

$rules = fetch_pricing_rules($pdo);
$applications = $pdo->query(
    'SELECT a.id, u.full_name, u.email, fr.business_name, fr.business_nature, fr.student_status, fr.number_of_stalls
     FROM applications a
     INNER JOIN users u ON u.id = a.user_id
     LEFT JOIN form_responses fr ON fr.id = a.form_response_id
     ORDER BY u.full_name ASC'
)->fetchAll();
$discounts = $pdo->query(
    'SELECT d.*, u.full_name, fr.business_name
     FROM application_special_discounts d
     INNER JOIN applications a ON a.id = d.application_id
     INNER JOIN users u ON u.id = a.user_id
     LEFT JOIN form_responses fr ON fr.id = a.form_response_id
     ORDER BY d.updated_at DESC, d.id DESC'
)->fetchAll();
$previewRows = pricing_preview_rows($pdo, 120);

$distinct = function (string $field) use ($pdo): array {
    $statement = $pdo->query('SELECT DISTINCT ' . $field . ' AS value FROM form_responses WHERE ' . $field . ' IS NOT NULL AND ' . $field . ' <> "" ORDER BY ' . $field . ' ASC LIMIT 100');
    return array_column($statement->fetchAll(), 'value');
};

require_once __DIR__ . '/../includes/header.php';
?>
<div class="app-shell">
    <?php require __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="app-main">
        <div class="page-header">
            <div>
                <h1>Pricing Plans</h1>
                <p>Set stall pricing by business nature and student status, then allocate special discounts to individual applicants.</p>
            </div>
            <div class="header-actions">
                <button class="button button-secondary" type="button" data-proof-modal-open="pricing-discount-modal">+ Discount</button>
                <button class="button button-primary" type="button" data-proof-modal-open="pricing-rule-modal">+ Add Pricing Plan</button>
            </div>
        </div>

        <div class="proof-modal" id="pricing-rule-modal" <?php echo $editRule ? '' : 'hidden'; ?> data-proof-modal>
            <div class="proof-modal-backdrop" data-proof-modal-close></div>
            <section class="proof-modal-panel" role="dialog" aria-modal="true" aria-labelledby="pricing-rule-modal-title">
                <div class="proof-modal-header">
                    <div>
                        <h2 id="pricing-rule-modal-title"><?php echo $editRule ? 'Edit Pricing Plan' : 'Create Pricing Plan'; ?></h2>
                        <p>Match by business nature and student status. Blank criteria match any value.</p>
                    </div>
                    <?php if ($editRule): ?>
                        <a class="icon-button" href="<?php echo h(app_url('admin/pricing.php')); ?>" aria-label="Close pricing plan form">x</a>
                    <?php else: ?>
                        <button class="icon-button" type="button" data-proof-modal-close aria-label="Close pricing plan form">x</button>
                    <?php endif; ?>
                </div>
                <form method="post" class="form-grid two">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="save_rule">
                    <input type="hidden" name="rule_id" value="<?php echo (int) ($editRule['id'] ?? 0); ?>">
                    <div class="field"><label>Rule Name</label><input name="rule_name" value="<?php echo h($editRule['rule_name'] ?? ''); ?>" required></div>
                    <div class="field"><label>Price Per Stall</label><input type="number" name="price_per_stall" min="0" step="1000" value="<?php echo h(isset($editRule['price_per_stall']) ? (string) $editRule['price_per_stall'] : '400000'); ?>" required></div>
                    <div class="field"><label>Business Nature Contains</label><input name="business_nature_match" list="business-nature-options" value="<?php echo h($editRule['business_nature_match'] ?? ''); ?>"><small>Blank means any business nature.</small></div>
                    <div class="field"><label>Student Status Contains</label><input name="student_status_match" list="student-status-options" value="<?php echo h($editRule['student_status_match'] ?? ''); ?>"><small>Blank means any student status.</small></div>
                    <div class="field"><label>Priority</label><input type="number" name="priority" value="<?php echo h((string) ($editRule['priority'] ?? 100)); ?>"><small>Lower priority is matched first.</small></div>
                    <label class="check-row"><input type="checkbox" name="is_active" <?php echo (int) ($editRule['is_active'] ?? 1) === 1 ? 'checked' : ''; ?>> Active rule</label>
                    <div class="field" style="grid-column: 1 / -1;"><label>Notes</label><textarea name="notes"><?php echo h($editRule['notes'] ?? ''); ?></textarea></div>
                    <div class="proof-modal-actions" style="grid-column: 1 / -1;"><button class="button button-primary" type="submit">Save Pricing Plan</button><?php if ($editRule): ?><a class="button button-ghost" href="<?php echo h(app_url('admin/pricing.php')); ?>">Cancel Edit</a><?php endif; ?></div>
                </form>
            </section>
        </div>

        <div class="proof-modal" id="pricing-discount-modal" hidden data-proof-modal>
            <div class="proof-modal-backdrop" data-proof-modal-close></div>
            <section class="proof-modal-panel" role="dialog" aria-modal="true" aria-labelledby="pricing-discount-modal-title">
                <div class="proof-modal-header">
                    <div>
                        <h2 id="pricing-discount-modal-title">Special Individual Discount</h2>
                        <p>Allocate a one-off discount to a specific applicant through the portal.</p>
                    </div>
                    <button class="icon-button" type="button" data-proof-modal-close aria-label="Close discount form">x</button>
                </div>
                <form method="post" class="form-grid">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="save_discount">
                    <div class="field"><label>Applicant</label><input type="search" name="application_search" list="discount-applicant-options" placeholder="Search applicant, business, or email" autocomplete="off" required data-applicant-search><input type="hidden" name="application_id" data-applicant-id><small>Start typing, then select an applicant from the search results.</small></div>
                    <div class="form-grid two">
                        <div class="field"><label>Discount Type</label><select name="discount_type"><option value="fixed">Fixed UGX</option><option value="percent">Percent</option></select></div>
                        <div class="field"><label>Discount Value</label><input type="number" name="discount_value" min="0" step="1000" required></div>
                    </div>
                    <div class="field"><label>Reason</label><textarea name="reason" placeholder="Scholarship, sponsor support, committee waiver..."></textarea></div>
                    <div class="proof-modal-actions"><button class="button button-primary" type="submit">Save Discount</button></div>
                </form>
            </section>
        </div>
        <datalist id="business-nature-options"><?php foreach ($distinct('business_nature') as $value): ?><option value="<?php echo h($value); ?>"></option><?php endforeach; ?></datalist>
        <datalist id="student-status-options"><?php foreach ($distinct('student_status') as $value): ?><option value="<?php echo h($value); ?>"></option><?php endforeach; ?></datalist>
        <datalist id="discount-applicant-options">
            <?php foreach ($applications as $application): ?>
                <?php $applicantLabel = trim($application['full_name'] . ' | ' . ($application['business_name'] ?: 'No business') . ' | ' . $application['email'] . ' (#' . (int) $application['id'] . ')'); ?>
                <option value="<?php echo h($applicantLabel); ?>" data-application-id="<?php echo (int) $application['id']; ?>"></option>
            <?php endforeach; ?>
        </datalist>

        <section class="panel" style="margin-top: 22px;">
            <h2>Pricing Rules</h2>
            <div class="table-scroll"><table><thead><tr><th>Rule</th><th>Match</th><th>Price</th><th>Priority</th><th>Status</th><th>Action</th></tr></thead><tbody>
            <?php foreach ($rules as $rule): ?>
                <tr>
                    <td data-label="Rule"><strong><?php echo h($rule['rule_name']); ?></strong><br><small><?php echo h($rule['notes'] ?? ''); ?></small></td>
                    <td data-label="Match">Business: <?php echo h($rule['business_nature_match'] ?: 'Any'); ?><br><small>Student: <?php echo h($rule['student_status_match'] ?: 'Any'); ?></small></td>
                    <td data-label="Price"><?php echo h(ugx_money((float) $rule['price_per_stall'])); ?></td>
                    <td data-label="Priority"><?php echo (int) $rule['priority']; ?></td>
                    <td data-label="Status"><?php echo badge((int) $rule['is_active'] === 1 ? 'Active' : 'Inactive'); ?></td>
                    <td data-label="Action"><div class="icon-action-row"><a class="table-icon-button" href="<?php echo h(app_url('admin/pricing.php?edit_rule=' . (int) $rule['id'])); ?>" aria-label="Edit pricing rule" title="Edit">&#9998;</a><form method="post" data-confirm="Delete this pricing rule?"><?php echo csrf_field(); ?><input type="hidden" name="action" value="delete_rule"><input type="hidden" name="rule_id" value="<?php echo (int) $rule['id']; ?>"><button class="table-icon-button danger" type="submit" aria-label="Delete pricing rule" title="Delete">&#128465;</button></form></div></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rules): ?><tr><td colspan="6" class="empty-state">No pricing rules configured.</td></tr><?php endif; ?>
            </tbody></table></div>
        </section>

        <section class="panel" style="margin-top: 22px;">
            <h2>Special Discounts</h2>
            <div class="table-scroll"><table><thead><tr><th>Applicant</th><th>Discount</th><th>Reason</th><th>Action</th></tr></thead><tbody>
            <?php foreach ($discounts as $discount): ?>
                <tr>
                    <td data-label="Applicant"><strong><?php echo h($discount['full_name']); ?></strong><br><small><?php echo h($discount['business_name'] ?? 'No business'); ?></small></td>
                    <td data-label="Discount"><?php echo $discount['discount_type'] === 'percent' ? h(number_format((float) $discount['discount_value'], 2) . '%') : h(ugx_money((float) $discount['discount_value'])); ?></td>
                    <td data-label="Reason"><?php echo h($discount['reason'] ?? 'No reason recorded'); ?></td>
                    <td data-label="Action"><div class="icon-action-row"><form method="post" data-confirm="Remove this special discount?"><?php echo csrf_field(); ?><input type="hidden" name="action" value="delete_discount"><input type="hidden" name="discount_id" value="<?php echo (int) $discount['id']; ?>"><button class="table-icon-button danger" type="submit" aria-label="Delete special discount" title="Delete">&#128465;</button></form></div></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$discounts): ?><tr><td colspan="4" class="empty-state">No individual discounts allocated yet.</td></tr><?php endif; ?>
            </tbody></table></div>
        </section>

        <section class="panel" style="margin-top: 22px;">
            <h2>Applicant Pricing Preview</h2>
            <div class="table-scroll"><table><thead><tr><th>Applicant</th><th>Criteria</th><th>Rule</th><th>Subtotal</th><th>Discount</th><th>Total Due</th></tr></thead><tbody>
            <?php foreach ($previewRows as $row): ?>
                <?php $pricing = $row['pricing']; ?>
                <tr>
                    <td data-label="Applicant"><strong><?php echo h($row['full_name']); ?></strong><br><small><?php echo h($row['business_name'] ?? 'No business'); ?></small></td>
                    <td data-label="Criteria"><?php echo h($row['business_nature'] ?? 'No business nature'); ?><br><small><?php echo h($row['student_status'] ?? 'No student status'); ?> / <?php echo (int) ($pricing['stall_count'] ?? 1); ?> stall(s)</small></td>
                    <td data-label="Rule"><?php echo h($pricing['rule']['rule_name'] ?? 'No rule'); ?><br><small><?php echo h(ugx_money((float) ($pricing['price_per_stall'] ?? 0))); ?> per stall</small></td>
                    <td data-label="Subtotal"><?php echo h(ugx_money((float) ($pricing['subtotal'] ?? 0))); ?></td>
                    <td data-label="Discount"><?php echo h(ugx_money((float) ($pricing['discount_amount'] ?? 0))); ?></td>
                    <td data-label="Total Due"><strong><?php echo h(ugx_money((float) ($pricing['total'] ?? 0))); ?></strong></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$previewRows): ?><tr><td colspan="6" class="empty-state">No portal applications available for pricing preview.</td></tr><?php endif; ?>
            </tbody></table></div>
        </section>
    </main>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
