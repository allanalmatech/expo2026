<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
$admin = require_login('admin');
$active = 'import';
$pageTitle = 'Import Responses';
$pdo = db();
$fields = form_response_import_fields();
$headers = [];
$summary = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'upload') {
    require_csrf();
    $error = '';
    if (!isset($_FILES['csv']) || !validate_uploaded_file($_FILES['csv'], ['csv'], $error)) {
        set_flash('error', $error ?: 'Please upload a valid CSV file.');
        redirect('admin/import-responses.php');
    }
    $dir = ensure_upload_dir('imports');
    $name = secure_upload_name((string) $_FILES['csv']['name']);
    $path = $dir . '/' . $name;
    if (!move_uploaded_file((string) $_FILES['csv']['tmp_name'], $path)) {
        set_flash('error', 'CSV file could not be saved.');
        redirect('admin/import-responses.php');
    }
    $handle = fopen($path, 'r');
    $headers = $handle ? (fgetcsv($handle) ?: []) : [];
    if (isset($headers[0])) {
        $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $headers[0]);
    }
    if ($handle) { fclose($handle); }
    if (!$headers) {
        set_flash('error', 'The CSV file does not contain a header row.');
        redirect('admin/import-responses.php');
    }
    $_SESSION['import_csv_path'] = $path;
    $_SESSION['import_csv_name'] = $_FILES['csv']['name'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'import') {
    require_csrf();
    $path = (string) ($_SESSION['import_csv_path'] ?? '');
    if ($path === '' || !is_file($path)) {
        set_flash('error', 'Upload a CSV file before importing.');
        redirect('admin/import-responses.php');
    }

    $mapping = $_POST['mapping'] ?? [];
    $summary = import_form_responses_from_csv_path($pdo, $path, $mapping);
    @unlink($path);
    unset($_SESSION['import_csv_path'], $_SESSION['import_csv_name']);
}

if (!$headers && !empty($_SESSION['import_csv_path']) && is_file((string) $_SESSION['import_csv_path'])) {
    $handle = fopen((string) $_SESSION['import_csv_path'], 'r');
    $headers = $handle ? (fgetcsv($handle) ?: []) : [];
    if (isset($headers[0])) {
        $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $headers[0]);
    }
    if ($handle) { fclose($handle); }
}

require_once __DIR__ . '/../includes/header.php';
?>
<div class="app-shell">
    <?php require __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="app-main">
        <div class="page-header">
            <div><h1>Import Google Form Responses</h1><p>Upload a Google Sheets CSV export, map columns, and import applicant responses.</p></div>
            <a class="button button-ghost" href="<?php echo h(app_url('admin/sync-google-sheet.php')); ?>">Automatic Sheet Sync</a>
        </div>

        <?php if ($summary): ?>
            <section class="panel">
                <h2>Import Summary</h2>
                <div class="stat-grid">
                    <article class="stat-card"><span class="label">Rows Processed</span><strong><?php echo number_format($summary['processed']); ?></strong></article>
                    <article class="stat-card"><span class="label">New Records</span><strong><?php echo number_format($summary['added']); ?></strong></article>
                    <article class="stat-card"><span class="label">Updated</span><strong><?php echo number_format($summary['updated']); ?></strong></article>
                    <article class="stat-card"><span class="label">Skipped</span><strong><?php echo number_format($summary['skipped']); ?></strong></article>
                </div>
                <?php foreach ($summary['errors'] as $error): ?><p class="danger-text"><?php echo h($error); ?></p><?php endforeach; ?>
            </section>
        <?php endif; ?>

        <div class="dashboard-grid">
            <section class="panel">
                <h2>1. Upload CSV</h2>
                <form method="post" enctype="multipart/form-data" class="form-grid">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="upload">
                    <div class="field"><label>Google Form CSV</label><input type="file" name="csv" accept=".csv" required></div>
                    <button class="button button-primary" type="submit">Upload and Map Columns</button>
                </form>
            </section>

            <section class="panel dark-card">
                <h2>Import Rules</h2>
                <p>Records are matched by lowercase email first, then normalized Uganda phone number. Existing responses are updated instead of duplicated.</p>
                <p>Applicants can create portal accounts only after their response exists in this import table.</p>
            </section>
        </div>

        <?php if ($headers): ?>
            <section class="panel" style="margin-top: 22px;">
                <h2>2. Map CSV Columns</h2>
                <p class="help-text">Uploaded file: <?php echo h($_SESSION['import_csv_name'] ?? 'CSV file'); ?></p>
                <form method="post" class="form-grid">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="import">
                    <div class="table-scroll">
                        <table>
                            <thead><tr><th>Portal Field</th><th>CSV Column</th></tr></thead>
                            <tbody>
                            <?php foreach ($fields as $field => $label): ?>
                                <tr>
                                    <td data-label="Portal Field"><?php echo h($label); ?></td>
                                    <td data-label="CSV Column">
                                        <select name="mapping[<?php echo h($field); ?>]">
                                            <option value="">Do not import</option>
                                            <?php $auto = auto_map_column($field, $headers); foreach ($headers as $index => $header): ?>
                                                <option value="<?php echo (int) $index; ?>" <?php echo (string) $index === $auto ? 'selected' : ''; ?>><?php echo h($header); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <button class="button button-primary" type="submit">Import Responses</button>
                </form>
            </section>
        <?php endif; ?>
    </main>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
