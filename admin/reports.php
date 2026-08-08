<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
$admin = require_login('admin');
$active = 'reports';
$pageTitle = 'Reports';
$pdo = db();

if (isset($_GET['export'])) {
    $type = (string) $_GET['export'];
    if (!in_array($type, ['applicants', 'payments', 'payments_xls', 'stalls'], true)) {
        $type = 'applicants';
    }

    if ($type === 'payments_xls') {
        ensure_payment_upload_schema();
        $filename = 'freshers-expo-updated-payments-' . date('Ymd-His') . '.xls';
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $rows = $pdo->query(
            'SELECT fr.*, a.id AS application_id, a.payment_status, a.application_status, a.assigned_stall_number,
                    u.full_name AS account_name, u.email AS account_email, u.phone AS account_phone, u.account_status
             FROM form_responses fr
             LEFT JOIN applications a ON a.form_response_id = fr.id
             LEFT JOIN users u ON u.id = a.user_id
             WHERE COALESCE(fr.sheet_paid_amount, 0) > 0
                OR EXISTS (SELECT 1 FROM payment_uploads pu WHERE pu.application_id = a.id AND pu.verification_status = "Verified" AND pu.payment_amount > 0)
                OR EXISTS (SELECT 1 FROM payment_receipts prp WHERE (prp.application_id = a.id OR prp.form_response_id = fr.id) AND prp.paid_amount > 0)
             ORDER BY COALESCE(fr.payment_recorded_at, fr.submitted_at, fr.updated_at, fr.created_at) DESC, fr.id DESC'
        )->fetchAll();
        $receiptRefs = $pdo->prepare(
            'SELECT GROUP_CONCAT(receipt_reference ORDER BY COALESCE(paid_at, created_at) DESC SEPARATOR ", ")
             FROM payment_receipts
             WHERE (? > 0 AND application_id = ?) OR (? > 0 AND form_response_id = ?)'
        );
        $latestReceipt = $pdo->prepare(
            'SELECT *
             FROM payment_receipts
             WHERE (? > 0 AND application_id = ?) OR (? > 0 AND form_response_id = ?)
             ORDER BY COALESCE(paid_at, created_at) DESC, id DESC
             LIMIT 1'
        );

        echo "<!doctype html><html><head><meta charset=\"utf-8\"><style>td{mso-number-format:'\\@';}</style></head><body><table border=\"1\">";
        echo '<thead><tr>';
        foreach (['No.', 'Names', 'Phone number', 'Email', 'Business Name', 'Business Nature', 'Stall Number', 'Qty', 'Paid', 'Balance', 'Totals', 'Max Staff', 'Payment Method', 'Transaction ID', 'Handled By', 'Time Stamp', 'Payment Status', 'Account Status', 'Receipt Reference'] as $heading) {
            echo '<th>' . h($heading) . '</th>';
        }
        echo '</tr></thead><tbody>';

        $index = 1;
        foreach ($rows as $row) {
            $applicationId = (int) ($row['application_id'] ?? 0);
            $responseId = (int) ($row['id'] ?? 0);
            if ($applicationId > 0) {
                $totals = payment_upload_totals($pdo, $applicationId);
                $paid = (float) ($totals['verified'] ?? 0);
                $balance = (float) ($totals['balance'] ?? 0);
                $totalDue = (float) ($totals['total_due'] ?? 0);
            } else {
                $baseline = form_response_payment_baseline($row);
                $paid = (float) $baseline['paid'];
                $balance = (float) $baseline['balance'];
                $totalDue = (float) $baseline['total'];
            }

            $receiptRefs->execute([$applicationId, $applicationId, $responseId, $responseId]);
            $references = (string) ($receiptRefs->fetchColumn() ?: '');
            $latestReceipt->execute([$applicationId, $applicationId, $responseId, $responseId]);
            $receipt = $latestReceipt->fetch() ?: [];

            $paymentStatus = (string) ($row['payment_status'] ?? '');
            if ($paymentStatus === '') {
                $paymentStatus = $paid > 0 ? ($balance <= 0 ? 'Payment Received' : 'Pending Verification') : 'Not Paid';
            }

            $values = [
                $index++,
                trim((string) ($row['account_name'] ?? '')) ?: (string) ($row['full_name'] ?? ''),
                trim((string) ($row['account_phone'] ?? '')) ?: (string) ($row['phone'] ?? ''),
                trim((string) ($row['account_email'] ?? '')) ?: (string) ($row['email'] ?? ''),
                (string) ($row['business_name'] ?? ''),
                (string) ($row['business_nature'] ?? ''),
                (string) ($row['assigned_stall_number'] ?? $row['stall_type'] ?? ''),
                (string) ($row['number_of_stalls'] ?? ''),
                (string) (int) round($paid),
                (string) (int) round($balance),
                (string) (int) round($totalDue),
                (string) ($row['max_staff'] ?? ''),
                trim((string) ($receipt['payment_method'] ?? '')) ?: (string) ($row['preferred_payment_method'] ?? ''),
                trim((string) ($receipt['transaction_id'] ?? '')) ?: (string) ($row['payment_transaction_id'] ?? ''),
                trim((string) ($receipt['received_by'] ?? '')) ?: (string) ($row['payment_handled_by'] ?? ''),
                format_date((string) (($receipt['paid_at'] ?? '') ?: ($row['payment_recorded_at'] ?? $row['submitted_at'] ?? $row['created_at'] ?? ''))),
                $paymentStatus,
                (string) ($row['account_status'] ?? 'No Portal Account'),
                $references,
            ];

            echo '<tr>';
            foreach ($values as $value) {
                echo '<td>' . h((string) $value) . '</td>';
            }
            echo '</tr>';
        }
        echo '</tbody></table></body></html>';
        exit;
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
            <section class="content-card"><h2>Payments</h2><p>Uploaded payment proofs, synced paid-register data, balances, receipts, and verification decisions.</p><a class="button button-primary" href="<?php echo h(app_url('admin/reports.php?export=payments_xls')); ?>">Export Updated XLS</a> <a class="button button-ghost" href="<?php echo h(app_url('admin/reports.php?export=payments')); ?>">Export Payments CSV</a></section>
            <section class="content-card"><h2>Approved Stall List</h2><p>Allocated and available stalls with tent groups, U-layout zones, and linked applicants.</p><a class="button button-primary" href="<?php echo h(app_url('admin/reports.php?export=stalls')); ?>">Export Stall CSV</a></section>
        </div>
    </main>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
