<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
$user = require_login('applicant');
$active = 'profile';
$pageTitle = 'Profile';

$statement = db()->prepare('SELECT fr.* FROM users u LEFT JOIN form_responses fr ON fr.id = u.form_response_id WHERE u.id = ? LIMIT 1');
$statement->execute([(int) $user['id']]);
$response = $statement->fetch() ?: [];

$groups = [
    'Applicant Details' => ['full_name', 'email', 'phone', 'student_status', 'institution', 'program', 'year_of_study'],
    'Business Details' => ['business_name', 'business_nature', 'business_description', 'applicant_type'],
    'Stall Requirements' => ['stall_type', 'number_of_stalls', 'electricity_needed', 'equipment_needed', 'table_chair_request', 'branding_space_needed'],
    'Payment and Agreement' => ['preferred_payment_method', 'proof_of_payment_url', 'rules_agreement'],
];

$labels = form_response_import_fields();

require_once __DIR__ . '/../includes/header.php';
?>
<div class="app-shell">
    <?php require __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="app-main">
        <div class="page-header">
            <div>
                <h1>Imported Profile</h1>
                <p>These details were imported from your Google Form response. Contact the committee if a correction is needed.</p>
            </div>
        </div>

        <div class="card-grid">
            <?php foreach ($groups as $title => $fields): ?>
                <section class="content-card">
                    <h2><?php echo h($title); ?></h2>
                    <div class="stack">
                        <?php foreach ($fields as $field): ?>
                            <div class="summary-item">
                                <span><?php echo h($labels[$field] ?? ucfirst(str_replace('_', ' ', $field))); ?></span>
                                <?php if ($field === 'proof_of_payment_url' && !empty($response[$field])): ?>
                                    <strong><a class="link-strong" href="<?php echo h($response[$field]); ?>" target="_blank" rel="noopener">View uploaded proof</a></strong>
                                <?php else: ?>
                                    <strong><?php echo h((string) ($response[$field] ?? 'Not provided')); ?></strong>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>
        </div>
    </main>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
