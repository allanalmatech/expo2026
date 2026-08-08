<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool
    {
        return $needle === '' || strpos($haystack, $needle) === 0;
    }
}

if (!function_exists('str_contains')) {
    function str_contains(string $haystack, string $needle): bool
    {
        return $needle === '' || strpos($haystack, $needle) !== false;
    }
}

function secure_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_name('freshers_expo_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

secure_session_start();

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function app_base_path(): string
{
    if (APP_URL !== '') {
        $path = parse_url(APP_URL, PHP_URL_PATH) ?: '';
        return rtrim($path, '/');
    }

    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    foreach (['/public/', '/applicant/', '/admin/', '/ajax/'] as $marker) {
        $position = strpos($script, $marker);
        if ($position !== false) {
            return rtrim(substr($script, 0, $position), '/');
        }
    }

    $directory = rtrim(str_replace('\\', '/', dirname($script)), '/');
    return $directory === '/' ? '' : $directory;
}

function app_url(string $path = ''): string
{
    if (APP_URL !== '') {
        return rtrim(APP_URL, '/') . '/' . ltrim($path, '/');
    }

    return app_base_path() . '/' . ltrim($path, '/');
}

function absolute_app_url(string $path = ''): string
{
    $url = app_url($path);
    if (preg_match('#^https?://#i', $url)) {
        return $url;
    }

    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $scheme = $secure ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host . $url;
}

function redirect(string $path): void
{
    header('Location: ' . app_url($path));
    exit;
}

function normalize_email(?string $email): ?string
{
    $email = strtolower(trim((string) $email));
    return $email === '' ? null : $email;
}

function normalize_phone(?string $phone): ?string
{
    $raw = trim((string) $phone);
    if (preg_match('/[\/,;|]|\s+or\s+/i', $raw)) {
        $parts = preg_split('/[\/,;|]|\s+or\s+/i', $raw) ?: [];
        foreach ($parts as $part) {
            $normalized = normalize_phone($part);
            if ($normalized !== null) {
                return $normalized;
            }
        }
    }

    $digits = preg_replace('/\D+/', '', (string) $phone);

    if ($digits === '') {
        return null;
    }

    if (strlen($digits) === 10 && str_starts_with($digits, '0')) {
        return '256' . substr($digits, 1);
    }

    if (strlen($digits) === 9 && str_starts_with($digits, '7')) {
        return '256' . $digits;
    }

    if (strlen($digits) === 13 && str_starts_with($digits, '2560')) {
        return '256' . substr($digits, 4);
    }

    return $digits;
}

function set_flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function get_flash_messages(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

function setting(string $key, string $default = ''): string
{
    try {
        $statement = db()->prepare('SELECT setting_value FROM portal_settings WHERE setting_key = ? LIMIT 1');
        $statement->execute([$key]);
        $value = $statement->fetchColumn();
        return $value === false ? $default : (string) $value;
    } catch (Throwable $exception) {
        error_log('Setting read failed: ' . $exception->getMessage());
        return $default;
    }
}

function save_setting(string $key, string $value): void
{
    $statement = db()->prepare(
        'INSERT INTO portal_settings (setting_key, setting_value, updated_at)
         VALUES (?, ?, NOW())
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()'
    );
    $statement->execute([$key, $value]);
}

function badge(string $value): string
{
    $class = status_class($value);
    return '<span class="badge ' . h($class) . '">' . h($value) . '</span>';
}

function status_class(?string $value): string
{
    $value = strtolower((string) $value);

    if (str_contains($value, 'approved') || str_contains($value, 'received') || str_contains($value, 'paid') || str_contains($value, 'signed') || str_contains($value, 'verified')) {
        return 'badge-success';
    }

    if (str_contains($value, 'pending') || str_contains($value, 'review')) {
        return 'badge-warning';
    }

    if (str_contains($value, 'reject') || str_contains($value, 'correction') || str_contains($value, 'not')) {
        return 'badge-danger';
    }

    return 'badge-muted';
}

function initials(?string $name): string
{
    $parts = preg_split('/\s+/', trim((string) $name));
    $letters = '';
    foreach ($parts as $part) {
        if ($part !== '') {
            $letters .= strtoupper(substr($part, 0, 1));
        }
        if (strlen($letters) >= 2) {
            break;
        }
    }
    return $letters !== '' ? $letters : 'FE';
}

function format_date(?string $date): string
{
    if (!$date) {
        return 'Not set';
    }

    $timestamp = strtotime($date);
    return $timestamp ? date('d M Y, H:i', $timestamp) : (string) $date;
}

function payment_proof_is_image(string $pathOrUrl): bool
{
    $path = parse_url($pathOrUrl, PHP_URL_PATH) ?: $pathOrUrl;
    $extension = strtolower(pathinfo((string) $path, PATHINFO_EXTENSION));
    return in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'], true);
}

function payment_proof_modal_id(string $rowKey): string
{
    return 'proof-modal-' . preg_replace('/[^A-Za-z0-9_-]+/', '-', $rowKey);
}

function payment_reupload_comment(string $comment = ''): string
{
    $comment = trim($comment);
    if ($comment !== '') {
        return str_starts_with($comment, 'Re-upload required:') ? $comment : 'Re-upload required: ' . $comment;
    }
    return 'Re-upload required: please upload a clearer or corrected payment proof.';
}

function payment_verification_label(string $status, ?string $comment = null): string
{
    if ($status === 'Rejected' && str_starts_with(trim((string) $comment), 'Re-upload required:')) {
        return 'Re-upload Required';
    }
    return $status;
}

function default_vendor_payment_sheet_url(): string
{
    return 'https://docs.google.com/spreadsheets/d/1cburWgn4UU6uo3nL7mHpA52Fpqvt7aWoKlo-v9j3Tl0/edit?usp=sharing';
}

function ensure_vendor_access_schema(?PDO $pdo = null): void
{
    $pdo ??= db();

    $formColumns = [
        'sheet_paid_amount' => 'ALTER TABLE form_responses ADD COLUMN sheet_paid_amount DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER proof_of_payment_url',
        'sheet_balance_due' => 'ALTER TABLE form_responses ADD COLUMN sheet_balance_due DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER sheet_paid_amount',
        'sheet_total_due' => 'ALTER TABLE form_responses ADD COLUMN sheet_total_due DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER sheet_balance_due',
        'max_staff' => 'ALTER TABLE form_responses ADD COLUMN max_staff INT UNSIGNED NULL AFTER sheet_total_due',
        'payment_transaction_id' => 'ALTER TABLE form_responses ADD COLUMN payment_transaction_id VARCHAR(120) NULL AFTER max_staff',
        'payment_handled_by' => 'ALTER TABLE form_responses ADD COLUMN payment_handled_by VARCHAR(120) NULL AFTER payment_transaction_id',
        'payment_recorded_at' => 'ALTER TABLE form_responses ADD COLUMN payment_recorded_at DATETIME NULL AFTER payment_handled_by',
    ];

    foreach ($formColumns as $column => $sql) {
        if (!db_column_exists($pdo, 'form_responses', $column)) {
            $pdo->exec($sql);
        }
    }

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS password_reset_tokens (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            token_hash CHAR(64) NOT NULL,
            expires_at DATETIME NOT NULL,
            used_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_password_reset_token_hash (token_hash),
            INDEX idx_password_reset_user (user_id),
            CONSTRAINT fk_password_reset_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS payment_receipts (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            application_id INT UNSIGNED NULL,
            form_response_id INT UNSIGNED NULL,
            payment_upload_id INT UNSIGNED NULL,
            user_id INT UNSIGNED NULL,
            receipt_reference VARCHAR(40) NOT NULL,
            receipt_token CHAR(64) NOT NULL,
            source_type VARCHAR(30) NOT NULL DEFAULT "sheet",
            paid_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
            balance_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
            total_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
            payment_method VARCHAR(120) NULL,
            transaction_id VARCHAR(120) NULL,
            payment_description VARCHAR(255) NULL,
            received_by VARCHAR(120) NULL,
            paid_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_payment_receipt_reference (receipt_reference),
            UNIQUE KEY uniq_payment_receipt_token (receipt_token),
            UNIQUE KEY uniq_payment_receipt_upload (payment_upload_id),
            INDEX idx_payment_receipt_application (application_id),
            INDEX idx_payment_receipt_response (form_response_id),
            INDEX idx_payment_receipt_source (source_type),
            CONSTRAINT fk_payment_receipt_application FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE SET NULL,
            CONSTRAINT fk_payment_receipt_response FOREIGN KEY (form_response_id) REFERENCES form_responses(id) ON DELETE SET NULL,
            CONSTRAINT fk_payment_receipt_upload FOREIGN KEY (payment_upload_id) REFERENCES payment_uploads(id) ON DELETE SET NULL,
            CONSTRAINT fk_payment_receipt_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS attendant_tags (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            application_id INT UNSIGNED NOT NULL,
            tag_token CHAR(64) NOT NULL,
            staff_name VARCHAR(190) NOT NULL,
            staff_role VARCHAR(120) NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            verified_count INT UNSIGNED NOT NULL DEFAULT 0,
            last_verified_at DATETIME NULL,
            revoked_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_attendant_tag_token (tag_token),
            INDEX idx_attendant_application (application_id),
            INDEX idx_attendant_user_active (user_id, is_active),
            CONSTRAINT fk_attendant_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_attendant_application FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
}

function parse_money_amount(?string $value): float
{
    $clean = preg_replace('/[^0-9.\-]+/', '', (string) $value);
    if ($clean === '' || $clean === '-' || $clean === '.') {
        return 0.0;
    }
    return max(0.0, (float) $clean);
}

function parse_sheet_datetime(?string $value): ?string
{
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }
    foreach (['d/m/Y H:i', 'd/m/Y', 'd-m-Y H:i', 'd-m-Y', 'Y-m-d H:i:s', 'Y-m-d'] as $format) {
        $date = DateTime::createFromFormat($format, $value);
        if ($date instanceof DateTime) {
            return $date->format('Y-m-d H:i:s');
        }
    }
    $timestamp = strtotime($value);
    return $timestamp ? date('Y-m-d H:i:s', $timestamp) : null;
}

function form_response_payment_baseline(array $response): array
{
    $paid = max(0.0, (float) ($response['sheet_paid_amount'] ?? 0));
    $balance = max(0.0, (float) ($response['sheet_balance_due'] ?? 0));
    $total = max(0.0, (float) ($response['sheet_total_due'] ?? 0));
    if ($total <= 0 && ($paid > 0 || $balance > 0)) {
        $total = $paid + $balance;
    }

    return ['paid' => $paid, 'balance' => $balance, 'total' => $total];
}

function max_staff_for_application(array $application): int
{
    $maxStaff = (int) ($application['max_staff'] ?? 0);
    if ($maxStaff > 0) {
        return $maxStaff;
    }
    $stallCount = max(1, (int) ($application['number_of_stalls'] ?? 1));
    return max(1, $stallCount * 2);
}

function new_receipt_reference(PDO $pdo): string
{
    for ($attempt = 0; $attempt < 8; $attempt++) {
        $reference = 'FE26-' . date('ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
        $check = $pdo->prepare('SELECT id FROM payment_receipts WHERE receipt_reference = ? LIMIT 1');
        $check->execute([$reference]);
        if (!$check->fetch()) {
            return $reference;
        }
    }
    return 'FE26-' . date('ymdHis') . '-' . strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
}

function receipt_verification_url(array $receipt): string
{
    return absolute_app_url('admin/receipt-verify.php?token=' . rawurlencode((string) $receipt['receipt_token']));
}

function receipt_qr_image_url(string $url, int $size = 150): string
{
    $size = max(90, min(260, $size));
    return 'https://api.qrserver.com/v1/create-qr-code/?size=' . $size . 'x' . $size . '&data=' . rawurlencode($url);
}

function fetch_payment_receipt(PDO $pdo, string $field, string $value): ?array
{
    ensure_vendor_access_schema($pdo);
    if (!in_array($field, ['receipt_token', 'receipt_reference'], true)) {
        return null;
    }

    $statement = $pdo->prepare(
        'SELECT pr.*, a.payment_status, a.application_status, a.compliance_status, a.assigned_stall_number, a.assigned_stall_location,
                u.full_name AS user_full_name, u.email AS user_email, u.phone AS user_phone,
                fr.full_name AS sheet_full_name, fr.email AS sheet_email, fr.phone AS sheet_phone, fr.business_name, fr.business_nature,
                fr.business_description, fr.number_of_stalls, fr.max_staff, fr.sheet_paid_amount, fr.sheet_balance_due, fr.sheet_total_due
         FROM payment_receipts pr
         LEFT JOIN applications a ON a.id = pr.application_id
         LEFT JOIN users u ON u.id = COALESCE(pr.user_id, a.user_id)
         LEFT JOIN form_responses fr ON fr.id = COALESCE(pr.form_response_id, a.form_response_id)
         WHERE pr.' . $field . ' = ?
         LIMIT 1'
    );
    $statement->execute([$value]);
    $receipt = $statement->fetch();
    return $receipt ?: null;
}

function payment_receipt_identity(array $receipt): string
{
    return trim((string) ($receipt['user_full_name'] ?? ''))
        ?: trim((string) ($receipt['sheet_full_name'] ?? ''))
        ?: 'Vendor';
}

function sync_sheet_payment_receipt(PDO $pdo, int $responseId): ?array
{
    ensure_vendor_access_schema($pdo);
    $statement = $pdo->prepare(
        'SELECT fr.*, a.id AS application_id, a.user_id, u.full_name AS account_name
         FROM form_responses fr
         LEFT JOIN applications a ON a.form_response_id = fr.id
         LEFT JOIN users u ON u.id = a.user_id
         WHERE fr.id = ? LIMIT 1'
    );
    $statement->execute([$responseId]);
    $response = $statement->fetch();
    if (!$response) {
        return null;
    }

    $baseline = form_response_payment_baseline($response);
    if ($baseline['paid'] <= 0) {
        return null;
    }

    $existing = $pdo->prepare('SELECT * FROM payment_receipts WHERE source_type = "sheet" AND form_response_id = ? LIMIT 1');
    $existing->execute([$responseId]);
    $receipt = $existing->fetch();

    $values = [
        (int) ($response['application_id'] ?? 0) ?: null,
        $responseId,
        (int) ($response['user_id'] ?? 0) ?: null,
        $baseline['paid'],
        $baseline['balance'],
        $baseline['total'],
        trim((string) ($response['preferred_payment_method'] ?? '')) ?: null,
        trim((string) ($response['payment_transaction_id'] ?? '')) ?: null,
        'Imported payment register payment',
        trim((string) ($response['payment_handled_by'] ?? '')) ?: null,
        $response['payment_recorded_at'] ?? $response['submitted_at'] ?? $response['created_at'] ?? null,
    ];

    if ($receipt) {
        $pdo->prepare(
            'UPDATE payment_receipts
             SET application_id = ?, form_response_id = ?, user_id = ?, paid_amount = ?, balance_amount = ?, total_amount = ?, payment_method = ?, transaction_id = ?, payment_description = ?, received_by = ?, paid_at = ?, updated_at = NOW()
             WHERE id = ?'
        )->execute([...$values, (int) $receipt['id']]);
        return fetch_payment_receipt($pdo, 'receipt_reference', (string) $receipt['receipt_reference']);
    }

    $reference = new_receipt_reference($pdo);
    $token = bin2hex(random_bytes(32));
    $pdo->prepare(
        'INSERT INTO payment_receipts (application_id, form_response_id, user_id, receipt_reference, receipt_token, source_type, paid_amount, balance_amount, total_amount, payment_method, transaction_id, payment_description, received_by, paid_at, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, "sheet", ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
    )->execute([
        $values[0], $values[1], $values[2], $reference, $token,
        $values[3], $values[4], $values[5], $values[6], $values[7], $values[8], $values[9], $values[10],
    ]);

    return fetch_payment_receipt($pdo, 'receipt_reference', $reference);
}

function sync_upload_payment_receipt(PDO $pdo, int $uploadId): ?array
{
    ensure_vendor_access_schema($pdo);
    ensure_payment_upload_schema();
    $statement = $pdo->prepare(
        'SELECT pu.*, a.user_id AS application_user_id, a.form_response_id, fr.preferred_payment_method, u.full_name AS verifier_name
         FROM payment_uploads pu
         INNER JOIN applications a ON a.id = pu.application_id
         LEFT JOIN form_responses fr ON fr.id = a.form_response_id
         LEFT JOIN users u ON u.id = pu.verified_by
         WHERE pu.id = ? LIMIT 1'
    );
    $statement->execute([$uploadId]);
    $upload = $statement->fetch();
    if (!$upload || ($upload['verification_status'] ?? '') !== 'Verified') {
        return null;
    }

    $pdo->prepare('UPDATE users SET account_status = "active", updated_at = NOW() WHERE id = ? AND role = "applicant" AND account_status = "pending_approval"')
        ->execute([(int) ($upload['application_user_id'] ?? $upload['user_id'] ?? 0)]);

    $totals = payment_upload_totals($pdo, (int) $upload['application_id']);
    $existing = $pdo->prepare('SELECT * FROM payment_receipts WHERE payment_upload_id = ? LIMIT 1');
    $existing->execute([$uploadId]);
    $receipt = $existing->fetch();

    $values = [
        (int) $upload['application_id'],
        (int) ($upload['form_response_id'] ?? 0) ?: null,
        $uploadId,
        (int) ($upload['user_id'] ?? $upload['application_user_id'] ?? 0) ?: null,
        (float) ($upload['payment_amount'] ?? 0),
        (float) ($totals['balance'] ?? 0),
        (float) ($totals['total_due'] ?? 0),
        trim((string) ($upload['preferred_payment_method'] ?? '')) ?: null,
        null,
        trim((string) ($upload['payment_description'] ?? 'Portal payment proof')) ?: 'Portal payment proof',
        trim((string) ($upload['verifier_name'] ?? 'Admin')) ?: 'Admin',
        $upload['verified_at'] ?? $upload['uploaded_at'] ?? null,
    ];

    if ($receipt) {
        $pdo->prepare(
            'UPDATE payment_receipts
             SET application_id = ?, form_response_id = ?, payment_upload_id = ?, user_id = ?, paid_amount = ?, balance_amount = ?, total_amount = ?, payment_method = ?, transaction_id = ?, payment_description = ?, received_by = ?, paid_at = ?, updated_at = NOW()
             WHERE id = ?'
        )->execute([...$values, (int) $receipt['id']]);
        return fetch_payment_receipt($pdo, 'receipt_reference', (string) $receipt['receipt_reference']);
    }

    $reference = new_receipt_reference($pdo);
    $token = bin2hex(random_bytes(32));
    $pdo->prepare(
        'INSERT INTO payment_receipts (application_id, form_response_id, payment_upload_id, user_id, receipt_reference, receipt_token, source_type, paid_amount, balance_amount, total_amount, payment_method, transaction_id, payment_description, received_by, paid_at, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, "upload", ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
    )->execute([
        $values[0], $values[1], $values[2], $values[3], $reference, $token,
        $values[4], $values[5], $values[6], $values[7], $values[8], $values[9], $values[10], $values[11],
    ]);

    return fetch_payment_receipt($pdo, 'receipt_reference', $reference);
}

function create_manual_payment_receipt(PDO $pdo, int $applicationId, float $amount, string $description, string $method, string $transactionId, ?string $paidAt, int $adminId): array
{
    ensure_vendor_access_schema($pdo);
    $statement = $pdo->prepare(
        'SELECT a.*, u.full_name AS admin_name
         FROM applications a
         LEFT JOIN users u ON u.id = ?
         WHERE a.id = ? LIMIT 1'
    );
    $statement->execute([$adminId, $applicationId]);
    $application = $statement->fetch();
    if (!$application) {
        throw new RuntimeException('Application not found for receipt.');
    }

    $totalsBefore = payment_upload_totals($pdo, $applicationId);
    $reference = new_receipt_reference($pdo);
    $token = bin2hex(random_bytes(32));
    $paidAt = parse_sheet_datetime($paidAt) ?? date('Y-m-d H:i:s');
    $balanceAfter = max(0, (float) ($totalsBefore['balance'] ?? 0) - $amount);

    $pdo->prepare(
        'INSERT INTO payment_receipts (application_id, form_response_id, user_id, receipt_reference, receipt_token, source_type, paid_amount, balance_amount, total_amount, payment_method, transaction_id, payment_description, received_by, paid_at, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, "admin", ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
    )->execute([
        $applicationId,
        (int) ($application['form_response_id'] ?? 0) ?: null,
        (int) $application['user_id'],
        $reference,
        $token,
        $amount,
        $balanceAfter,
        (float) ($totalsBefore['total_due'] ?? 0),
        trim($method) ?: null,
        trim($transactionId) ?: null,
        trim($description) ?: 'Admin recorded payment',
        trim((string) ($application['admin_name'] ?? 'Admin')) ?: 'Admin',
        $paidAt,
    ]);

    refresh_application_payment_status_from_uploads($pdo, $applicationId);
    $pdo->prepare('UPDATE users SET account_status = "active", updated_at = NOW() WHERE id = ? AND role = "applicant" AND account_status = "pending_approval"')
        ->execute([(int) $application['user_id']]);
    $receipt = fetch_payment_receipt($pdo, 'receipt_reference', $reference);
    if (!$receipt) {
        throw new RuntimeException('Receipt could not be generated.');
    }
    return $receipt;
}

function tag_verification_url(array $tag): string
{
    return absolute_app_url('admin/verify-tag.php?token=' . rawurlencode((string) $tag['tag_token']));
}

function fetch_attendant_tag(PDO $pdo, string $token): ?array
{
    ensure_vendor_access_schema($pdo);
    $statement = $pdo->prepare(
        'SELECT at.*, a.payment_status, a.application_status, a.compliance_status, a.assigned_stall_number, a.assigned_stall_location,
                u.full_name AS account_name, u.email, u.phone,
                fr.business_name, fr.business_nature, fr.business_description, fr.number_of_stalls, fr.max_staff, fr.sheet_paid_amount, fr.sheet_balance_due, fr.sheet_total_due
         FROM attendant_tags at
         INNER JOIN applications a ON a.id = at.application_id
         INNER JOIN users u ON u.id = at.user_id
         LEFT JOIN form_responses fr ON fr.id = a.form_response_id
         WHERE at.tag_token = ? LIMIT 1'
    );
    $statement->execute([$token]);
    $tag = $statement->fetch();
    return $tag ?: null;
}

function active_attendant_count(PDO $pdo, int $applicationId): int
{
    ensure_vendor_access_schema($pdo);
    $statement = $pdo->prepare('SELECT COUNT(*) FROM attendant_tags WHERE application_id = ? AND is_active = 1 AND revoked_at IS NULL');
    $statement->execute([$applicationId]);
    return (int) $statement->fetchColumn();
}

function ensure_payment_upload_schema(): void
{
    $pdo = db();
    ensure_vendor_access_schema($pdo);
    if (!db_column_exists($pdo, 'payment_uploads', 'payment_amount')) {
        $pdo->exec('ALTER TABLE payment_uploads ADD COLUMN payment_amount DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER file_size');
    }
    if (!db_column_exists($pdo, 'payment_uploads', 'payment_description')) {
        $pdo->exec('ALTER TABLE payment_uploads ADD COLUMN payment_description VARCHAR(255) NULL AFTER payment_amount');
    }
}

function payment_upload_totals(PDO $pdo, int $applicationId, ?array $pricing = null): array
{
    ensure_payment_upload_schema();
    $baselineStatement = $pdo->prepare(
        'SELECT fr.sheet_paid_amount, fr.sheet_balance_due, fr.sheet_total_due
         FROM applications a
         LEFT JOIN form_responses fr ON fr.id = a.form_response_id
         WHERE a.id = ? LIMIT 1'
    );
    $baselineStatement->execute([$applicationId]);
    $baseline = form_response_payment_baseline($baselineStatement->fetch() ?: []);

    $statement = $pdo->prepare(
        'SELECT verification_status, COUNT(*) AS upload_count, COALESCE(SUM(payment_amount), 0) AS amount
         FROM payment_uploads
         WHERE application_id = ?
         GROUP BY verification_status'
    );
    $statement->execute([$applicationId]);

    $pricingTotal = (float) (($pricing ?? calculate_application_pricing($pdo, $applicationId))['total'] ?? 0);
    $totalDue = $baseline['total'] > 0 ? $baseline['total'] : $pricingTotal;

    $totals = [
        'total_due' => $totalDue,
        'sheet_paid' => $baseline['paid'],
        'sheet_balance_due' => $baseline['balance'],
        'verified' => $baseline['paid'],
        'pending' => 0.0,
        'rejected' => 0.0,
        'verified_count' => 0,
        'pending_count' => 0,
        'rejected_count' => 0,
        'upload_count' => 0,
    ];

    foreach ($statement->fetchAll() as $row) {
        $status = strtolower((string) $row['verification_status']);
        $amount = (float) $row['amount'];
        $count = (int) $row['upload_count'];
        if (isset($totals[$status])) {
            $totals[$status] += $amount;
            $totals[$status . '_count'] = $count;
        }
        $totals['upload_count'] += $count;
    }

    if (db_table_exists($pdo, 'payment_receipts')) {
        $manualStatement = $pdo->prepare('SELECT COUNT(*) AS receipt_count, COALESCE(SUM(paid_amount), 0) AS amount FROM payment_receipts WHERE application_id = ? AND source_type = "admin"');
        $manualStatement->execute([$applicationId]);
        $manual = $manualStatement->fetch() ?: [];
        $totals['verified'] += (float) ($manual['amount'] ?? 0);
        $totals['verified_count'] += (int) ($manual['receipt_count'] ?? 0);
    }

    $totals['submitted'] = $totals['verified'] + $totals['pending'];
    $totals['balance'] = max(0, $totals['total_due'] - $totals['verified']);
    return $totals;
}

function refresh_application_payment_status_from_uploads(PDO $pdo, int $applicationId, bool $forceRejected = false): void
{
    if ($forceRejected) {
        $pdo->prepare('UPDATE applications SET payment_status = "Payment Rejected", updated_at = NOW() WHERE id = ?')->execute([$applicationId]);
        return;
    }

    $totals = payment_upload_totals($pdo, $applicationId);
    if ($totals['total_due'] > 0 && $totals['verified'] >= $totals['total_due']) {
        $status = 'Payment Received';
    } elseif ($totals['pending_count'] > 0 || $totals['verified'] > 0) {
        $status = 'Pending Verification';
    } elseif ($totals['rejected_count'] > 0) {
        $status = 'Payment Rejected';
    } else {
        $status = 'Not Paid';
    }

    $pdo->prepare('UPDATE applications SET payment_status = ?, updated_at = NOW() WHERE id = ?')->execute([$status, $applicationId]);
}

function ugx_money(float $amount): string
{
    return 'UGX ' . number_format((int) round($amount));
}

function bazaar_pricing_seed_rules(): array
{
    $tariffs = [
        ['Electronics and gadgets', 100000, 200000],
        ['Food and drinks', 70000, 140000],
        ['NGO / awareness campaign', 50000, 100000],
        ['Agriculture and agro-consultancy', 50000, 70000],
        ['Health and wellness', 50000, 100000],
        ['Entertainment / gaming', 70000, 100000],
        ['Bedding and hostel items', 150000, 200000],
        ['Clothing and fashion', 80000, 130000],
        ['Technology / innovation services', 50000, 70000],
        ['Laundry services', 50000, 70000],
        ['Girly essentials', 50000, 50000],
        ['Art and crafts', 50000, 70000],
        ['Gas sales and refilling', 150000, 200000],
        ['Cosmetics and beauty products', 100000, 150000],
        ['Event planning and surprises', 50000, 70000],
        ['Stationery and printing', 50000, 70000],
        ['Safety gear', 150000, 200000],
        ['Mobile services', 50000, 70000],
        ['Corporate Brand', 100000, 150000],
        ['House hold items', 80000, 100000],
    ];

    $rules = [];
    $priority = 10;
    foreach ($tariffs as [$businessNature, $studentPrice, $nonStudentPrice]) {
        $rules[] = [$businessNature . ' - Student', $businessNature, 'Yes', $studentPrice, $priority++, 'Bazaar tariff for student exhibitors.'];
        $rules[] = [$businessNature . ' - Non Student', $businessNature, 'Not a student', $nonStudentPrice, $priority++, 'Bazaar tariff for non-student exhibitors.'];
    }

    return $rules;
}

function ensure_pricing_schema(): void
{
    $pdo = db();
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS pricing_plan_rules (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            rule_name VARCHAR(190) NOT NULL,
            business_nature_match VARCHAR(190) NULL,
            student_status_match VARCHAR(190) NULL,
            price_per_stall DECIMAL(12,2) NOT NULL,
            priority INT NOT NULL DEFAULT 100,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            notes TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_pricing_active_priority (is_active, priority),
            INDEX idx_pricing_business (business_nature_match),
            INDEX idx_pricing_student (student_status_match)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS application_special_discounts (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            application_id INT UNSIGNED NOT NULL,
            discount_type ENUM("fixed", "percent") NOT NULL DEFAULT "fixed",
            discount_value DECIMAL(12,2) NOT NULL,
            reason VARCHAR(255) NULL,
            created_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_discount_application (application_id),
            INDEX idx_discount_created_by (created_by)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $count = (int) $pdo->query('SELECT COUNT(*) FROM pricing_plan_rules')->fetchColumn();
    if ($count > 0) {
        return;
    }

    $insert = $pdo->prepare(
        'INSERT INTO pricing_plan_rules (rule_name, business_nature_match, student_status_match, price_per_stall, priority, is_active, notes, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, 1, ?, NOW(), NOW())'
    );
    foreach (bazaar_pricing_seed_rules() as $rule) {
        $insert->execute($rule);
    }
}

function pricing_match_value(?string $value, ?string $matcher): bool
{
    $matcher = strtolower(trim((string) $matcher));
    if ($matcher === '') {
        return true;
    }
    return str_contains(strtolower(trim((string) $value)), $matcher);
}

function fetch_pricing_rules(PDO $pdo, bool $activeOnly = false): array
{
    ensure_pricing_schema();
    $sql = 'SELECT * FROM pricing_plan_rules';
    if ($activeOnly) {
        $sql .= ' WHERE is_active = 1';
    }
    $sql .= ' ORDER BY priority ASC, id ASC';
    return $pdo->query($sql)->fetchAll();
}

function find_pricing_rule_for_response(PDO $pdo, ?string $businessNature, ?string $studentStatus): ?array
{
    foreach (fetch_pricing_rules($pdo, true) as $rule) {
        if (pricing_match_value($businessNature, $rule['business_nature_match'] ?? '') && pricing_match_value($studentStatus, $rule['student_status_match'] ?? '')) {
            return $rule;
        }
    }
    return null;
}

function calculate_application_pricing(PDO $pdo, int $applicationId): ?array
{
    ensure_pricing_schema();
    $statement = $pdo->prepare(
        'SELECT a.id AS application_id, a.user_id, u.full_name, fr.business_name, fr.business_nature, fr.student_status, fr.number_of_stalls
         FROM applications a
         INNER JOIN users u ON u.id = a.user_id
         LEFT JOIN form_responses fr ON fr.id = a.form_response_id
         WHERE a.id = ? LIMIT 1'
    );
    $statement->execute([$applicationId]);
    $application = $statement->fetch();
    if (!$application) {
        return null;
    }

    $rule = find_pricing_rule_for_response($pdo, $application['business_nature'] ?? null, $application['student_status'] ?? null);
    $pricePerStall = (float) ($rule['price_per_stall'] ?? 0);
    $stallCount = max(1, (int) ($application['number_of_stalls'] ?? 1));
    $subtotal = $pricePerStall * $stallCount;

    $discountStatement = $pdo->prepare('SELECT * FROM application_special_discounts WHERE application_id = ? LIMIT 1');
    $discountStatement->execute([$applicationId]);
    $discount = $discountStatement->fetch() ?: null;
    $discountAmount = 0.0;
    if ($discount) {
        $value = max(0, (float) $discount['discount_value']);
        if (($discount['discount_type'] ?? '') === 'percent') {
            $discountAmount = $subtotal * min($value, 100) / 100;
        } else {
            $discountAmount = min($value, $subtotal);
        }
    }

    return [
        'application' => $application,
        'rule' => $rule,
        'discount' => $discount,
        'stall_count' => $stallCount,
        'price_per_stall' => $pricePerStall,
        'subtotal' => $subtotal,
        'discount_amount' => $discountAmount,
        'total' => max(0, $subtotal - $discountAmount),
    ];
}

function pricing_preview_rows(PDO $pdo, int $limit = 80): array
{
    ensure_pricing_schema();
    $rows = $pdo->query(
        'SELECT a.id AS application_id, u.full_name, fr.business_name, fr.business_nature, fr.student_status, fr.number_of_stalls
         FROM applications a
         INNER JOIN users u ON u.id = a.user_id
         LEFT JOIN form_responses fr ON fr.id = a.form_response_id
         ORDER BY u.full_name ASC
         LIMIT ' . max(1, min(300, $limit))
    )->fetchAll();

    foreach ($rows as &$row) {
        $pricing = calculate_application_pricing($pdo, (int) $row['application_id']);
        $row['pricing'] = $pricing;
    }
    unset($row);

    return $rows;
}

function json_response(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function allowed_upload_extensions(): array
{
    return ['pdf', 'jpg', 'jpeg', 'png'];
}

function validate_uploaded_file(array $file, array $extensions, string &$error): bool
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $error = 'Please choose a valid file to upload.';
        return false;
    }

    if (($file['size'] ?? 0) > APP_MAX_UPLOAD_BYTES) {
        $error = 'The file is too large. Maximum upload size is 5 MB.';
        return false;
    }

    $extension = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, $extensions, true)) {
        $error = 'Only PDF, JPG, JPEG, and PNG files are allowed.';
        return false;
    }

    $allowedMime = [
        'pdf' => ['application/pdf', 'application/x-pdf'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'csv' => ['text/plain', 'text/csv', 'application/vnd.ms-excel', 'application/octet-stream'],
    ];

    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, (string) $file['tmp_name']);
        finfo_close($finfo);
        if (!in_array($mime, $allowedMime[$extension] ?? [], true)) {
            $error = 'The uploaded file type is not allowed.';
            return false;
        }
    }

    return true;
}

function ensure_upload_dir(string $folder): string
{
    $dir = dirname(__DIR__) . '/uploads/' . trim($folder, '/');
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    return $dir;
}

function secure_upload_name(string $original): string
{
    $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    return bin2hex(random_bytes(16)) . '.' . $extension;
}

function find_form_response_for_identifier(PDO $pdo, string $identifier): array
{
    $email = normalize_email($identifier);
    if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $statement = $pdo->prepare('SELECT * FROM form_responses WHERE LOWER(email) = ? ORDER BY id DESC LIMIT 1');
        $statement->execute([$email]);
        $row = $statement->fetch();
        if ($row) {
            return ['status' => 'found', 'response' => $row];
        }
    }

    $phone = normalize_phone($identifier);
    if (!$phone) {
        return ['status' => 'invalid'];
    }

    $statement = $pdo->prepare('SELECT * FROM form_responses WHERE normalized_phone = ? ORDER BY id DESC');
    $statement->execute([$phone]);
    $rows = $statement->fetchAll();

    if (count($rows) > 1) {
        return ['status' => 'multiple'];
    }

    if (count($rows) === 1) {
        return ['status' => 'found', 'response' => $rows[0]];
    }

    return ['status' => 'not_found'];
}

function find_user_by_identifier(PDO $pdo, string $identifier): ?array
{
    $email = normalize_email($identifier);
    if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $statement = $pdo->prepare('SELECT * FROM users WHERE LOWER(email) = ? LIMIT 1');
        $statement->execute([$email]);
        $user = $statement->fetch();
        if ($user) {
            return $user;
        }
    }

    $phone = normalize_phone($identifier);
    if ($phone) {
        $statement = $pdo->prepare('SELECT * FROM users WHERE normalized_phone = ? LIMIT 1');
        $statement->execute([$phone]);
        $user = $statement->fetch();
        return $user ?: null;
    }

    return null;
}

function count_rows(string $sql, array $params = []): int
{
    $statement = db()->prepare($sql);
    $statement->execute($params);
    return (int) $statement->fetchColumn();
}

function application_filters_from_request(array $source): array
{
    return [
        'q' => trim((string) ($source['q'] ?? '')),
        'student_status' => trim((string) ($source['student_status'] ?? '')),
        'applicant_type' => trim((string) ($source['applicant_type'] ?? '')),
        'business_nature' => trim((string) ($source['business_nature'] ?? '')),
        'application_status' => trim((string) ($source['application_status'] ?? '')),
        'payment_status' => trim((string) ($source['payment_status'] ?? '')),
        'compliance_status' => trim((string) ($source['compliance_status'] ?? '')),
        'stall_type' => trim((string) ($source['stall_type'] ?? '')),
        'electricity_needed' => trim((string) ($source['electricity_needed'] ?? '')),
    ];
}

function application_list_limit_from_request(array $source, int $default = 10): int
{
    $value = (string) ($source['list_size'] ?? (string) $default);
    if ($value === 'all') {
        return 0;
    }

    $limit = (int) $value;
    return in_array($limit, [10, 20, 50], true) ? $limit : $default;
}

function application_page_from_request(array $source): int
{
    return max(1, (int) ($source['page'] ?? 1));
}

function fetch_admin_applications(PDO $pdo, array $filters = [], int $limit = 100, int $offset = 0): array
{
    $conditions = [];
    $params = [];

    if (($filters['q'] ?? '') !== '') {
        $conditions[] = '(full_name LIKE ? OR email LIKE ? OR phone LIKE ? OR student_status LIKE ? OR business_name LIKE ? OR business_nature LIKE ? OR assigned_stall_number LIKE ?)';
        $search = '%' . $filters['q'] . '%';
        array_push($params, $search, $search, $search, $search, $search, $search, $search);
    }

    foreach (['student_status', 'applicant_type', 'business_nature', 'stall_type', 'electricity_needed'] as $field) {
        if (($filters[$field] ?? '') !== '') {
            $conditions[] = $field . ' = ?';
            $params[] = $filters[$field];
        }
    }

    foreach (['application_status', 'payment_status', 'compliance_status'] as $field) {
        if (($filters[$field] ?? '') !== '') {
            $conditions[] = $field . ' = ?';
            $params[] = $filters[$field];
        }
    }

    $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
    $limitSql = $limit > 0 ? ' LIMIT ' . max(1, min(501, $limit)) . ' OFFSET ' . max(0, $offset) : '';
    $sql = "SELECT * FROM (
                SELECT CONCAT('application:', a.id) AS row_key,
                       'application' AS row_type,
                       a.id,
                       a.id AS application_id,
                       a.user_id,
                       a.form_response_id,
                       COALESCE(NULLIF(u.full_name, ''), NULLIF(fr.full_name, ''), 'Applicant') AS full_name,
                       COALESCE(NULLIF(u.email, ''), NULLIF(fr.email, '')) AS email,
                       COALESCE(NULLIF(u.phone, ''), NULLIF(fr.phone, '')) AS phone,
                       fr.business_name,
                       fr.business_nature,
                       fr.student_status,
                       fr.applicant_type,
                       fr.stall_type,
                       fr.number_of_stalls,
                       fr.electricity_needed,
                       CAST(a.application_status AS CHAR(40)) AS application_status,
                       CAST(a.payment_status AS CHAR(40)) AS payment_status,
                       CAST(a.compliance_status AS CHAR(40)) AS compliance_status,
                       a.assigned_stall_number,
                       a.assigned_stall_location,
                       a.updated_at,
                       fr.submitted_at,
                       COALESCE(fr.created_at, a.created_at) AS created_at
                FROM applications a
                INNER JOIN users u ON u.id = a.user_id
                LEFT JOIN form_responses fr ON fr.id = a.form_response_id
                UNION ALL
                SELECT CONCAT('response:', fr.id) AS row_key,
                       'response' AS row_type,
                       0 AS id,
                       NULL AS application_id,
                       NULL AS user_id,
                       fr.id AS form_response_id,
                       COALESCE(NULLIF(fr.full_name, ''), 'Applicant') AS full_name,
                       NULLIF(fr.email, '') AS email,
                       NULLIF(fr.phone, '') AS phone,
                       fr.business_name,
                       fr.business_nature,
                       fr.student_status,
                       fr.applicant_type,
                       fr.stall_type,
                       fr.number_of_stalls,
                       fr.electricity_needed,
                       CAST('Synced From Sheet' AS CHAR(40)) AS application_status,
                        CASE
                            WHEN COALESCE(fr.sheet_paid_amount, 0) > 0 AND COALESCE(fr.sheet_balance_due, 0) <= 0 THEN 'Payment Received'
                            WHEN COALESCE(fr.sheet_paid_amount, 0) > 0 OR COALESCE(fr.proof_of_payment_url, '') <> '' THEN 'Pending Verification'
                            ELSE 'Not Paid'
                        END AS payment_status,
                       CASE WHEN COALESCE(fr.rules_agreement, '') <> '' THEN 'Pending Review' ELSE 'Not Signed' END AS compliance_status,
                       NULL AS assigned_stall_number,
                       NULL AS assigned_stall_location,
                       fr.updated_at,
                       fr.submitted_at,
                       fr.created_at
                FROM form_responses fr
                LEFT JOIN applications a ON a.form_response_id = fr.id
                WHERE a.id IS NULL
            ) application_rows
            $where
            ORDER BY COALESCE(updated_at, submitted_at, created_at) DESC, row_key DESC" . $limitSql;

    $statement = $pdo->prepare($sql);
    $statement->execute($params);
    return $statement->fetchAll();
}

