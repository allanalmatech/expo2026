<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
$user = require_login('applicant');
$active = 'compliance';
$pageTitle = 'Compliance';
$pdo = db();

$applicationStatement = $pdo->prepare('SELECT * FROM applications WHERE user_id = ? LIMIT 1');
$applicationStatement->execute([(int) $user['id']]);
$application = $applicationStatement->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = (string) ($_POST['action'] ?? 'confirm');
    if (!$application) {
        set_flash('error', 'No application record was found for your account.');
        redirect('applicant/compliance.php');
    }

    if ($action === 'confirm' && ($application['compliance_status'] ?? '') === 'Signed') {
        set_flash('success', 'Compliance already accepted.');
        redirect('applicant/compliance.php');
    }

    $filePath = null;
    $status = 'Signed';
    $signedAt = date('Y-m-d H:i:s');

    if (!empty($_FILES['document']['name'])) {
        $error = '';
        if (!validate_uploaded_file($_FILES['document'], allowed_upload_extensions(), $error)) {
            set_flash('error', $error);
            redirect('applicant/compliance.php');
        }
        $dir = ensure_upload_dir('compliance');
        $name = secure_upload_name((string) $_FILES['document']['name']);
        if (!move_uploaded_file((string) $_FILES['document']['tmp_name'], $dir . '/' . $name)) {
            set_flash('error', 'The compliance document could not be saved.');
            redirect('applicant/compliance.php');
        }
        $filePath = 'uploads/compliance/' . $name;
        $status = 'Pending Review';
        $signedAt = null;
    }

    $existing = $pdo->prepare('SELECT id FROM compliance_documents WHERE user_id = ? AND application_id = ? LIMIT 1');
    $existing->execute([(int) $user['id'], (int) $application['id']]);
    $documentId = $existing->fetchColumn();

    if ($documentId) {
        $sql = 'UPDATE compliance_documents SET document_status = ?, signed_at = ?, updated_at = NOW()';
        $params = [$status, $signedAt];
        if ($filePath) {
            $sql .= ', file_path = ?';
            $params[] = $filePath;
        }
        $sql .= ' WHERE id = ?';
        $params[] = (int) $documentId;
        $pdo->prepare($sql)->execute($params);
    } else {
        $insert = $pdo->prepare('INSERT INTO compliance_documents (user_id, application_id, document_status, signed_at, file_path, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())');
        $insert->execute([(int) $user['id'], (int) $application['id'], $status, $signedAt, $filePath]);
    }

    $pdo->prepare('UPDATE applications SET compliance_status = ?, updated_at = NOW() WHERE id = ?')->execute([$status, (int) $application['id']]);
    set_flash('success', $status === 'Signed' ? 'Compliance willingness recorded.' : 'Compliance document uploaded for review.');
    redirect('applicant/compliance.php');
}

$document = null;
if ($application) {
    $docStatement = $pdo->prepare('SELECT * FROM compliance_documents WHERE application_id = ? ORDER BY updated_at DESC LIMIT 1');
    $docStatement->execute([(int) $application['id']]);
    $document = $docStatement->fetch() ?: null;
}

require_once __DIR__ . '/../includes/header.php';
?>
<div class="app-shell">
    <?php require __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="app-main">
        <div class="page-header">
            <div>
                <h1>Compliance</h1>
                <p>Review the event rules and confirm willingness to comply before final stall allocation.</p>
            </div>
            <?php echo badge($application['compliance_status'] ?? 'Not Signed'); ?>
        </div>

        <div class="dashboard-grid">
            <section class="panel">
                <h2>Rules Summary</h2>
                <p><?php echo h(setting('rules_text', 'By participating in the event, all stall holders agree to operate only within their allocated space, keep their stall clean and safe, avoid selling illegal or unauthorized items, follow hygiene requirements where applicable, respect university property, follow security and electrical safety instructions, avoid excessive noise, and comply with all guidance from the organizing committee. Final stall allocation is subject to approval and signing of the compliance document.')); ?></p>
                <?php if (($application['compliance_status'] ?? '') === 'Signed'): ?>
                    <div class="accepted-state">
                        <span class="accepted-mark">✓</span>
                        <div>
                            <strong>Compliance accepted</strong>
                            <p>You have already confirmed willingness to comply with the event rules.</p>
                        </div>
                    </div>
                <?php else: ?>
                    <form method="post" class="form-grid" data-confirm="Confirm willingness to comply with the event rules?">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="confirm">
                        <button class="button button-primary" type="submit">Confirm Willingness to Comply</button>
                    </form>
                <?php endif; ?>
            </section>

            <aside class="panel">
                <h2>Signed Document</h2>
                <?php if ($document): ?>
                    <p><?php echo badge($document['document_status']); ?></p>
                    <?php if (!empty($document['file_path'])): ?><a class="link-strong" href="<?php echo h(app_url($document['file_path'])); ?>" target="_blank" rel="noopener">View uploaded document</a><?php endif; ?>
                <?php else: ?>
                    <p class="help-text">No signed document uploaded yet. Upload only if the committee requests it.</p>
                <?php endif; ?>
                <form method="post" enctype="multipart/form-data" class="form-grid" style="margin-top: 18px;">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="upload_document">
                    <div class="field">
                        <label for="document">Upload Signed Document</label>
                        <input type="file" id="document" name="document" accept=".pdf,.jpg,.jpeg,.png">
                    </div>
                    <button class="button button-ghost" type="submit">Upload for Review</button>
                </form>
            </aside>
        </div>
    </main>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
