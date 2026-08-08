<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
$user = require_login('admin');
$active = 'applications';
$pageTitle = 'Applications';
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = (string) ($_POST['bulk_action'] ?? '');
    $selected = $_POST['selected'] ?? [];
    if (!is_array($selected)) {
        $selected = [];
    }

    if ($action === 'delete') {
        if (!$selected) {
            set_flash('error', 'Select at least one application or synced sheet row to delete.');
            redirect('admin/applications.php');
        }

        try {
            $pdo->beginTransaction();
            $summary = delete_application_entries($pdo, $selected);
            $pdo->commit();
            $deleted = (int) $summary['applications'] + (int) $summary['responses'];
            set_flash('success', 'Deleted ' . number_format($deleted) . ' selected entr' . ($deleted === 1 ? 'y' : 'ies') . '.');
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Bulk application delete failed: ' . $exception->getMessage());
            set_flash('error', 'Selected entries could not be deleted.');
        }
        redirect('admin/applications.php');
    }
}

$filters = application_filters_from_request($_GET);
$listLimit = application_list_limit_from_request($_GET, 10);
$currentPage = application_page_from_request($_GET);
$paginationOffset = $listLimit > 0 ? ($currentPage - 1) * $listLimit : 0;
$rows = fetch_admin_applications($pdo, $filters, $listLimit > 0 ? $listLimit + 1 : 0, $paginationOffset);
$hasNextPage = $listLimit > 0 && count($rows) > $listLimit;
if ($hasNextPage) {
    $rows = array_slice($rows, 0, $listLimit);
}
$totalRecords = count_rows('SELECT COUNT(*) FROM applications') + count_rows('SELECT COUNT(*) FROM form_responses fr LEFT JOIN applications a ON a.form_response_id = fr.id WHERE a.id IS NULL');

$distinct = function (string $field) use ($pdo): array {
    $statement = $pdo->query('SELECT DISTINCT ' . $field . ' AS value FROM form_responses WHERE ' . $field . ' IS NOT NULL AND ' . $field . ' <> "" ORDER BY ' . $field . ' ASC LIMIT 80');
    return array_column($statement->fetchAll(), 'value');
};

