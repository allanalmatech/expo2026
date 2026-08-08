<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
$user = require_login('admin');
$active = 'dashboard';
$pageTitle = 'Admin Dashboard';

$stats = [
    'Total Applicants' => count_rows('SELECT COUNT(*) FROM users WHERE role = "applicant"'),
    'Student Applicants' => count_rows('SELECT COUNT(*) FROM applications a LEFT JOIN form_responses fr ON fr.id = a.form_response_id WHERE LOWER(COALESCE(fr.student_status, fr.applicant_type, "")) LIKE "%student%" AND LOWER(COALESCE(fr.student_status, fr.applicant_type, "")) NOT LIKE "%non%"'),
    'Non-Student Applicants' => count_rows('SELECT COUNT(*) FROM applications a LEFT JOIN form_responses fr ON fr.id = a.form_response_id WHERE LOWER(COALESCE(fr.student_status, fr.applicant_type, "")) LIKE "%non%"'),
    'Pending Review' => count_rows('SELECT COUNT(*) FROM applications WHERE application_status = "Pending Review"'),
    'Approved' => count_rows('SELECT COUNT(*) FROM applications WHERE application_status = "Approved"'),
    'Rejected' => count_rows('SELECT COUNT(*) FROM applications WHERE application_status = "Rejected"'),
    'Paid' => count_rows('SELECT COUNT(*) FROM applications WHERE payment_status = "Payment Received"'),
    'Pending Payment Verification' => count_rows('SELECT COUNT(*) FROM applications WHERE payment_status = "Pending Verification"'),
    'Stalls Allocated' => count_rows('SELECT COUNT(*) FROM stalls WHERE is_allocated = 1'),
    'Available Stalls' => count_rows('SELECT COUNT(*) FROM stalls WHERE is_allocated = 0'),
];

$recent = fetch_admin_applications(db(), [], 6);

require_once __DIR__ . '/../includes/header.php';
?>
<div class="app-shell">
    <?php require __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="app-main">
        <div class="page-header">
            <div>
                <h1>Management Dashboard</h1>
                <p>Overview of imported vendors, payments, reviews, and stall allocation.</p>
            </div>
            <div class="header-actions">
                <a class="button button-primary" href="<?php echo h(app_url('admin/import-responses.php')); ?>">Import Responses</a>
                <a class="button button-ghost" href="<?php echo h(app_url('admin/reports.php')); ?>">Export Reports</a>
            </div>
        </div>

        <div class="stat-grid">
            <?php foreach ($stats as $label => $value): ?>
                <article class="stat-card">
                    <span class="label"><?php echo h($label); ?></span>
                    <strong><?php echo number_format($value); ?></strong>
                </article>
            <?php endforeach; ?>
        </div>

        <section class="panel">
            <div class="page-header">
                <h2>Recent Applications</h2>
                <a class="link-strong" href="<?php echo h(app_url('admin/applications.php')); ?>">Open manager</a>
            </div>
            <div class="table-scroll">
                <table>
                    <thead><tr><th>Name</th><th>Business</th><th>Contact</th><th>Payment</th><th>Status</th><th>Assigned Stall</th><th>Action</th></tr></thead>
                    <tbody><?php echo render_admin_application_rows($recent, false); ?></tbody>
                </table>
            </div>
        </section>
    </main>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