function render_admin_application_pagination(int $page, int $limit, bool $hasNext): string
{
    ob_start();
    ?>
    <div class="table-pagination">
        <div class="pagination-summary"><?php echo $limit > 0 ? 'Page ' . number_format($page) : 'Full list'; ?></div>
        <label>Show
            <select data-application-list-size aria-label="Visible applicant range">
                <option value="10" <?php echo $limit === 10 ? 'selected' : ''; ?>>1-10</option>
                <option value="20" <?php echo $limit === 20 ? 'selected' : ''; ?>>1-20</option>
                <option value="50" <?php echo $limit === 50 ? 'selected' : ''; ?>>1-50</option>
                <option value="all" <?php echo $limit === 0 ? 'selected' : ''; ?>>Full list</option>
            </select>
        </label>
        <div class="pagination-actions">
            <button class="table-icon-button" type="button" data-application-page="prev" <?php echo $limit <= 0 || $page <= 1 ? 'disabled' : ''; ?> aria-label="Previous page">&laquo;</button>
            <button class="table-icon-button" type="button" data-application-page="next" <?php echo $limit <= 0 || !$hasNext ? 'disabled' : ''; ?> aria-label="Next page">&raquo;</button>
        </div>
    </div>
    <?php
    return trim((string) ob_get_clean());
}

