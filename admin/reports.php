<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
$admin = require_login('admin');
$active = 'reports';
$pageTitle = 'Reports';
$pdo = db();

if (isset($_GET['export'])) {
    $type = (string) $_GET['export'];
    if (!in_array($type, ['applicants', 'payments', 'stalls'], true)) {
        $type = 'applicants';
    }
    $filename = 'freshers-expo-' . $type . '-' . date('Ymd-His') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');

    if ($type === 'payments') {
        fputcsv($out, ['Applicant', 'Email', 'Business', 'Application Payment Status', 'Upload Status', 'File', 'Uploaded At', 'Admin Comment']);
        $rows = $pdo->query('SELECT u.full_name, u.email, fr.business_name, a.payment_status, pu.verification_status, pu.original_filename, pu.uploaded_at, pu.admin_comment FROM payment_uploads pu INNER JOIN users u ON u.id = pu.user_id INNER JOIN applications a ON a.id = pu.application_id LEFT JOIN form_responses fr ON fr.id = a.form_response_id ORDER BY pu.uploaded_at DESC');
        foreach ($rows as $row) { fputcsv($out, $row); }
    } elseif ($type === 'stalls') {
        fputcsv($out, ['Stall Number', 'Location', 'Type', 'Tent Group', 'Tent Type', 'Arrangement', 'U-Layout Zone', 'Allocated', 'Applicant', 'Business']);
        $rows = $pdo->query('SELECT s.stall_number, s.stall_location, s.stall_type, s.tent_group_code, s.tent_code, s.arrangement_key, s.layout_zone, IF(s.is_allocated = 1, "Yes", "No") AS allocated, u.full_name, fr.business_name FROM stalls s LEFT JOIN users u ON u.id = s.allocated_to_user_id LEFT JOIN applications a ON a.user_id = u.id LEFT JOIN form_responses fr ON fr.id = a.form_response_id ORDER BY s.stall_number ASC');
        foreach ($rows as $row) { fputcsv($out, $row); }
    } else {
        fputcsv($out, ['Name', 'Email', 'Phone', 'Business', 'Applicant Type', 'Stall Type', 'Application Status', 'Payment Status', 'Compliance Status', 'Assigned Stall']);
        $rows = $pdo->query('SELECT u.full_name, u.email, u.phone, fr.business_name, fr.applicant_type, fr.stall_type, a.application_status, a.payment_status, a.compliance_status, a.assigned_stall_number FROM applications a INNER JOIN users u ON u.id = a.user_id LEFT JOIN form_responses fr ON fr.id = a.form_response_id ORDER BY u.full_name ASC');
        foreach ($rows as $row) { fputcsv($out, $row); }
    }
    fclose($out);
    exit;
}

require_once __DIR__ . '/../includes/header.php';
?>
<div class="app-shell">
    <?php require __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="app-main">
        <div class="page-header">
            <div><h1>Reports</h1><p>Export operational CSV reports for committee review.</p></div>
        </div>
        <div class="card-grid">
            <section class="content-card"><h2>Applicants</h2><p>Full applicant list with application, payment, compliance, and stall status.</p><a class="button button-primary" href="<?php echo h(app_url('admin/reports.php?export=applicants')); ?>">Export Applicants CSV</a></section>
            <section class="content-card"><h2>Payments</h2><p>Uploaded payment proofs and verification decisions.</p><a class="button button-primary" href="<?php echo h(app_url('admin/reports.php?export=payments')); ?>">Export Payments CSV</a></section>
            <section class="content-card"><h2>Approved Stall List</h2><p>Allocated and available stalls with tent groups, U-layout zones, and linked applicants.</p><a class="button button-primary" href="<?php echo h(app_url('admin/reports.php?export=stalls')); ?>">Export Stall CSV</a></section>
        </div>
    </main>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
