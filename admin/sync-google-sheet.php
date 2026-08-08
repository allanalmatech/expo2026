<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

$admin = require_login('admin');
$active = 'sheet_sync';
$pageTitle = 'Google Sheet Sync';
$pdo = db();
$summary = null;
$activationSummary = null;

$token = setting('google_sheet_cron_token', '');
if ($token === '') {
    $token = bin2hex(random_bytes(24));
    save_setting('google_sheet_cron_token', $token);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = (string) ($_POST['action'] ?? 'save');

    if ($action === 'activate_paid') {
        $activationSummary = activate_paid_vendor_accounts($pdo);
        if (!empty($activationSummary['errors'])) {
            set_flash('error', 'Paid account activation completed with errors. Review the summary below.');
        } else {
            set_flash('success', 'Paid vendor accounts activated successfully.');
        }
    } else {

        save_setting('google_sheet_url', trim((string) ($_POST['google_sheet_url'] ?? '')));
        save_setting('google_sheet_gid', trim((string) ($_POST['google_sheet_gid'] ?? '0')) ?: '0');
        save_setting('google_sheet_auto_sync_enabled', isset($_POST['google_sheet_auto_sync_enabled']) ? '1' : '0');

        if (!empty($_POST['regenerate_token'])) {
            $token = bin2hex(random_bytes(24));
            save_setting('google_sheet_cron_token', $token);
        }

        if ($action === 'sync') {
            $summary = sync_google_sheet_responses((int) $admin['id']);
            if (!empty($summary['errors'])) {
                set_flash('error', 'Sheet sync completed with errors. Review the summary below.');
            } else {
                set_flash('success', 'Google Sheet synced successfully.');
            }
        } else {
            set_flash('success', 'Google Sheet sync settings saved.');
            redirect('admin/sync-google-sheet.php');
        }
    }
}

$values = [
    'google_sheet_url' => setting('google_sheet_url', default_vendor_payment_sheet_url()),
    'google_sheet_gid' => setting('google_sheet_gid', '0'),
    'google_sheet_auto_sync_enabled' => setting('google_sheet_auto_sync_enabled', '0'),
    'google_sheet_last_sync_at' => setting('google_sheet_last_sync_at', ''),
    'google_sheet_cron_token' => setting('google_sheet_cron_token', $token),
];
$csvUrl = google_sheet_csv_url($values['google_sheet_url'], $values['google_sheet_gid']);
$cronUrl = absolute_app_url('cron/sync-google-sheet.php?token=' . rawurlencode($values['google_sheet_cron_token']));

try {
    $logs = $pdo->query('SELECT * FROM sheet_sync_logs ORDER BY synced_at DESC LIMIT 10')->fetchAll();
} catch (Throwable $exception) {
    $logs = [];
}