function render_admin_application_rows(array $rows, bool $includeSelection = true): string
{
    ob_start();
    foreach ($rows as $row):
        $isApplication = ($row['row_type'] ?? 'application') === 'application';
        $rowKey = (string) ($row['row_key'] ?? ('application:' . (int) ($row['id'] ?? 0)));
        ?>
        <tr>
            <?php if ($includeSelection): ?>
                <td data-label="Select"><input type="checkbox" name="selected[]" value="<?php echo h($rowKey); ?>" data-application-row-select aria-label="Select <?php echo h($row['full_name'] ?? 'entry'); ?>"></td>
            <?php endif; ?>
            <td data-label="Name">
                <div class="identity-cell">
                    <span class="avatar"><?php echo h(initials($row['full_name'] ?? '')); ?></span>
                    <div>
                        <strong><?php echo h($row['full_name'] ?? 'Unknown'); ?></strong>
                        <small><?php echo h($row['student_status'] ?? $row['applicant_type'] ?? 'Applicant'); ?><?php echo $isApplication ? '' : ' / Synced sheet row'; ?></small>
                    </div>
                </div>
            </td>
            <td data-label="Business"><?php echo h($row['business_name'] ?? 'Not provided'); ?></td>
            <td data-label="Contact">
                <span><?php echo h($row['email'] ?? ''); ?></span><br>
                <small><?php echo h($row['phone'] ?? ''); ?></small>
            </td>
            <td data-label="Payment"><?php echo badge($row['payment_status'] ?? 'Not Paid'); ?></td>
            <td data-label="Status">
                <?php if ($isApplication): ?>
                    <select class="compact-select" data-status-select data-field="application_status" data-id="<?php echo (int) $row['id']; ?>">
                        <?php foreach (['Pending Review', 'Needs Correction', 'Approved', 'Rejected'] as $status): ?>
                            <option value="<?php echo h($status); ?>" <?php echo ($row['application_status'] ?? '') === $status ? 'selected' : ''; ?>><?php echo h($status); ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php else: ?>
                    <?php echo badge('Synced From Sheet'); ?><br><small>Awaiting portal account</small>
                <?php endif; ?>
            </td>
            <td data-label="Assigned Stall"><?php echo h($row['assigned_stall_number'] ?: 'Not assigned'); ?></td>
            <td data-label="Action">
                <?php if ($isApplication): ?>
                    <a class="link-strong" href="<?php echo h(app_url('admin/application-view.php?id=' . (int) $row['id'])); ?>">View</a>
                <?php else: ?>
                    <span class="help-text">Synced only</span>
                <?php endif; ?>
            </td>
        </tr>
        <?php
    endforeach;

    if (!$rows):
        ?>
        <tr><td colspan="<?php echo $includeSelection ? 8 : 7; ?>" class="empty-state">No applications match the current filters.</td></tr>
        <?php
    endif;

    return ob_get_clean();
}

