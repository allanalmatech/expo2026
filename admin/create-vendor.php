<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
$admin = require_login('admin');
$active = 'create_vendor';
$pageTitle = 'Create Vendor Account';
$pdo = db();
ensure_vendor_access_schema($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    try {
        $created = create_admin_vendor_account($pdo, $_POST, (int) $admin['id']);
        set_flash('success', 'Vendor account created and approved. Login phone: ' . $created['login'] . ' / Password: ' . $created['password']);
        redirect('admin/application-view.php?id=' . (int) $created['application_id']);
    } catch (Throwable $exception) {
        error_log('Admin vendor account creation failed: ' . $exception->getMessage());
        set_flash('error', $exception instanceof RuntimeException ? $exception->getMessage() : 'Vendor account could not be created.');
    }
}

require_once __DIR__ . '/../includes/header.php';
?>
<div class="app-shell">
    <?php require __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="app-main">
        <div class="page-header">
            <div>
                <h1>Create Vendor Account</h1>
                <p>Create an approved portal account, business profile, payment baseline, and direct credentials.</p>
            </div>
            <a class="button button-ghost" href="<?php echo h(app_url('admin/applications.php')); ?>">Back to Applicants</a>
        </div>

        <section class="panel">
            <form method="post" class="form-grid two" data-confirm="Create and approve this vendor account?">
                <?php echo csrf_field(); ?>

                <div class="field"><label>Vendor Name</label><input name="full_name" value="<?php echo h($_POST['full_name'] ?? ''); ?>" placeholder="Owner or contact person"></div>
                <div class="field"><label>Phone Number</label><input name="phone" value="<?php echo h($_POST['phone'] ?? ''); ?>" placeholder="0772 000000" required><small>This is the login username.</small></div>
                <div class="field"><label>Email</label><input type="email" name="email" value="<?php echo h($_POST['email'] ?? ''); ?>"></div>
                <div class="field"><label>Initial Password</label><input name="password" value="<?php echo h($_POST['password'] ?? ''); ?>" placeholder="Leave blank to use normalized phone"><small>Give this password directly to the vendor.</small></div>

                <div class="field"><label>Business Name</label><input name="business_name" value="<?php echo h($_POST['business_name'] ?? ''); ?>" required></div>
                <div class="field"><label>Business Nature</label><input name="business_nature" value="<?php echo h($_POST['business_nature'] ?? ''); ?>" required></div>
                <div class="field" style="grid-column: 1 / -1;"><label>Business Description</label><textarea name="business_description"><?php echo h($_POST['business_description'] ?? ''); ?></textarea></div>

                <div class="field"><label>Stall Type</label><input name="stall_type" value="<?php echo h($_POST['stall_type'] ?? ''); ?>" placeholder="Standard stall, tent slot, etc."></div>
                <div class="field"><label>Number of Stalls</label><input name="number_of_stalls" type="number" min="1" value="<?php echo h($_POST['number_of_stalls'] ?? '1'); ?>" required></div>
                <div class="field"><label>Max Staff</label><input name="max_staff" type="number" min="1" value="<?php echo h($_POST['max_staff'] ?? '2'); ?>" required></div>
                <div class="field"><label>Total Amount Due (UGX)</label><input name="total_due" type="number" min="1" step="1" value="<?php echo h($_POST['total_due'] ?? ''); ?>" required></div>

                <div class="field"><label>Amount Already Paid (UGX)</label><input name="paid_amount" type="number" min="0" step="1" value="<?php echo h($_POST['paid_amount'] ?? '0'); ?>"></div>
                <div class="field"><label>Payment Method</label><input name="payment_method" value="<?php echo h($_POST['payment_method'] ?? ''); ?>" placeholder="Mobile Money, Bank, Cash"></div>
                <div class="field"><label>Transaction ID</label><input name="transaction_id" value="<?php echo h($_POST['transaction_id'] ?? ''); ?>" placeholder="Reference number"></div>

                <div class="proof-modal-actions" style="grid-column: 1 / -1;">
                    <button class="button button-primary" type="submit">Create Approved Account</button>
                </div>
            </form>
        </section>
    </main>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