require_once __DIR__ . '/../includes/header.php';
?>
<div class="app-shell">
    <?php require __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="app-main">
        <div class="page-header">
            <div>
                <h1>Google Sheet Sync</h1>
                <p>Automatically read the paid vendor register from Google Sheets, including paid amount, balance, max staff, and payment timestamp.</p>
            </div>
            <a class="button button-ghost" href="<?php echo h(app_url('admin/import-responses.php')); ?>">Manual CSV Import</a>
        </div>

        <?php if ($summary): ?>
            <section class="panel">
                <h2>Sync Summary</h2>
                <div class="stat-grid">
                    <article class="stat-card"><span class="label">Rows Processed</span><strong><?php echo number_format((int) $summary['processed']); ?></strong></article>
                    <article class="stat-card"><span class="label">New Records</span><strong><?php echo number_format((int) $summary['added']); ?></strong></article>
                    <article class="stat-card"><span class="label">Updated</span><strong><?php echo number_format((int) $summary['updated']); ?></strong></article>
                    <article class="stat-card"><span class="label">Skipped</span><strong><?php echo number_format((int) $summary['skipped']); ?></strong></article>
                </div>
                <?php foreach (($summary['errors'] ?? []) as $error): ?><p class="danger-text"><?php echo h($error); ?></p><?php endforeach; ?>
            </section>
        <?php endif; ?>

        <?php if ($activationSummary): ?>
            <section class="panel">
                <h2>Paid Account Activation Summary</h2>
                <div class="stat-grid">
                    <article class="stat-card"><span class="label">Paid Rows</span><strong><?php echo number_format((int) $activationSummary['processed']); ?></strong></article>
                    <article class="stat-card"><span class="label">Accounts Created</span><strong><?php echo number_format((int) $activationSummary['created']); ?></strong></article>
                    <article class="stat-card"><span class="label">Accounts Updated</span><strong><?php echo number_format((int) $activationSummary['updated']); ?></strong></article>
                    <article class="stat-card"><span class="label">Applications Created</span><strong><?php echo number_format((int) $activationSummary['applications_created']); ?></strong></article>
                    <article class="stat-card"><span class="label">Receipts Synced</span><strong><?php echo number_format((int) $activationSummary['receipts_synced']); ?></strong></article>
                    <article class="stat-card"><span class="label">Skipped</span><strong><?php echo number_format((int) $activationSummary['skipped']); ?></strong></article>
                </div>
                <?php foreach (($activationSummary['errors'] ?? []) as $error): ?><p class="danger-text"><?php echo h($error); ?></p><?php endforeach; ?>
            </section>
        <?php endif; ?>

        <div class="dashboard-grid">
            <section class="panel">
                <h2>Sheet Connection</h2>
                <form method="post" class="form-grid">
                    <?php echo csrf_field(); ?>
                    <div class="field">
                        <label>Google Sheet URL or Sheet ID</label>
                        <input name="google_sheet_url" value="<?php echo h($values['google_sheet_url']); ?>" placeholder="https://docs.google.com/spreadsheets/d/.../edit#gid=0">
                        <small>Use the paid vendor register link, a published CSV link, or the spreadsheet ID.</small>
                    </div>
                    <div class="form-grid two">
                        <div class="field">
                            <label>Worksheet GID</label>
                            <input name="google_sheet_gid" value="<?php echo h($values['google_sheet_gid']); ?>" placeholder="0">
                            <small>The number after `gid=` in the sheet URL.</small>
                        </div>
                        <div class="field">
                            <label>Automatic Cron Sync</label>
                            <label class="check-row"><input type="checkbox" name="google_sheet_auto_sync_enabled" value="1" <?php echo $values['google_sheet_auto_sync_enabled'] === '1' ? 'checked' : ''; ?>> Enable token-protected cron sync</label>
                        </div>
                    </div>
                    <?php if ($csvUrl): ?>
                        <div class="notice success"><strong>CSV feed:</strong> <?php echo h($csvUrl); ?></div>
                    <?php endif; ?>
                    <div class="header-actions">
                        <button class="button button-secondary" name="action" value="save" type="submit">Save Settings</button>
                        <button class="button button-primary" name="action" value="sync" type="submit">Sync Now</button>
                    </div>
                </form>
            </section>

            <aside class="panel dark-card">
                <h2>Automatic Cron URL</h2>
                <p>Set cPanel Cron Jobs to call this URL every 5 to 15 minutes:</p>
                <input readonly value="<?php echo h($cronUrl); ?>" onclick="this.select()">
                <p class="help-text">Example cron command:</p>
                <code>curl -fsS "<?php echo h($cronUrl); ?>" >/dev/null</code>
                <div class="divider"></div>
                <form method="post" data-confirm="Regenerate the sync token? Existing cron URLs will stop working.">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="google_sheet_url" value="<?php echo h($values['google_sheet_url']); ?>">
                    <input type="hidden" name="google_sheet_gid" value="<?php echo h($values['google_sheet_gid']); ?>">
                    <?php if ($values['google_sheet_auto_sync_enabled'] === '1'): ?><input type="hidden" name="google_sheet_auto_sync_enabled" value="1"><?php endif; ?>
                    <button class="button button-ghost" name="regenerate_token" value="1" type="submit">Regenerate Token</button>
                </form>
            </aside>
        </div>

        <section class="panel" style="margin-top: 22px;">
            <h2>Paid Vendor Account Activation</h2>
            <p>Creates or updates applicant logins for every synced sheet row with a paid amount. The normalized phone number is used as both username and password, and matching applications are approved.</p>
            <form method="post" data-confirm="Activate all paid vendor accounts and reset their passwords to their normalized phone numbers?">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="activate_paid">
                <button class="button button-primary" type="submit">Activate Paid Vendor Accounts</button>
            </form>
        </section>

        <section class="panel" style="margin-top: 22px;">
            <h2>How to Prepare the Paid Register</h2>
            <div class="feature-grid">
                <article class="content-card feature-card"><h3>1. Keep Headers Clear</h3><p>The importer detects headers like Names, Phone number, Business Name, Paid, Balance, Totals, Max Staff, Transaction ID, and Time Stamp.</p></article>
                <article class="content-card feature-card"><h3>2. Allow Read Access</h3><p>Share the sheet as "Anyone with the link can view" or use File > Share > Publish to web and choose CSV.</p></article>
                <article class="content-card feature-card"><h3>3. Sync Automatically</h3><p>The portal maps common header names automatically and matches vendors by normalized Uganda phone number, including slash-separated alternatives.</p></article>
            </div>
        </section>

        <section class="panel" style="margin-top: 22px;">
            <h2>Recent Sync Logs</h2>
            <div class="table-scroll">
                <table>
                    <thead><tr><th>Synced At</th><th>Rows</th><th>Added</th><th>Updated</th><th>Skipped</th><th>Errors</th></tr></thead>
                    <tbody>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td data-label="Synced At"><?php echo h(format_date($log['synced_at'])); ?></td>
                            <td data-label="Rows"><?php echo number_format((int) $log['rows_processed']); ?></td>
                            <td data-label="Added"><?php echo number_format((int) $log['new_records']); ?></td>
                            <td data-label="Updated"><?php echo number_format((int) $log['updated_records']); ?></td>
                            <td data-label="Skipped"><?php echo number_format((int) $log['skipped_rows']); ?></td>
                            <td data-label="Errors"><small><?php echo h(implode('; ', json_decode((string) $log['errors_json'], true) ?: [])); ?></small></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$logs): ?><tr><td colspan="6" class="empty-state">No sync logs yet.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