function parse_application_row_key(string $rowKey): ?array
{
    if (!preg_match('/^(application|response):(\d+)$/', $rowKey, $matches)) {
        return null;
    }

    return ['type' => $matches[1], 'id' => (int) $matches[2]];
}

function delete_application_entries(PDO $pdo, array $rowKeys): array
{
    $summary = ['applications' => 0, 'responses' => 0, 'skipped' => 0];
    $parsedRows = [];

    foreach (array_unique(array_map('strval', $rowKeys)) as $rowKey) {
        $parsed = parse_application_row_key($rowKey);
        if (!$parsed || $parsed['id'] <= 0) {
            $summary['skipped']++;
            continue;
        }
        $parsedRows[] = $parsed;
    }

    if (!$parsedRows) {
        return $summary;
    }

    $applicationStatement = $pdo->prepare('SELECT id, user_id, form_response_id FROM applications WHERE id = ? LIMIT 1');
    $responseStatement = $pdo->prepare('SELECT id FROM form_responses WHERE id = ? LIMIT 1');
    $referenceStatement = $pdo->prepare(
        'SELECT
            (SELECT COUNT(*) FROM users WHERE form_response_id = ?) +
            (SELECT COUNT(*) FROM applications WHERE form_response_id = ?)'
    );

    foreach ($parsedRows as $row) {
        if ($row['type'] === 'application') {
            $applicationStatement->execute([$row['id']]);
            $application = $applicationStatement->fetch();
            if (!$application) {
                $summary['skipped']++;
                continue;
            }

            $userId = (int) $application['user_id'];
            $formResponseId = (int) ($application['form_response_id'] ?? 0);
            $pdo->prepare('UPDATE stalls SET is_allocated = 0, allocated_to_user_id = NULL, updated_at = NOW() WHERE allocated_to_user_id = ?')->execute([$userId]);
            $deletedUser = $pdo->prepare('DELETE FROM users WHERE id = ? AND role = "applicant"');
            $deletedUser->execute([$userId]);
            if ($deletedUser->rowCount() === 0) {
                $pdo->prepare('DELETE FROM applications WHERE id = ?')->execute([$row['id']]);
            }

            if ($formResponseId > 0) {
                $referenceStatement->execute([$formResponseId, $formResponseId]);
                if ((int) $referenceStatement->fetchColumn() === 0) {
                    $pdo->prepare('DELETE FROM form_responses WHERE id = ?')->execute([$formResponseId]);
                }
            }
            $summary['applications']++;
            continue;
        }

        $responseStatement->execute([$row['id']]);
        if (!$responseStatement->fetch()) {
            $summary['skipped']++;
            continue;
        }
        $pdo->prepare('DELETE FROM form_responses WHERE id = ?')->execute([$row['id']]);
        $summary['responses']++;
    }

    return $summary;
}

