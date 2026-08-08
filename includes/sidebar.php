<?php
declare(strict_types=1);

$user = current_user();
$role = $user['role'] ?? 'applicant';
$active = $active ?? '';

$adminNav = [
    'dashboard' => ['Dashboard', 'admin/dashboard.php'],
    'applications' => ['Applications', 'admin/applications.php'],
    'create_vendor' => ['Create Vendor', 'admin/create-vendor.php'],
    'payments' => ['Payments', 'admin/payments.php'],
    'pricing' => ['Pricing', 'admin/pricing.php'],
    'messages' => ['Messaging', 'admin/messages.php'],
    'stalls' => ['Stalls', 'admin/stalls.php'],
    'layout_designer' => ['Layout Designer', 'admin/layout-designer.php'],
    'reports' => ['Reports', 'admin/reports.php'],
    'import' => ['Import CSV', 'admin/import-responses.php'],
    'sheet_sync' => ['Sheet Sync', 'admin/sync-google-sheet.php'],
    'settings' => ['Settings', 'admin/settings.php'],
];

$applicantNav = [
    'dashboard' => ['Dashboard', 'applicant/dashboard.php'],
    'profile' => ['Profile', 'applicant/profile.php'],
    'payment' => ['Payment', 'applicant/payment.php'],
    'receipts' => ['Receipts', 'applicant/receipts.php'],
    'tags' => ['Staff Tags', 'applicant/tags.php'],
    'messages' => ['Messages', 'applicant/messages.php'],
    'compliance' => ['Compliance', 'applicant/compliance.php'],
    'stall' => ['Stall', 'applicant/stall.php'],
];

$nav = $role === 'admin' ? $adminNav : $applicantNav;
?>
<button class="mobile-sidebar-button" type="button" data-sidebar-toggle>Menu</button>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <strong><?php echo $role === 'admin' ? 'Management Portal' : APP_EVENT_NAME; ?></strong>
        <span><?php echo $role === 'admin' ? 'Expo 2026 Admin' : h($user['full_name'] ?? 'Applicant'); ?></span>
    </div>
    <nav class="sidebar-nav" aria-label="Portal navigation">
        <?php foreach ($nav as $key => [$label, $href]): ?>
            <a class="<?php echo $active === $key ? 'active' : ''; ?>" href="<?php echo h(app_url($href)); ?>">
                <span class="nav-dot"></span><?php echo h($label); ?>
            </a>
        <?php endforeach; ?>
    </nav>
    <div class="sidebar-footer">
        <div class="sidebar-zoom" aria-label="Interface zoom controls">
            <span>Zoom</span>
            <div class="sidebar-zoom-actions">
                <button type="button" data-ui-zoom="out" aria-label="Zoom out">-</button>
                <strong data-ui-zoom-label>100%</strong>
                <button type="button" data-ui-zoom="in" aria-label="Zoom in">+</button>
                <button type="button" data-ui-zoom="reset" aria-label="Reset zoom">Reset</button>
            </div>
        </div>
        <?php if ($role === 'admin'): ?>
            <a class="button button-secondary button-block" href="<?php echo h(app_url('admin/reports.php')); ?>">Export Data</a>
        <?php endif; ?>
        <a href="<?php echo h(app_url('public/logout.php')); ?>">Logout</a>
    </div>
</aside>
<div class="sidebar-backdrop" data-sidebar-close></div>
