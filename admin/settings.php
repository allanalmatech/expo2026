<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
$admin = require_login('admin');
$active = 'settings';
$pageTitle = 'Settings';

$keys = ['google_form_url', 'event_name', 'event_dates', 'contact_phone', 'contact_email', 'rules_text'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    foreach ($keys as $key) {
        save_setting($key, trim((string) ($_POST[$key] ?? '')));
    }
    set_flash('success', 'Settings updated.');
    redirect('admin/settings.php');
}

$values = [];
foreach ($keys as $key) {
    $values[$key] = setting($key, '');
}

require_once __DIR__ . '/../includes/header.php';
?>
<div class="app-shell">
    <?php require __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="app-main">
        <div class="page-header">
            <div><h1>Portal Settings</h1><p>Manage public links, event details, and compliance rules.</p></div>
        </div>
        <section class="panel">
            <form method="post" class="form-grid">
                <?php echo csrf_field(); ?>
                <div class="form-grid two">
                    <div class="field"><label>Google Form Link</label><input type="url" name="google_form_url" value="<?php echo h($values['google_form_url']); ?>"></div>
                    <div class="field"><label>Event Name</label><input name="event_name" value="<?php echo h($values['event_name']); ?>" placeholder="Freshers Expo 2026"></div>
                    <div class="field"><label>Event Dates</label><input name="event_dates" value="<?php echo h($values['event_dates']); ?>"></div>
                    <div class="field"><label>Contact Phone</label><input name="contact_phone" value="<?php echo h($values['contact_phone']); ?>"></div>
                    <div class="field"><label>Contact Email</label><input type="email" name="contact_email" value="<?php echo h($values['contact_email']); ?>"></div>
                </div>
                <div class="field"><label>Stall Rules Text</label><textarea name="rules_text"><?php echo h($values['rules_text']); ?></textarea></div>
                <button class="button button-primary" type="submit">Save Settings</button>
            </form>
        </section>
    </main>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