function form_response_import_fields(): array
{
    return [
        'submitted_at' => 'Submitted At',
        'full_name' => 'Full Name',
        'email' => 'Email',
        'phone' => 'Phone',
        'student_status' => 'Student Status',
        'institution' => 'Institution',
        'program' => 'Program',
        'year_of_study' => 'Year of Study',
        'business_name' => 'Business Name',
        'business_nature' => 'Business Nature',
        'business_description' => 'Business Description',
        'applicant_type' => 'Applicant Type',
        'stall_type' => 'Stall Type',
        'number_of_stalls' => 'Number of Stalls',
        'electricity_needed' => 'Electricity Needed',
        'equipment_needed' => 'Equipment Needed',
        'table_chair_request' => 'Table and Chair Request',
        'branding_space_needed' => 'Branding Space Needed',
        'preferred_payment_method' => 'Preferred Payment Method',
        'proof_of_payment_url' => 'Proof of Payment URL',
        'sheet_paid_amount' => 'Paid Amount',
        'sheet_balance_due' => 'Balance Due',
        'sheet_total_due' => 'Total Due',
        'max_staff' => 'Max Staff',
        'payment_transaction_id' => 'Transaction ID',
        'payment_handled_by' => 'Handled By',
        'payment_recorded_at' => 'Payment Timestamp',
        'rules_agreement' => 'Rules Agreement',
    ];
}

function auto_map_column(string $field, array $headers): string
{
    $aliases = [
        'submitted_at' => ['timestamp', 'submitted at', 'submission time', 'date'],
        'full_name' => ['full name', 'names', 'name', 'applicant name'],
        'email' => ['email', 'email address'],
        'phone' => ['phone', 'phone number', 'contact', 'contact number'],
        'student_status' => ['student status', 'are you a student', 'student or non student', 'student/non student', 'student non student', 'must student', 'not a student', 'non student'],
        'institution' => ['institution', 'university'],
        'program' => ['program', 'course'],
        'year_of_study' => ['year of study', 'year'],
        'business_name' => ['business name', 'brand name', 'stall name'],
        'business_nature' => ['business nature', 'category', 'type of business'],
        'business_description' => ['business description', 'description'],
        'applicant_type' => ['applicant type', 'vendor type'],
        'stall_type' => ['stall type', 'stall size'],
        'number_of_stalls' => ['number of stalls', 'stalls needed', 'qty', 'quantity'],
        'electricity_needed' => ['electricity needed', 'power'],
        'equipment_needed' => ['equipment needed', 'equipment'],
        'table_chair_request' => ['table', 'chair', 'table/chair'],
        'branding_space_needed' => ['branding space', 'branding'],
        'preferred_payment_method' => ['payment method', 'preferred payment'],
        'proof_of_payment_url' => ['proof of payment', 'payment proof', 'receipt'],
        'sheet_paid_amount' => ['paid amount', 'amount paid', 'paid'],
        'sheet_balance_due' => ['balance due', 'balance'],
        'sheet_total_due' => ['total due', 'totals', 'total'],
        'max_staff' => ['max staff', 'maximum staff', 'staff cap', 'staff limit'],
        'payment_transaction_id' => ['transaction id', 'transaction reference', 'reference', 'receipt number'],
        'payment_handled_by' => ['handled by', 'received by', 'collector'],
        'payment_recorded_at' => ['payment timestamp', 'payment time', 'time stamp', 'timestamp'],
        'rules_agreement' => ['rules agreement', 'agree', 'consent'],
    ];

    $normalizedHeaders = [];
    foreach ($headers as $index => $header) {
        $normalizedHeaders[(string) $index] = strtolower(trim((string) $header));
    }

    foreach ($aliases[$field] ?? [$field] as $alias) {
        foreach ($normalizedHeaders as $index => $header) {
            if ($header === $alias || str_contains($header, $alias)) {
                return (string) $index;
            }
        }
    }

    return '';
}

function auto_form_response_mapping(array $headers): array
{
    $mapping = [];
    foreach (form_response_import_fields() as $field => $label) {
        $mapping[$field] = auto_map_column($field, $headers);
    }
    return $mapping;
}

function form_response_mapping_has_identity(array $mapping): bool
{
    return ($mapping['phone'] ?? '') !== '' || ($mapping['email'] ?? '') !== '';
}

function sync_form_response_linked_portal_records(PDO $pdo, int $responseId): void
{
    ensure_vendor_access_schema($pdo);
    $responseStatement = $pdo->prepare('SELECT * FROM form_responses WHERE id = ? LIMIT 1');
    $responseStatement->execute([$responseId]);
    $response = $responseStatement->fetch();
    if (!$response) {
        return;
    }

    $baseline = form_response_payment_baseline($response);
    if ($baseline['paid'] > 0) {
        sync_sheet_payment_receipt($pdo, $responseId);
        $pdo->prepare('UPDATE users SET account_status = "active", updated_at = NOW() WHERE form_response_id = ? AND role = "applicant" AND account_status = "pending_approval"')
            ->execute([$responseId]);
    }

    $applications = $pdo->prepare('SELECT id FROM applications WHERE form_response_id = ?');
    $applications->execute([$responseId]);
    foreach ($applications->fetchAll() as $application) {
        refresh_application_payment_status_from_uploads($pdo, (int) $application['id']);
    }
}

function import_form_responses_from_csv_path(PDO $pdo, string $path, array $mapping = []): array
{
    ensure_vendor_access_schema($pdo);
    $fields = form_response_import_fields();
    $summary = ['processed' => 0, 'added' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];
    $handle = fopen($path, 'r');
    if (!$handle) {
        $summary['errors'][] = 'Unable to open CSV file.';
        return $summary;
    }

    $headers = fgetcsv($handle) ?: [];
    if (isset($headers[0])) {
        $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $headers[0]);
    }
    if (!$headers) {
        fclose($handle);
        $summary['errors'][] = 'The CSV file does not contain a header row.';
        return $summary;
    }

    if (!$mapping) {
        $mapping = auto_form_response_mapping($headers);
        $headerAttempts = 0;
        while (!form_response_mapping_has_identity($mapping) && $headerAttempts < 5 && ($candidate = fgetcsv($handle)) !== false) {
            $headerAttempts++;
            if (!array_filter($candidate, fn($value) => trim((string) $value) !== '')) {
                continue;
            }
            $candidateMapping = auto_form_response_mapping($candidate);
            if (form_response_mapping_has_identity($candidateMapping)) {
                $headers = $candidate;
                if (isset($headers[0])) {
                    $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $headers[0]);
                }
                $mapping = $candidateMapping;
                break;
            }
        }
    }

    if (!form_response_mapping_has_identity($mapping)) {
        fclose($handle);
        $summary['errors'][] = 'Could not identify a phone or email column in the CSV header row.';
        return $summary;
    }

    $insertFields = array_merge(array_keys($fields), ['normalized_phone', 'raw_data_json']);

    while (($row = fgetcsv($handle)) !== false) {
        $summary['processed']++;
        if (!array_filter($row, fn($value) => trim((string) $value) !== '')) {
            $summary['skipped']++;
            continue;
        }

        $data = [];
        foreach ($fields as $field => $label) {
            $index = $mapping[$field] ?? '';
            $data[$field] = $index !== '' ? trim((string) ($row[(int) $index] ?? '')) : '';
        }

        $email = normalize_email($data['email'] ?? null);
        $normalizedPhone = normalize_phone($data['phone'] ?? '');
        if (strtolower(trim((string) ($data['full_name'] ?? ''))) === 'totals') {
            $summary['skipped']++;
            continue;
        }
        if (!$email && !$normalizedPhone) {
            $hasVendorContent = false;
            foreach (['full_name', 'business_name', 'business_nature', 'sheet_paid_amount', 'sheet_balance_due', 'sheet_total_due', 'payment_transaction_id'] as $contentField) {
                if (trim((string) ($data[$contentField] ?? '')) !== '') {
                    $hasVendorContent = true;
                    break;
                }
            }
            if (!$hasVendorContent) {
                $summary['skipped']++;
                continue;
            }
            $summary['skipped']++;
            $summary['errors'][] = 'Row ' . $summary['processed'] . ': missing email and phone.';
            continue;
        }

        $data['email'] = $email;
        $data['normalized_phone'] = $normalizedPhone;
        $data['number_of_stalls'] = $data['number_of_stalls'] !== '' ? (int) $data['number_of_stalls'] : null;
        $data['sheet_paid_amount'] = parse_money_amount($data['sheet_paid_amount'] ?? '');
        $data['sheet_balance_due'] = parse_money_amount($data['sheet_balance_due'] ?? '');
        $data['sheet_total_due'] = parse_money_amount($data['sheet_total_due'] ?? '');
        $data['max_staff'] = trim((string) ($data['max_staff'] ?? '')) !== '' ? max(0, (int) preg_replace('/\D+/', '', (string) $data['max_staff'])) : null;
        $data['payment_recorded_at'] = parse_sheet_datetime($data['payment_recorded_at'] ?? null);
        if ($data['submitted_at'] !== '') {
            $data['submitted_at'] = parse_sheet_datetime($data['submitted_at']);
        } else {
            $data['submitted_at'] = null;
        }

        $raw = [];
        foreach ($headers as $index => $header) {
            $raw[(string) $header] = $row[$index] ?? '';
        }
        $data['raw_data_json'] = json_encode($raw);

        $conditions = [];
        $params = [];
        if ($email) {
            $conditions[] = 'LOWER(email) = ?';
            $params[] = $email;
        }
        if ($normalizedPhone) {
            $conditions[] = 'normalized_phone = ?';
            $params[] = $normalizedPhone;
        }

        $find = $pdo->prepare('SELECT id FROM form_responses WHERE ' . implode(' OR ', $conditions) . ' ORDER BY id DESC LIMIT 1');
        $find->execute($params);
        $existingId = $find->fetchColumn();

        if ($existingId) {
            $sets = [];
            $values = [];
            foreach ($insertFields as $field) {
                $sets[] = $field . ' = ?';
                $values[] = $data[$field] ?? null;
            }
            $values[] = (int) $existingId;
            $pdo->prepare('UPDATE form_responses SET ' . implode(', ', $sets) . ', updated_at = NOW() WHERE id = ?')->execute($values);
            sync_form_response_linked_portal_records($pdo, (int) $existingId);
            $summary['updated']++;
        } else {
            $columns = implode(', ', $insertFields) . ', created_at, updated_at';
            $placeholders = rtrim(str_repeat('?, ', count($insertFields)), ', ') . ', NOW(), NOW()';
            $values = [];
            foreach ($insertFields as $field) {
                $values[] = $data[$field] ?? null;
            }
            $pdo->prepare('INSERT INTO form_responses (' . $columns . ') VALUES (' . $placeholders . ')')->execute($values);
            sync_form_response_linked_portal_records($pdo, (int) $pdo->lastInsertId());
            $summary['added']++;
        }
    }

    fclose($handle);
    return $summary;
}