require_once __DIR__ . '/../includes/header.php';
?>
<div class="app-shell">
    <?php require __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="app-main">
        <div class="page-header">
            <div>
                <h1>Applicant Manager</h1>
                <p>Search, filter, review, and update imported vendor profiles.</p>
            </div>
            <div class="header-actions">
                <a class="button button-secondary" href="<?php echo h(app_url('admin/messages.php')); ?>">Bulk Announcement</a>
                <a class="button button-ghost" href="<?php echo h(app_url('admin/messages.php')); ?>">Send Message</a>
                <a class="button button-primary" href="<?php echo h(app_url('admin/reports.php?export=applicants')); ?>">Export CSV</a>
            </div>
        </div>

        <div class="stat-grid">
            <article class="stat-card"><span class="label">Total Records</span><strong><?php echo number_format($totalRecords); ?></strong></article>
            <article class="stat-card"><span class="label">Portal Accounts</span><strong><?php echo number_format(count_rows('SELECT COUNT(*) FROM users WHERE role = "applicant"')); ?></strong></article>
            <article class="stat-card"><span class="label">Paid Vendors</span><strong><?php echo number_format(count_rows('SELECT COUNT(*) FROM applications WHERE payment_status = "Payment Received"')); ?></strong></article>
            <article class="stat-card"><span class="label">Sheet-Only Rows</span><strong><?php echo number_format(count_rows('SELECT COUNT(*) FROM form_responses fr LEFT JOIN applications a ON a.form_response_id = fr.id WHERE a.id IS NULL')); ?></strong></article>
        </div>

        <section class="panel">
            <form class="table-toolbar" method="get" data-filter-applications>
                <input type="hidden" name="list_size" value="<?php echo h($listLimit === 0 ? 'all' : (string) $listLimit); ?>" data-application-list-size-input>
                <input type="hidden" name="page" value="<?php echo (int) $currentPage; ?>" data-application-page-input>
                <input type="search" name="q" placeholder="Search applicants, businesses, emails, phones, stalls..." value="<?php echo h($filters['q']); ?>">
                <select name="application_status">
                    <option value="">Any application status</option>
                    <?php foreach (['Pending Review', 'Needs Correction', 'Approved', 'Rejected', 'Synced From Sheet'] as $option): ?><option <?php echo $filters['application_status'] === $option ? 'selected' : ''; ?>><?php echo h($option); ?></option><?php endforeach; ?>
                </select>
                <select name="payment_status">
                    <option value="">Any payment status</option>
                    <?php foreach (['Not Paid', 'Pending Verification', 'Payment Received', 'Payment Rejected'] as $option): ?><option <?php echo $filters['payment_status'] === $option ? 'selected' : ''; ?>><?php echo h($option); ?></option><?php endforeach; ?>
                </select>
                <select name="compliance_status">
                    <option value="">Any compliance status</option>
                    <?php foreach (['Not Signed', 'Signed', 'Pending Review'] as $option): ?><option <?php echo $filters['compliance_status'] === $option ? 'selected' : ''; ?>><?php echo h($option); ?></option><?php endforeach; ?>
                </select>
                <select name="student_status">
                    <option value="">Any student status</option>
                    <?php foreach ($distinct('student_status') as $option): ?><option value="<?php echo h($option); ?>" <?php echo $filters['student_status'] === $option ? 'selected' : ''; ?>><?php echo h($option); ?></option><?php endforeach; ?>
                </select>
                <select name="applicant_type">
                    <option value="">Any applicant type</option>
                    <?php foreach ($distinct('applicant_type') as $option): ?><option value="<?php echo h($option); ?>" <?php echo $filters['applicant_type'] === $option ? 'selected' : ''; ?>><?php echo h($option); ?></option><?php endforeach; ?>
                </select>
                <select name="business_nature">
                    <option value="">Any business nature</option>
                    <?php foreach ($distinct('business_nature') as $option): ?><option value="<?php echo h($option); ?>" <?php echo $filters['business_nature'] === $option ? 'selected' : ''; ?>><?php echo h($option); ?></option><?php endforeach; ?>
                </select>
                <select name="stall_type">
                    <option value="">Any stall type</option>
                    <?php foreach ($distinct('stall_type') as $option): ?><option value="<?php echo h($option); ?>" <?php echo $filters['stall_type'] === $option ? 'selected' : ''; ?>><?php echo h($option); ?></option><?php endforeach; ?>
                </select>
                <select name="electricity_needed">
                    <option value="">Electricity?</option>
                    <?php foreach ($distinct('electricity_needed') as $option): ?><option value="<?php echo h($option); ?>" <?php echo $filters['electricity_needed'] === $option ? 'selected' : ''; ?>><?php echo h($option); ?></option><?php endforeach; ?>
                </select>
            </form>

            <form method="post" data-bulk-applications-form data-confirm="Delete the selected application entries? This cannot be undone.">
                <?php echo csrf_field(); ?>
                <div class="bulk-action-bar">
                    <span><strong data-selected-count>0</strong> selected</span>
                    <select name="bulk_action" required>
                        <option value="">Bulk action</option>
                        <option value="delete">Delete selected</option>
                    </select>
                    <button class="button button-danger" type="submit">Apply</button>
                </div>
                <div class="table-scroll">
                    <table>
                        <thead><tr><th><input type="checkbox" data-select-all-applications aria-label="Select all visible entries"></th><th>Name</th><th>Business</th><th>Contact</th><th>Payment</th><th>Status</th><th>Assigned Stall</th><th>Action</th></tr></thead>
                        <tbody data-applications-body><?php echo render_admin_application_rows($rows); ?></tbody>
                    </table>
                </div>
                <div data-applications-pagination><?php echo render_admin_application_pagination($currentPage, $listLimit, $hasNextPage); ?></div>
            </form>
        </section>
    </main>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