function google_sheet_csv_url(string $sheetUrlOrId, string $gid = '0'): string
{
    $sheetUrlOrId = trim($sheetUrlOrId);
    $gid = trim($gid) !== '' ? trim($gid) : '0';

    if ($sheetUrlOrId === '') {
        return '';
    }

    if (filter_var($sheetUrlOrId, FILTER_VALIDATE_URL)) {
        $parts = parse_url($sheetUrlOrId);
        $query = [];
        if (!empty($parts['query'])) {
            parse_str((string) $parts['query'], $query);
        }
        if (!empty($query['gid'])) {
            $gid = (string) $query['gid'];
        }
        if (!empty($parts['fragment']) && preg_match('/gid=([0-9]+)/', (string) $parts['fragment'], $matches)) {
            $gid = $matches[1];
        }
        if (str_contains($sheetUrlOrId, 'output=csv') || str_contains($sheetUrlOrId, 'format=csv')) {
            return $sheetUrlOrId;
        }
        if (preg_match('#/spreadsheets/d/e/([a-zA-Z0-9_-]+)#', $sheetUrlOrId, $matches)) {
            return 'https://docs.google.com/spreadsheets/d/e/' . $matches[1] . '/pub?gid=' . rawurlencode($gid) . '&single=true&output=csv';
        }
        if (preg_match('#/spreadsheets/d/([a-zA-Z0-9_-]+)#', $sheetUrlOrId, $matches)) {
            return 'https://docs.google.com/spreadsheets/d/' . $matches[1] . '/export?format=csv&gid=' . rawurlencode($gid);
        }
        return $sheetUrlOrId;
    }

    if (preg_match('/^[a-zA-Z0-9_-]{20,}$/', $sheetUrlOrId)) {
        return 'https://docs.google.com/spreadsheets/d/' . $sheetUrlOrId . '/export?format=csv&gid=' . rawurlencode($gid);
    }

    return '';
}

function fetch_remote_csv_to_path(string $url, string &$error): ?string
{
    if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
        $error = 'Enter a valid Google Sheet URL, published CSV URL, or sheet ID.';
        return null;
    }

    $content = false;
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 12,
            CURLOPT_TIMEOUT => 45,
            CURLOPT_USERAGENT => 'Freshers Expo Portal Sheet Sync/1.0',
        ]);
        $content = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        if (($content === false || $httpCode >= 400) && stripos($curlError, 'SSL certificate') !== false) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_CONNECTTIMEOUT => 12,
                CURLOPT_TIMEOUT => 45,
                CURLOPT_USERAGENT => 'Freshers Expo Portal Sheet Sync/1.0',
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
            ]);
            $content = curl_exec($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
        }
        if ($content === false || $httpCode >= 400) {
            $error = $curlError ?: 'Google Sheet returned HTTP status ' . $httpCode . '.';
            return null;
        }
    } elseif (ini_get('allow_url_fopen')) {
        $context = stream_context_create([
            'http' => [
                'timeout' => 45,
                'header' => "User-Agent: Freshers Expo Portal Sheet Sync/1.0\r\n",
            ],
        ]);
        $content = @file_get_contents($url, false, $context);
        if ($content === false) {
            $error = 'Unable to download the sheet. Enable cURL or allow_url_fopen on the server.';
            return null;
        }
    } else {
        $error = 'This server cannot fetch remote URLs. Enable PHP cURL or allow_url_fopen.';
        return null;
    }

    $content = (string) $content;
    if (trim($content) === '') {
        $error = 'The Google Sheet returned an empty response.';
        return null;
    }
    if (strlen($content) > 8 * 1024 * 1024) {
        $error = 'The Google Sheet CSV is larger than the 8 MB sync limit.';
        return null;
    }
    if (preg_match('/^\s*</', $content) && stripos($content, '<html') !== false) {
        $error = 'Google returned an HTML page instead of CSV. Share the sheet as "Anyone with the link can view" or publish it as CSV.';
        return null;
    }

    $dir = ensure_upload_dir('imports');
    $path = tempnam($dir, 'sheet_sync_');
    if (!$path || file_put_contents($path, $content) === false) {
        $error = 'Unable to save the downloaded CSV temporarily.';
        return null;
    }

    return $path;
}

function sync_google_sheet_responses(?int $syncedByUserId = null): array
{
    $sheet = setting('google_sheet_url', default_vendor_payment_sheet_url());
    $gid = setting('google_sheet_gid', '0');
    $csvUrl = google_sheet_csv_url($sheet, $gid);
    $error = '';
    $path = fetch_remote_csv_to_path($csvUrl, $error);

    if (!$path) {
        $summary = ['processed' => 0, 'added' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => [$error]];
        record_sheet_sync_log($csvUrl, $summary, $syncedByUserId);
        return $summary;
    }

    $summary = import_form_responses_from_csv_path(db(), $path);
    @unlink($path);
    record_sheet_sync_log($csvUrl, $summary, $syncedByUserId);
    save_setting('google_sheet_last_sync_at', date('Y-m-d H:i:s'));
    save_setting('google_sheet_last_sync_summary', json_encode($summary));

    return $summary;
}

function record_sheet_sync_log(string $sourceUrl, array $summary, ?int $syncedByUserId = null): void
{
    try {
        $pdo = db();
        if (!db_table_exists($pdo, 'sheet_sync_logs')) {
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS sheet_sync_logs (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    source_url TEXT NULL,
                    rows_processed INT UNSIGNED NOT NULL DEFAULT 0,
                    new_records INT UNSIGNED NOT NULL DEFAULT 0,
                    updated_records INT UNSIGNED NOT NULL DEFAULT 0,
                    skipped_rows INT UNSIGNED NOT NULL DEFAULT 0,
                    errors_json LONGTEXT NULL,
                    synced_by_user_id INT UNSIGNED NULL,
                    synced_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_sheet_sync_synced_at (synced_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        }
        $statement = $pdo->prepare(
            'INSERT INTO sheet_sync_logs (source_url, rows_processed, new_records, updated_records, skipped_rows, errors_json, synced_by_user_id, synced_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $statement->execute([
            $sourceUrl,
            (int) ($summary['processed'] ?? 0),
            (int) ($summary['added'] ?? 0),
            (int) ($summary['updated'] ?? 0),
            (int) ($summary['skipped'] ?? 0),
            json_encode($summary['errors'] ?? []),
            $syncedByUserId,
        ]);
    } catch (Throwable $exception) {
        error_log('Sheet sync log failed: ' . $exception->getMessage());
    }
}

function db_table_exists(PDO $pdo, string $table): bool
{
    $statement = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
    $statement->execute([$table]);
    return (int) $statement->fetchColumn() > 0;
}

function db_column_exists(PDO $pdo, string $table, string $column): bool
{
    $statement = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    $statement->execute([$table, $column]);
    return (int) $statement->fetchColumn() > 0;
}

function db_index_exists(PDO $pdo, string $table, string $index): bool
{
    $statement = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?');
    $statement->execute([$table, $index]);
    return (int) $statement->fetchColumn() > 0;
}

function db_quote_identifier(string $identifier): string
{
    if (!preg_match('/^[A-Za-z0-9_]+$/', $identifier)) {
        throw new InvalidArgumentException('Invalid database identifier.');
    }
    return '`' . $identifier . '`';
}

function db_table_is_readable(PDO $pdo, string $table): bool
{
    if (!db_table_exists($pdo, $table)) {
        return false;
    }

    try {
        $pdo->query('SELECT 1 FROM ' . db_quote_identifier($table) . ' LIMIT 1');
        return true;
    } catch (Throwable $exception) {
        error_log('Database table ' . $table . ' is not readable and will be recreated if possible: ' . $exception->getMessage());
        return false;
    }
}

function db_drop_table_if_exists(PDO $pdo, string $table): void
{
    $pdo->exec('DROP TABLE IF EXISTS ' . db_quote_identifier($table));
}

function db_move_orphan_tablespace_file(PDO $pdo, string $table): void
{
    if (db_table_exists($pdo, $table)) {
        return;
    }

    $database = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
    if (!preg_match('/^[A-Za-z0-9_]+$/', $database) || !preg_match('/^[A-Za-z0-9_]+$/', $table)) {
        return;
    }

    $dataDir = rtrim((string) $pdo->query('SELECT @@datadir')->fetchColumn(), "\\/");
    if ($dataDir === '') {
        return;
    }

    $path = $dataDir . DIRECTORY_SEPARATOR . $database . DIRECTORY_SEPARATOR . $table . '.ibd';
    if (!is_file($path)) {
        return;
    }

    $backupPath = $path . '.orphan-' . date('Ymd-His') . '.bak';
    if (@rename($path, $backupPath)) {
        error_log('Moved orphaned tablespace file for ' . $table . ' to ' . $backupPath);
        return;
    }

    error_log('Could not move orphaned tablespace file for ' . $table . ' at ' . $path);
}

function repair_layout_designer_tables(PDO $pdo): void
{
    $venueLayoutsReadable = db_table_is_readable($pdo, 'venue_layouts');
    $layoutElementsReadable = db_table_is_readable($pdo, 'layout_elements');

    if (!$venueLayoutsReadable) {
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        try {
            db_drop_table_if_exists($pdo, 'layout_elements');
            db_drop_table_if_exists($pdo, 'venue_layouts');
        } finally {
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        }
        db_move_orphan_tablespace_file($pdo, 'layout_elements');
        db_move_orphan_tablespace_file($pdo, 'venue_layouts');
        return;
    }

    if (!$layoutElementsReadable) {
        db_drop_table_if_exists($pdo, 'layout_elements');
        db_move_orphan_tablespace_file($pdo, 'layout_elements');
    }
}

function ensure_tent_planning_schema(): void
{
    $pdo = db();

    if (db_table_exists($pdo, 'stalls')) {
        $columns = [
            'tent_group_code' => 'VARCHAR(80) NULL',
            'tent_code' => 'VARCHAR(20) NULL',
            'arrangement_key' => 'VARCHAR(80) NULL',
            'layout_zone' => 'VARCHAR(80) NULL',
        ];
        foreach ($columns as $column => $definition) {
            if (!db_column_exists($pdo, 'stalls', $column)) {
                $pdo->exec('ALTER TABLE stalls ADD COLUMN ' . $column . ' ' . $definition);
            }
        }
        if (!db_index_exists($pdo, 'stalls', 'idx_stall_tent_group')) {
            $pdo->exec('ALTER TABLE stalls ADD INDEX idx_stall_tent_group (tent_group_code, tent_code)');
        }
        if (!db_index_exists($pdo, 'stalls', 'idx_stall_layout_zone')) {
            $pdo->exec('ALTER TABLE stalls ADD INDEX idx_stall_layout_zone (layout_zone)');
        }
    }

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS tent_capacity_rules (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tent_code VARCHAR(20) NOT NULL,
            tent_name VARCHAR(120) NOT NULL,
            canopy_type VARCHAR(80) NOT NULL,
            min_stalls INT UNSIGNED NOT NULL,
            recommended_stalls INT UNSIGNED NOT NULL,
            max_stalls INT UNSIGNED NOT NULL,
            footprint_sqm DECIMAL(8,2) NOT NULL,
            hire_setup_cost DECIMAL(12,2) NOT NULL,
            hard_rule TEXT NOT NULL,
            normal_assumption TEXT NOT NULL,
            updated_at DATETIME NULL,
            UNIQUE KEY uniq_tent_code (tent_code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS tent_arrangement_rules (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tent_code VARCHAR(20) NOT NULL,
            arrangement_key VARCHAR(80) NOT NULL,
            arrangement_name VARCHAR(120) NOT NULL,
            number_of_stalls INT UNSIGNED NOT NULL,
            suitable_exhibitors VARCHAR(255) NOT NULL,
            stall_class VARCHAR(80) NOT NULL,
            walkway_ratio DECIMAL(5,2) NOT NULL,
            setup_extra DECIMAL(12,2) NOT NULL DEFAULT 0,
            UNIQUE KEY uniq_tent_arrangement (tent_code, arrangement_key),
            INDEX idx_tent_arrangement_tent (tent_code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS venue_layout_zones (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            zone_key VARCHAR(80) NOT NULL,
            zone_name VARCHAR(120) NOT NULL,
            u_position VARCHAR(190) NOT NULL,
            traffic_level VARCHAR(80) NOT NULL,
            notes TEXT NULL,
            map_x INT NULL,
            map_y INT NULL,
            map_width INT NULL,
            map_height INT NULL,
            UNIQUE KEY uniq_zone_key (zone_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $zoneColumns = [
        'map_x' => 'INT NULL',
        'map_y' => 'INT NULL',
        'map_width' => 'INT NULL',
        'map_height' => 'INT NULL',
    ];
    foreach ($zoneColumns as $column => $definition) {
        if (!db_column_exists($pdo, 'venue_layout_zones', $column)) {
            $pdo->exec('ALTER TABLE venue_layout_zones ADD COLUMN ' . $column . ' ' . $definition);
        }
    }

    $pdo->exec(
        "INSERT INTO tent_capacity_rules (tent_code, tent_name, canopy_type, min_stalls, recommended_stalls, max_stalls, footprint_sqm, hire_setup_cost, hard_rule, normal_assumption, updated_at) VALUES
        ('50', '50-Seater Tent', 'Single Canopy', 1, 4, 5, 54.00, 450000.00, 'Never allocate more than 5 stalls in one 50-seater tent.', 'Use 4 stalls per tent as the normal planning assumption unless the exhibitor category requires a different arrangement.', NOW()),
        ('100', '100-Seater Tent', 'Double Canopy', 1, 8, 10, 108.00, 850000.00, 'The tent must not exceed 10 stalls.', 'Use 8 stalls per 100-seater tent as the normal planning assumption for standard exhibitors.', NOW())
        ON DUPLICATE KEY UPDATE tent_name = VALUES(tent_name), canopy_type = VALUES(canopy_type), min_stalls = VALUES(min_stalls), recommended_stalls = VALUES(recommended_stalls), max_stalls = VALUES(max_stalls), footprint_sqm = VALUES(footprint_sqm), hire_setup_cost = VALUES(hire_setup_cost), hard_rule = VALUES(hard_rule), normal_assumption = VALUES(normal_assumption), updated_at = NOW()"
    );

    $pdo->exec(
        "INSERT INTO tent_arrangement_rules (tent_code, arrangement_key, arrangement_name, number_of_stalls, suitable_exhibitors, stall_class, walkway_ratio, setup_extra) VALUES
        ('50', 'exclusive_50', 'Exclusive tent', 1, 'Medium corporate exhibitor or sponsor', 'exclusive_50', 0.16, 160000.00),
        ('50', 'large_50', 'Large stalls', 2, 'Established businesses or service providers', 'large', 0.20, 140000.00),
        ('50', 'standard_50', 'Standard stalls', 4, 'SMEs, NGOs and retailers', 'standard', 0.25, 110000.00),
        ('50', 'small_50', 'Small stalls', 5, 'Student businesses, startups and small vendors', 'small', 0.30, 90000.00),
        ('100', 'exclusive_100', 'Exclusive corporate pavilion', 1, 'Headline sponsor, telecom, bank or major brand', 'exclusive_100', 0.14, 320000.00),
        ('100', 'mega_100', 'Mega premium stalls', 2, 'Two large corporate exhibitors', 'premium', 0.18, 260000.00),
        ('100', 'large_100', 'Large stalls', 4, 'Banks, insurance companies and established brands', 'large', 0.22, 220000.00),
        ('100', 'medium_100', 'Medium stalls', 6, 'Corporate exhibitors and government agencies', 'medium', 0.25, 185000.00),
        ('100', 'standard_100', 'Standard stalls', 8, 'Mixed SMEs, NGOs and service providers', 'standard', 0.28, 160000.00),
        ('100', 'small_100', 'Small stalls', 10, 'Student businesses, startups and small retailers', 'small', 0.32, 140000.00)
        ON DUPLICATE KEY UPDATE arrangement_name = VALUES(arrangement_name), number_of_stalls = VALUES(number_of_stalls), suitable_exhibitors = VALUES(suitable_exhibitors), stall_class = VALUES(stall_class), walkway_ratio = VALUES(walkway_ratio), setup_extra = VALUES(setup_extra)"
    );

    $pdo->exec(
        "INSERT INTO venue_layout_zones (zone_key, zone_name, u_position, traffic_level, notes) VALUES
        ('corporate_sponsors', 'Corporate & Sponsors', 'Business distribution zone', 'Not specified', 'Tents 1, 2, 3, 12, 13, 14. Banks, telecoms, insurance, universities, government agencies, and internet providers.'),
        ('retail_commercial', 'Retail & Commercial', 'Business distribution zone', 'Not specified', 'Tents 4, 5, 6, 7, 8. Electronics, furniture, cosmetics, fashion, phones, printing, supermarkets, and hardware.'),
        ('students_innovation', 'Students & Innovation', 'Business distribution zone', 'Not specified', 'Tents 9, 10, 11. Student businesses, clubs, innovation projects, startups, and campus entrepreneurs.'),
        ('food_beverage', 'Food & Beverage', 'Business distribution zone', 'Not specified', 'Tent 15 mandatory beer garden. Reserved for Nile Breweries, Uganda Breweries, Bell Lager, Nile Special, Tusker, Guinness, Pilsner, Castle Lite, spirits and beverage promotions subject to university approval, lounge seating, and a responsible consumption area. Adjacent to the entertainment stage as the event social hub.')
        ON DUPLICATE KEY UPDATE zone_name = VALUES(zone_name), u_position = VALUES(u_position), traffic_level = VALUES(traffic_level), notes = VALUES(notes)"
    );

    if (db_table_exists($pdo, 'layout_elements')) {
        $pdo->exec(
            "UPDATE layout_elements SET u_zone = CASE
                WHEN tent_group_code IN ('TENT-01', 'TENT-02', 'TENT-03', 'TENT-12', 'TENT-13', 'TENT-14') THEN 'corporate_sponsors'
                WHEN tent_group_code IN ('TENT-04', 'TENT-05', 'TENT-06', 'TENT-07', 'TENT-08') THEN 'retail_commercial'
                WHEN tent_group_code IN ('TENT-09', 'TENT-10', 'TENT-11') THEN 'students_innovation'
                WHEN tent_group_code = 'TENT-15' THEN 'food_beverage'
                ELSE u_zone
            END
            WHERE element_type IN ('tent_50', 'tent_100')"
        );
        $pdo->exec("UPDATE layout_elements SET u_zone = NULL WHERE u_zone IN ('entry_arm', 'stage_front', 'corner_turn', 'exit_arm', 'food_court_edge', 'service_back')");
    }

    if (db_table_exists($pdo, 'stalls')) {
        $pdo->exec(
            "UPDATE stalls SET layout_zone = CASE
                WHEN tent_group_code IN ('TENT-01', 'TENT-02', 'TENT-03', 'TENT-12', 'TENT-13', 'TENT-14') THEN 'corporate_sponsors'
                WHEN tent_group_code IN ('TENT-04', 'TENT-05', 'TENT-06', 'TENT-07', 'TENT-08') THEN 'retail_commercial'
                WHEN tent_group_code IN ('TENT-09', 'TENT-10', 'TENT-11') THEN 'students_innovation'
                WHEN tent_group_code = 'TENT-15' THEN 'food_beverage'
                ELSE layout_zone
            END"
        );
        $pdo->exec("UPDATE stalls SET layout_zone = NULL WHERE layout_zone IN ('entry_arm', 'stage_front', 'corner_turn', 'exit_arm', 'food_court_edge', 'service_back')");
    }

    $pdo->exec("DELETE FROM venue_layout_zones WHERE zone_key IN ('entry_arm', 'stage_front', 'corner_turn', 'exit_arm', 'food_court_edge', 'service_back')");
}

function ensure_layout_designer_schema(?int $createdByUserId = null): void
{
    $pdo = db();
    ensure_tent_planning_schema();
    repair_layout_designer_tables($pdo);

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS venue_layouts (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(190) NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 0,
            created_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_venue_layout_name (name),
            INDEX idx_venue_layout_active (is_active),
            CONSTRAINT fk_venue_layout_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS layout_elements (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            layout_id INT UNSIGNED NOT NULL,
            element_type ENUM("tent_50", "tent_100", "stage", "reg_desk", "waste_point", "toilet_m", "toilet_f", "walkway", "label") NOT NULL,
            tent_group_code VARCHAR(80) NULL,
            tent_type VARCHAR(20) NULL,
            stall_count INT UNSIGNED NULL,
            category VARCHAR(80) NULL,
            u_zone VARCHAR(80) NULL,
            x INT NOT NULL DEFAULT 0,
            y INT NOT NULL DEFAULT 0,
            width INT NOT NULL DEFAULT 100,
            height INT NOT NULL DEFAULT 80,
            rotation INT NOT NULL DEFAULT 0,
            label VARCHAR(190) NULL,
            z_index INT NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_layout_elements_layout (layout_id),
            INDEX idx_layout_elements_tent_group (tent_group_code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $statement = $pdo->prepare('SELECT id FROM venue_layouts WHERE name = ? LIMIT 1');
    $statement->execute(['Default U-Shape MUST Pitch']);
    $layoutId = (int) $statement->fetchColumn();

    if ($layoutId <= 0) {
        if ($createdByUserId === null) {
            $adminStatement = $pdo->query('SELECT id FROM users WHERE role = "admin" ORDER BY id ASC LIMIT 1');
            $createdByUserId = (int) ($adminStatement->fetchColumn() ?: 0) ?: null;
        }

        $insertLayout = $pdo->prepare('INSERT INTO venue_layouts (name, is_active, created_by, created_at, updated_at) VALUES (?, 1, ?, NOW(), NOW())');
        $insertLayout->execute(['Default U-Shape MUST Pitch', $createdByUserId]);
        $layoutId = (int) $pdo->lastInsertId();
    }

    $countStatement = $pdo->prepare('SELECT COUNT(*) FROM layout_elements WHERE layout_id = ?');
    $countStatement->execute([$layoutId]);
    if ((int) $countStatement->fetchColumn() > 0) {
        return;
    }

    $elements = [
        ['tent_100', 'TENT-01', '100', 8, 'corporate', 'corporate_sponsors', 520, 120, 190, 110, 0, '1', 1],
        ['tent_100', 'TENT-02', '100', 8, 'corporate', 'corporate_sponsors', 290, 120, 190, 110, 0, '2', 2],
        ['tent_100', 'TENT-03', '100', 8, 'corporate', 'corporate_sponsors', 760, 1030, 190, 110, 0, '3', 3],
        ['tent_100', 'TENT-04', '100', 8, 'sme', 'retail_commercial', 530, 1030, 190, 110, 0, '4', 4],
        ['tent_100', 'TENT-05', '100', 8, 'sme', 'retail_commercial', 300, 1030, 190, 110, 0, '5', 5],
        ['tent_50', 'TENT-06', '50', 4, 'sme', 'retail_commercial', 520, 440, 120, 120, 0, '6', 6],
        ['tent_50', 'TENT-07', '50', 4, 'sme', 'retail_commercial', 520, 610, 120, 120, 0, '7', 7],
        ['tent_50', 'TENT-08', '50', 4, 'sme', 'retail_commercial', 520, 780, 120, 120, 0, '8', 8],
        ['tent_50', 'TENT-09', '50', 4, 'student', 'students_innovation', 350, 440, 120, 120, 0, '9', 9],
        ['tent_50', 'TENT-10', '50', 4, 'student', 'students_innovation', 350, 610, 120, 120, 0, '10', 10],
        ['tent_50', 'TENT-11', '50', 4, 'student', 'students_innovation', 350, 780, 120, 120, 0, '11', 11],
        ['tent_50', 'TENT-12', '50', 5, 'corporate', 'corporate_sponsors', 80, 120, 120, 190, 0, '12', 12],
        ['tent_50', 'TENT-13', '50', 5, 'corporate', 'corporate_sponsors', 80, 330, 120, 190, 0, '13', 13],
        ['tent_50', 'TENT-14', '50', 5, 'corporate', 'corporate_sponsors', 80, 540, 120, 190, 0, '14', 14],
        ['tent_50', 'TENT-15', '50', 1, 'food_beverage', 'food_beverage', 80, 750, 120, 190, 0, '15 Beer', 15],
        ['stage', null, null, null, null, null, 860, 430, 170, 420, 0, 'STAGE', 16],
        ['reg_desk', null, null, null, null, null, 910, 250, 100, 110, 0, 'RD', 17],
        ['waste_point', null, null, null, null, null, 80, 1120, 100, 100, 0, 'WCP', 18],
        ['toilet_m', null, null, null, null, null, 80, 1380, 100, 110, 0, 'MT (M)', 19],
        ['toilet_f', null, null, null, null, null, 230, 1380, 100, 110, 0, 'MT (F)', 20],
        ['walkway', null, null, null, null, null, 730, 40, 70, 110, 0, 'ENTRY FLOW', 21],
        ['walkway', null, null, null, null, null, 120, 1260, 70, 110, 0, 'EXIT FLOW', 22],
    ];

    $insert = $pdo->prepare(
        'INSERT INTO layout_elements (layout_id, element_type, tent_group_code, tent_type, stall_count, category, u_zone, x, y, width, height, rotation, label, z_index, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
    );

    foreach ($elements as $element) {
        $insert->execute(array_merge([$layoutId], $element));
    }
}

function layout_arrangement_rules_by_tent(PDO $pdo): array
{
    $rules = [];
    $rows = $pdo->query(
        'SELECT tcr.tent_code, tcr.max_stalls, tar.arrangement_key, tar.number_of_stalls, tar.arrangement_name, tar.stall_class
         FROM tent_capacity_rules tcr
         INNER JOIN tent_arrangement_rules tar ON tar.tent_code = tcr.tent_code'
    )->fetchAll();

    foreach ($rows as $row) {
        $rules[(string) $row['tent_code']][(int) $row['number_of_stalls']] = $row;
    }

    return $rules;
}

function active_venue_layout(PDO $pdo): ?array
{
    $layout = $pdo->query('SELECT id, name, is_active, updated_at FROM venue_layouts WHERE is_active = 1 ORDER BY updated_at DESC, id DESC LIMIT 1')->fetch();
    if (!$layout) {
        $layout = $pdo->query('SELECT id, name, is_active, updated_at FROM venue_layouts ORDER BY updated_at DESC, id DESC LIMIT 1')->fetch();
    }

    return $layout ?: null;
}

function layout_tent_groups_for_layout(PDO $pdo, int $layoutId): array
{
    $statement = $pdo->prepare(
        'SELECT tent_group_code, tent_type, stall_count, category, u_zone
         FROM layout_elements
         WHERE layout_id = ?
           AND element_type IN ("tent_50", "tent_100")
           AND tent_group_code IS NOT NULL
           AND tent_group_code <> ""
         ORDER BY z_index ASC, id ASC'
    );
    $statement->execute([$layoutId]);

    $groups = [];
    foreach ($statement->fetchAll() as $row) {
        $group = strtoupper(trim((string) $row['tent_group_code']));
        if ($group === '') {
            continue;
        }
        $groups[$group] = [
            'tent_type' => (string) $row['tent_type'],
            'stall_count' => (int) $row['stall_count'],
            'category' => (string) $row['category'],
            'u_zone' => $row['u_zone'] !== null ? (string) $row['u_zone'] : null,
        ];
    }

    return $groups;
}

function layout_blocked_stalls(PDO $pdo, array $tentGroups): array
{
    if (!$tentGroups) {
        return [];
    }

    $blocked = [];
    $statement = $pdo->prepare(
        'SELECT s.stall_number, s.tent_group_code, u.full_name, a.payment_status
         FROM stalls s
         LEFT JOIN users u ON u.id = s.allocated_to_user_id
         LEFT JOIN applications a ON a.user_id = s.allocated_to_user_id
         WHERE s.tent_group_code = ?
           AND (s.allocated_to_user_id IS NOT NULL OR a.payment_status IN ("Pending Verification", "Payment Received"))'
    );

    foreach ($tentGroups as $group) {
        $statement->execute([$group]);
        foreach ($statement->fetchAll() as $row) {
            $blocked[] = $row['tent_group_code'] . ' / ' . $row['stall_number'] . ' - ' . ($row['full_name'] ?: 'assigned applicant') . ' (' . ($row['payment_status'] ?: 'allocated') . ')';
        }
    }

    return $blocked;
}

function sync_layout_stalls(PDO $pdo, array $newTentGroups, array $rules): void
{
    $existingStatement = $pdo->prepare('SELECT * FROM stalls WHERE tent_group_code = ? ORDER BY id ASC');
    $updateStatement = $pdo->prepare('UPDATE stalls SET stall_location = ?, stall_type = ?, tent_code = ?, arrangement_key = ?, layout_zone = ?, updated_at = NOW() WHERE id = ?');
    $insertStatement = $pdo->prepare('INSERT INTO stalls (stall_number, stall_location, stall_type, tent_group_code, tent_code, arrangement_key, layout_zone, is_allocated, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, 0, NOW(), NOW())');
    $deleteStatement = $pdo->prepare('DELETE FROM stalls WHERE id = ? AND allocated_to_user_id IS NULL');

    foreach ($newTentGroups as $group => $metadata) {
        $tentType = (string) $metadata['tent_type'];
        $stallCount = (int) $metadata['stall_count'];
        $rule = $rules[$tentType][$stallCount] ?? null;
        if (!$rule) {
            throw new RuntimeException('Invalid stall count for ' . $group . '.');
        }

        $arrangementKey = (string) $rule['arrangement_key'];
        $stallType = (string) $rule['arrangement_name'];
        $uZone = $metadata['u_zone'] ?: null;

        $existingStatement->execute([$group]);
        $existing = $existingStatement->fetchAll();
        if (count($existing) > $stallCount) {
            $extras = array_slice($existing, $stallCount);
            $blocked = layout_blocked_stalls($pdo, [$group]);
            if ($blocked) {
                throw new RuntimeException('Cannot reduce or delete assigned stalls: ' . implode('; ', $blocked));
            }
            foreach ($extras as $stall) {
                $deleteStatement->execute([(int) $stall['id']]);
            }
            $existing = array_slice($existing, 0, $stallCount);
        }

        foreach ($existing as $stall) {
            $updateStatement->execute([$uZone, $stallType, $tentType, $arrangementKey, $uZone, (int) $stall['id']]);
        }

        for ($i = count($existing) + 1; $i <= $stallCount; $i++) {
            $stallNumber = $group . '-' . str_pad((string) $i, 2, '0', STR_PAD_LEFT);
            $insertStatement->execute([$stallNumber, $uZone, $stallType, $group, $tentType, $arrangementKey, $uZone]);
        }
    }
}

function sync_active_layout_stalls(PDO $pdo): ?array
{
    $layout = active_venue_layout($pdo);
    if (!$layout) {
        return null;
    }

    $tentGroups = layout_tent_groups_for_layout($pdo, (int) $layout['id']);
    if ($tentGroups) {
        sync_layout_stalls($pdo, $tentGroups, layout_arrangement_rules_by_tent($pdo));
    }

    return $layout;
}

function layout_stalls_select_sql(string $whereClause = ''): string
{
    return 'SELECT s.*, le.label AS tent_label, le.element_type AS layout_element_type, le.stall_count AS layout_stall_count,
                   le.category AS layout_category, le.u_zone AS layout_u_zone, le.z_index AS layout_z_index,
                   vlz.zone_name, tar.arrangement_name,
                   u.full_name, fr.business_name, a.id AS allocated_application_id
            FROM stalls s
            INNER JOIN (
                SELECT tent_group_code, MIN(id) AS element_id
                FROM layout_elements
                WHERE layout_id = ?
                  AND element_type IN ("tent_50", "tent_100")
                  AND tent_group_code IS NOT NULL
                  AND tent_group_code <> ""
                GROUP BY tent_group_code
            ) active_tents ON active_tents.tent_group_code = s.tent_group_code
            INNER JOIN layout_elements le ON le.id = active_tents.element_id
            LEFT JOIN venue_layout_zones vlz ON vlz.zone_key = le.u_zone
            LEFT JOIN tent_arrangement_rules tar ON tar.tent_code = s.tent_code AND tar.arrangement_key = s.arrangement_key
            LEFT JOIN users u ON u.id = s.allocated_to_user_id
            LEFT JOIN applications a ON a.user_id = u.id
            LEFT JOIN form_responses fr ON fr.id = a.form_response_id
            ' . $whereClause . '
            ORDER BY le.z_index ASC, s.stall_number ASC';
}

function fetch_layout_stalls(PDO $pdo, int $layoutId, bool $availableOnly = false): array
{
    $where = $availableOnly ? 'WHERE s.is_allocated = 0' : '';
    $statement = $pdo->prepare(layout_stalls_select_sql($where));
    $statement->execute([$layoutId]);
    return $statement->fetchAll();
}

function fetch_layout_stall(PDO $pdo, int $layoutId, int $stallId): ?array
{
    $statement = $pdo->prepare(layout_stalls_select_sql('WHERE s.id = ?'));
    $statement->execute([$layoutId, $stallId]);
    $stall = $statement->fetch();
    return $stall ?: null;
}

function layout_stall_location_label(array $stall): string
{
    $parts = [];
    if (!empty($stall['tent_group_code'])) {
        $parts[] = 'Tent ' . $stall['tent_group_code'];
    }
    if (!empty($stall['zone_name'])) {
        $parts[] = (string) $stall['zone_name'];
    } elseif (!empty($stall['layout_zone'])) {
        $parts[] = (string) $stall['layout_zone'];
    } elseif (!empty($stall['stall_location'])) {
        $parts[] = (string) $stall['stall_location'];
    }
    if (!empty($stall['tent_code'])) {
        $parts[] = (string) $stall['tent_code'] . '-seater';
    }

    return $parts ? implode(' / ', $parts) : 'Location to be confirmed';
}

function refresh_application_stall_assignment(PDO $pdo, int $userId): array
{
    $hasLayoutZones = db_table_exists($pdo, 'venue_layout_zones');
    $statement = $pdo->prepare(
        'SELECT s.*' . ($hasLayoutZones ? ', vlz.zone_name' : '') . '
         FROM stalls s
         ' . ($hasLayoutZones ? 'LEFT JOIN venue_layout_zones vlz ON vlz.zone_key = s.layout_zone' : '') . '
         WHERE s.allocated_to_user_id = ?
         ORDER BY s.tent_group_code ASC, s.stall_number ASC'
    );
    $statement->execute([$userId]);
    $stalls = $statement->fetchAll();

    $numbers = [];
    $locations = [];
    foreach ($stalls as $stall) {
        $numbers[] = (string) $stall['stall_number'];
        $location = layout_stall_location_label($stall);
        if (!in_array($location, $locations, true)) {
            $locations[] = $location;
        }
    }

    $pdo->prepare('UPDATE applications SET assigned_stall_number = ?, assigned_stall_location = ?, updated_at = NOW() WHERE user_id = ?')
        ->execute([
            $numbers ? implode(', ', $numbers) : null,
            $locations ? implode('; ', $locations) : null,
            $userId,
        ]);

    return $stalls;
}
