CREATE TABLE IF NOT EXISTS form_responses (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    submitted_at DATETIME NULL,
    full_name VARCHAR(190) NULL,
    email VARCHAR(190) NULL,
    phone VARCHAR(60) NULL,
    normalized_phone VARCHAR(30) NULL,
    student_status VARCHAR(120) NULL,
    institution VARCHAR(190) NULL,
    program VARCHAR(190) NULL,
    year_of_study VARCHAR(80) NULL,
    business_name VARCHAR(190) NULL,
    business_nature VARCHAR(190) NULL,
    business_description TEXT NULL,
    applicant_type VARCHAR(120) NULL,
    stall_type VARCHAR(120) NULL,
    number_of_stalls INT UNSIGNED NULL,
    electricity_needed VARCHAR(120) NULL,
    equipment_needed TEXT NULL,
    table_chair_request VARCHAR(190) NULL,
    branding_space_needed VARCHAR(120) NULL,
    preferred_payment_method VARCHAR(120) NULL,
    proof_of_payment_url TEXT NULL,
    sheet_paid_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    sheet_balance_due DECIMAL(12,2) NOT NULL DEFAULT 0,
    sheet_total_due DECIMAL(12,2) NOT NULL DEFAULT 0,
    max_staff INT UNSIGNED NULL,
    payment_transaction_id VARCHAR(120) NULL,
    payment_handled_by VARCHAR(120) NULL,
    payment_recorded_at DATETIME NULL,
    rules_agreement VARCHAR(190) NULL,
    raw_data_json LONGTEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_form_email (email),
    INDEX idx_form_phone (normalized_phone),
    INDEX idx_form_business (business_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'form_responses' AND COLUMN_NAME = 'sheet_paid_amount');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE form_responses ADD COLUMN sheet_paid_amount DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER proof_of_payment_url', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'form_responses' AND COLUMN_NAME = 'sheet_balance_due');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE form_responses ADD COLUMN sheet_balance_due DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER sheet_paid_amount', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'form_responses' AND COLUMN_NAME = 'sheet_total_due');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE form_responses ADD COLUMN sheet_total_due DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER sheet_balance_due', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'form_responses' AND COLUMN_NAME = 'max_staff');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE form_responses ADD COLUMN max_staff INT UNSIGNED NULL AFTER sheet_total_due', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'form_responses' AND COLUMN_NAME = 'payment_transaction_id');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE form_responses ADD COLUMN payment_transaction_id VARCHAR(120) NULL AFTER max_staff', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'form_responses' AND COLUMN_NAME = 'payment_handled_by');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE form_responses ADD COLUMN payment_handled_by VARCHAR(120) NULL AFTER payment_transaction_id', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'form_responses' AND COLUMN_NAME = 'payment_recorded_at');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE form_responses ADD COLUMN payment_recorded_at DATETIME NULL AFTER payment_handled_by', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    form_response_id INT UNSIGNED NULL,
    full_name VARCHAR(190) NOT NULL,
    email VARCHAR(190) NULL,
    phone VARCHAR(60) NULL,
    normalized_phone VARCHAR(30) NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('applicant', 'admin') NOT NULL DEFAULT 'applicant',
    is_verified TINYINT(1) NOT NULL DEFAULT 0,
    account_status VARCHAR(30) NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_user_form_response (form_response_id),
    UNIQUE KEY uniq_user_email (email),
    UNIQUE KEY uniq_user_phone (normalized_phone),
    INDEX idx_user_role (role),
    CONSTRAINT fk_users_form_response FOREIGN KEY (form_response_id) REFERENCES form_responses(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS applications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    form_response_id INT UNSIGNED NULL,
    application_status ENUM('Pending Review', 'Needs Correction', 'Approved', 'Rejected') NOT NULL DEFAULT 'Pending Review',
    payment_status ENUM('Not Paid', 'Pending Verification', 'Payment Received', 'Payment Rejected') NOT NULL DEFAULT 'Not Paid',
    compliance_status ENUM('Not Signed', 'Signed', 'Pending Review') NOT NULL DEFAULT 'Not Signed',
    assigned_stall_number VARCHAR(80) NULL,
    assigned_stall_location VARCHAR(190) NULL,
    admin_notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_application_user (user_id),
    INDEX idx_application_form_response (form_response_id),
    INDEX idx_application_status (application_status),
    INDEX idx_payment_status (payment_status),
    CONSTRAINT fk_applications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_applications_form_response FOREIGN KEY (form_response_id) REFERENCES form_responses(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payment_uploads (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    application_id INT UNSIGNED NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    file_type VARCHAR(20) NOT NULL,
    file_size INT UNSIGNED NOT NULL,
    payment_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    payment_description VARCHAR(255) NULL,
    verification_status ENUM('Pending', 'Verified', 'Rejected') NOT NULL DEFAULT 'Pending',
    uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    verified_by INT UNSIGNED NULL,
    verified_at DATETIME NULL,
    admin_comment TEXT NULL,
    INDEX idx_payment_application (application_id),
    INDEX idx_payment_status (verification_status),
    CONSTRAINT fk_payment_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_payment_application FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE,
    CONSTRAINT fk_payment_verified_by FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payment_uploads' AND COLUMN_NAME = 'payment_amount');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE payment_uploads ADD COLUMN payment_amount DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER file_size', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payment_uploads' AND COLUMN_NAME = 'payment_description');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE payment_uploads ADD COLUMN payment_description VARCHAR(255) NULL AFTER payment_amount', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS password_reset_tokens (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_password_reset_token_hash (token_hash),
    INDEX idx_password_reset_user (user_id),
    CONSTRAINT fk_password_reset_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payment_receipts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    application_id INT UNSIGNED NULL,
    form_response_id INT UNSIGNED NULL,
    payment_upload_id INT UNSIGNED NULL,
    user_id INT UNSIGNED NULL,
    receipt_reference VARCHAR(40) NOT NULL,
    receipt_token CHAR(64) NOT NULL,
    source_type VARCHAR(30) NOT NULL DEFAULT 'sheet',
    paid_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    balance_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    total_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    payment_method VARCHAR(120) NULL,
    transaction_id VARCHAR(120) NULL,
    payment_description VARCHAR(255) NULL,
    proof_file_path VARCHAR(255) NULL,
    proof_original_filename VARCHAR(255) NULL,
    proof_file_type VARCHAR(20) NULL,
    proof_file_size INT UNSIGNED NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payment_receipts' AND COLUMN_NAME = 'proof_file_path');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE payment_receipts ADD COLUMN proof_file_path VARCHAR(255) NULL AFTER payment_description', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payment_receipts' AND COLUMN_NAME = 'proof_original_filename');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE payment_receipts ADD COLUMN proof_original_filename VARCHAR(255) NULL AFTER proof_file_path', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payment_receipts' AND COLUMN_NAME = 'proof_file_type');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE payment_receipts ADD COLUMN proof_file_type VARCHAR(20) NULL AFTER proof_original_filename', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payment_receipts' AND COLUMN_NAME = 'proof_file_size');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE payment_receipts ADD COLUMN proof_file_size INT UNSIGNED NULL AFTER proof_file_type', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS attendant_tags (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS messages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sender_id INT UNSIGNED NOT NULL,
    receiver_id INT UNSIGNED NULL,
    title VARCHAR(190) NOT NULL,
    body TEXT NOT NULL,
    message_type ENUM('direct', 'announcement') NOT NULL DEFAULT 'direct',
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_message_receiver (receiver_id),
    INDEX idx_message_type (message_type),
    CONSTRAINT fk_messages_sender FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_messages_receiver FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS announcement_recipients (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    announcement_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    read_at DATETIME NULL,
    UNIQUE KEY uniq_announcement_user (announcement_id, user_id),
    CONSTRAINT fk_announcement_message FOREIGN KEY (announcement_id) REFERENCES messages(id) ON DELETE CASCADE,
    CONSTRAINT fk_announcement_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS stalls (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    stall_number VARCHAR(80) NOT NULL,
    stall_location VARCHAR(190) NULL,
    stall_type VARCHAR(120) NULL,
    tent_group_code VARCHAR(80) NULL,
    tent_code VARCHAR(20) NULL,
    arrangement_key VARCHAR(80) NULL,
    layout_zone VARCHAR(80) NULL,
    is_allocated TINYINT(1) NOT NULL DEFAULT 0,
    allocated_to_user_id INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_stall_number (stall_number),
    INDEX idx_stall_tent_group (tent_group_code, tent_code),
    INDEX idx_stall_layout_zone (layout_zone),
    INDEX idx_stall_allocated (is_allocated),
    CONSTRAINT fk_stall_user FOREIGN KEY (allocated_to_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'stalls' AND COLUMN_NAME = 'tent_group_code');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE stalls ADD COLUMN tent_group_code VARCHAR(80) NULL AFTER stall_type', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'stalls' AND COLUMN_NAME = 'tent_code');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE stalls ADD COLUMN tent_code VARCHAR(20) NULL AFTER tent_group_code', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'stalls' AND COLUMN_NAME = 'arrangement_key');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE stalls ADD COLUMN arrangement_key VARCHAR(80) NULL AFTER tent_code', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'stalls' AND COLUMN_NAME = 'layout_zone');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE stalls ADD COLUMN layout_zone VARCHAR(80) NULL AFTER arrangement_key', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'stalls' AND INDEX_NAME = 'idx_stall_tent_group');
SET @sql = IF(@idx_exists = 0, 'ALTER TABLE stalls ADD INDEX idx_stall_tent_group (tent_group_code, tent_code)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'stalls' AND INDEX_NAME = 'idx_stall_layout_zone');
SET @sql = IF(@idx_exists = 0, 'ALTER TABLE stalls ADD INDEX idx_stall_layout_zone (layout_zone)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS compliance_documents (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    application_id INT UNSIGNED NOT NULL,
    document_status ENUM('Not Signed', 'Signed', 'Pending Review') NOT NULL DEFAULT 'Not Signed',
    signed_at DATETIME NULL,
    file_path VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_compliance_application (application_id),
    CONSTRAINT fk_compliance_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_compliance_application FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS portal_settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(120) NOT NULL,
    setting_value TEXT NULL,
    updated_at DATETIME NULL,
    UNIQUE KEY uniq_setting_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sheet_sync_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    source_url TEXT NULL,
    rows_processed INT UNSIGNED NOT NULL DEFAULT 0,
    new_records INT UNSIGNED NOT NULL DEFAULT 0,
    updated_records INT UNSIGNED NOT NULL DEFAULT 0,
    skipped_rows INT UNSIGNED NOT NULL DEFAULT 0,
    errors_json LONGTEXT NULL,
    synced_by_user_id INT UNSIGNED NULL,
    synced_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_sheet_sync_synced_at (synced_at),
    CONSTRAINT fk_sheet_sync_user FOREIGN KEY (synced_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tent_capacity_rules (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tent_arrangement_rules (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS venue_layout_zones (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'venue_layout_zones' AND COLUMN_NAME = 'map_x');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE venue_layout_zones ADD COLUMN map_x INT NULL', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'venue_layout_zones' AND COLUMN_NAME = 'map_y');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE venue_layout_zones ADD COLUMN map_y INT NULL', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'venue_layout_zones' AND COLUMN_NAME = 'map_width');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE venue_layout_zones ADD COLUMN map_width INT NULL', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'venue_layout_zones' AND COLUMN_NAME = 'map_height');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE venue_layout_zones ADD COLUMN map_height INT NULL', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS pricing_plan_rules (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS application_special_discounts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    application_id INT UNSIGNED NOT NULL,
    discount_type ENUM('fixed', 'percent') NOT NULL DEFAULT 'fixed',
    discount_value DECIMAL(12,2) NOT NULL,
    reason VARCHAR(255) NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_discount_application (application_id),
    INDEX idx_discount_created_by (created_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS venue_layouts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(190) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 0,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_venue_layout_name (name),
    INDEX idx_venue_layout_active (is_active),
    CONSTRAINT fk_venue_layout_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS layout_elements (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    layout_id INT UNSIGNED NOT NULL,
    element_type ENUM('tent_50', 'tent_100', 'stage', 'reg_desk', 'waste_point', 'toilet_m', 'toilet_f', 'walkway', 'label') NOT NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO portal_settings (setting_key, setting_value, updated_at) VALUES
('event_name', 'Freshers Expo 2026', NOW()),
('event_dates', 'Dates to be announced', NOW()),
('contact_phone', '+256 700 000000', NOW()),
('contact_email', 'expo@must.ac.ug', NOW()),
('google_form_url', '#', NOW()),
('rules_text', 'By participating in the event, all stall holders agree to operate only within their allocated space, keep their stall clean and safe, avoid selling illegal or unauthorized items, follow hygiene requirements where applicable, respect university property, follow security and electrical safety instructions, avoid excessive noise, and comply with all guidance from the organizing committee. Final stall allocation is subject to approval and signing of the compliance document.', NOW())
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW();

INSERT IGNORE INTO portal_settings (setting_key, setting_value, updated_at) VALUES
('google_sheet_url', '', NOW()),
('google_sheet_gid', '0', NOW()),
('google_sheet_auto_sync_enabled', '0', NOW()),
('google_sheet_cron_token', '', NOW()),
('google_sheet_last_sync_at', '', NOW()),
('google_sheet_last_sync_summary', '', NOW());

INSERT INTO tent_capacity_rules (tent_code, tent_name, canopy_type, min_stalls, recommended_stalls, max_stalls, footprint_sqm, hire_setup_cost, hard_rule, normal_assumption, updated_at) VALUES
('50', '50-Seater Tent', 'Single Canopy', 1, 4, 5, 54.00, 450000.00, 'Never allocate more than 5 stalls in one 50-seater tent.', 'Use 4 stalls per tent as the normal planning assumption unless the exhibitor category requires a different arrangement.', NOW()),
('100', '100-Seater Tent', 'Double Canopy', 1, 8, 10, 108.00, 850000.00, 'The tent must not exceed 10 stalls.', 'Use 8 stalls per 100-seater tent as the normal planning assumption for standard exhibitors.', NOW())
ON DUPLICATE KEY UPDATE
tent_name = VALUES(tent_name), canopy_type = VALUES(canopy_type), min_stalls = VALUES(min_stalls),
recommended_stalls = VALUES(recommended_stalls), max_stalls = VALUES(max_stalls), footprint_sqm = VALUES(footprint_sqm),
hire_setup_cost = VALUES(hire_setup_cost), hard_rule = VALUES(hard_rule), normal_assumption = VALUES(normal_assumption), updated_at = NOW();

INSERT INTO tent_arrangement_rules (tent_code, arrangement_key, arrangement_name, number_of_stalls, suitable_exhibitors, stall_class, walkway_ratio, setup_extra) VALUES
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
ON DUPLICATE KEY UPDATE
arrangement_name = VALUES(arrangement_name), number_of_stalls = VALUES(number_of_stalls), suitable_exhibitors = VALUES(suitable_exhibitors),
stall_class = VALUES(stall_class), walkway_ratio = VALUES(walkway_ratio), setup_extra = VALUES(setup_extra);

INSERT INTO venue_layout_zones (zone_key, zone_name, u_position, traffic_level, notes) VALUES
('corporate_sponsors', 'Corporate & Sponsors', 'Business distribution zone', 'Not specified', 'Tents 1, 2, 3, 12, 13, 14. Banks, telecoms, insurance, universities, government agencies, and internet providers.'),
('retail_commercial', 'Retail & Commercial', 'Business distribution zone', 'Not specified', 'Tents 4, 5, 6, 7, 8. Electronics, furniture, cosmetics, fashion, phones, printing, supermarkets, and hardware.'),
('students_innovation', 'Students & Innovation', 'Business distribution zone', 'Not specified', 'Tents 9, 10, 11. Student businesses, clubs, innovation projects, startups, and campus entrepreneurs.'),
('food_beverage', 'Food & Beverage', 'Business distribution zone', 'Not specified', 'Tent 15 mandatory beer garden. Reserved for Nile Breweries, Uganda Breweries, Bell Lager, Nile Special, Tusker, Guinness, Pilsner, Castle Lite, spirits and beverage promotions subject to university approval, lounge seating, and a responsible consumption area. Adjacent to the entertainment stage as the event social hub.')
ON DUPLICATE KEY UPDATE
zone_name = VALUES(zone_name), u_position = VALUES(u_position), traffic_level = VALUES(traffic_level), notes = VALUES(notes);

UPDATE layout_elements SET u_zone = CASE
    WHEN tent_group_code IN ('TENT-01', 'TENT-02', 'TENT-03', 'TENT-12', 'TENT-13', 'TENT-14') THEN 'corporate_sponsors'
    WHEN tent_group_code IN ('TENT-04', 'TENT-05', 'TENT-06', 'TENT-07', 'TENT-08') THEN 'retail_commercial'
    WHEN tent_group_code IN ('TENT-09', 'TENT-10', 'TENT-11') THEN 'students_innovation'
    WHEN tent_group_code = 'TENT-15' THEN 'food_beverage'
    ELSE u_zone
END
WHERE element_type IN ('tent_50', 'tent_100');

UPDATE layout_elements SET u_zone = NULL WHERE u_zone IN ('entry_arm', 'stage_front', 'corner_turn', 'exit_arm', 'food_court_edge', 'service_back');

UPDATE stalls SET layout_zone = CASE
    WHEN tent_group_code IN ('TENT-01', 'TENT-02', 'TENT-03', 'TENT-12', 'TENT-13', 'TENT-14') THEN 'corporate_sponsors'
    WHEN tent_group_code IN ('TENT-04', 'TENT-05', 'TENT-06', 'TENT-07', 'TENT-08') THEN 'retail_commercial'
    WHEN tent_group_code IN ('TENT-09', 'TENT-10', 'TENT-11') THEN 'students_innovation'
    WHEN tent_group_code = 'TENT-15' THEN 'food_beverage'
    ELSE layout_zone
END;

UPDATE stalls SET layout_zone = NULL WHERE layout_zone IN ('entry_arm', 'stage_front', 'corner_turn', 'exit_arm', 'food_court_edge', 'service_back');

DELETE FROM venue_layout_zones WHERE zone_key IN ('entry_arm', 'stage_front', 'corner_turn', 'exit_arm', 'food_court_edge', 'service_back');

DELETE FROM pricing_plan_rules;
INSERT INTO pricing_plan_rules (rule_name, business_nature_match, student_status_match, price_per_stall, priority, is_active, notes, created_at, updated_at) VALUES
('Electronics and gadgets - Student', 'Electronics and gadgets', 'Yes', 100000.00, 10, 1, 'Bazaar tariff for student exhibitors.', NOW(), NOW()),
('Electronics and gadgets - Non Student', 'Electronics and gadgets', 'Not a student', 200000.00, 11, 1, 'Bazaar tariff for non-student exhibitors.', NOW(), NOW()),
('Food and drinks - Student', 'Food and drinks', 'Yes', 70000.00, 12, 1, 'Bazaar tariff for student exhibitors.', NOW(), NOW()),
('Food and drinks - Non Student', 'Food and drinks', 'Not a student', 140000.00, 13, 1, 'Bazaar tariff for non-student exhibitors.', NOW(), NOW()),
('NGO / awareness campaign - Student', 'NGO / awareness campaign', 'Yes', 50000.00, 14, 1, 'Bazaar tariff for student exhibitors.', NOW(), NOW()),
('NGO / awareness campaign - Non Student', 'NGO / awareness campaign', 'Not a student', 100000.00, 15, 1, 'Bazaar tariff for non-student exhibitors.', NOW(), NOW()),
('Agriculture and agro-consultancy - Student', 'Agriculture and agro-consultancy', 'Yes', 50000.00, 16, 1, 'Bazaar tariff for student exhibitors.', NOW(), NOW()),
('Agriculture and agro-consultancy - Non Student', 'Agriculture and agro-consultancy', 'Not a student', 70000.00, 17, 1, 'Bazaar tariff for non-student exhibitors.', NOW(), NOW()),
('Health and wellness - Student', 'Health and wellness', 'Yes', 50000.00, 18, 1, 'Bazaar tariff for student exhibitors.', NOW(), NOW()),
('Health and wellness - Non Student', 'Health and wellness', 'Not a student', 100000.00, 19, 1, 'Bazaar tariff for non-student exhibitors.', NOW(), NOW()),
('Entertainment / gaming - Student', 'Entertainment / gaming', 'Yes', 70000.00, 20, 1, 'Bazaar tariff for student exhibitors.', NOW(), NOW()),
('Entertainment / gaming - Non Student', 'Entertainment / gaming', 'Not a student', 100000.00, 21, 1, 'Bazaar tariff for non-student exhibitors.', NOW(), NOW()),
('Bedding and hostel items - Student', 'Bedding and hostel items', 'Yes', 150000.00, 22, 1, 'Bazaar tariff for student exhibitors.', NOW(), NOW()),
('Bedding and hostel items - Non Student', 'Bedding and hostel items', 'Not a student', 200000.00, 23, 1, 'Bazaar tariff for non-student exhibitors.', NOW(), NOW()),
('Clothing and fashion - Student', 'Clothing and fashion', 'Yes', 80000.00, 24, 1, 'Bazaar tariff for student exhibitors.', NOW(), NOW()),
('Clothing and fashion - Non Student', 'Clothing and fashion', 'Not a student', 130000.00, 25, 1, 'Bazaar tariff for non-student exhibitors.', NOW(), NOW()),
('Technology / innovation services - Student', 'Technology / innovation services', 'Yes', 50000.00, 26, 1, 'Bazaar tariff for student exhibitors.', NOW(), NOW()),
('Technology / innovation services - Non Student', 'Technology / innovation services', 'Not a student', 70000.00, 27, 1, 'Bazaar tariff for non-student exhibitors.', NOW(), NOW()),
('Laundry services - Student', 'Laundry services', 'Yes', 50000.00, 28, 1, 'Bazaar tariff for student exhibitors.', NOW(), NOW()),
('Laundry services - Non Student', 'Laundry services', 'Not a student', 70000.00, 29, 1, 'Bazaar tariff for non-student exhibitors.', NOW(), NOW()),
('Girly essentials - Student', 'Girly essentials', 'Yes', 50000.00, 30, 1, 'Bazaar tariff for student exhibitors.', NOW(), NOW()),
('Girly essentials - Non Student', 'Girly essentials', 'Not a student', 50000.00, 31, 1, 'Bazaar tariff for non-student exhibitors.', NOW(), NOW()),
('Art and crafts - Student', 'Art and crafts', 'Yes', 50000.00, 32, 1, 'Bazaar tariff for student exhibitors.', NOW(), NOW()),
('Art and crafts - Non Student', 'Art and crafts', 'Not a student', 70000.00, 33, 1, 'Bazaar tariff for non-student exhibitors.', NOW(), NOW()),
('Gas sales and refilling - Student', 'Gas sales and refilling', 'Yes', 150000.00, 34, 1, 'Bazaar tariff for student exhibitors.', NOW(), NOW()),
('Gas sales and refilling - Non Student', 'Gas sales and refilling', 'Not a student', 200000.00, 35, 1, 'Bazaar tariff for non-student exhibitors.', NOW(), NOW()),
('Cosmetics and beauty products - Student', 'Cosmetics and beauty products', 'Yes', 100000.00, 36, 1, 'Bazaar tariff for student exhibitors.', NOW(), NOW()),
('Cosmetics and beauty products - Non Student', 'Cosmetics and beauty products', 'Not a student', 150000.00, 37, 1, 'Bazaar tariff for non-student exhibitors.', NOW(), NOW()),
('Event planning and surprises - Student', 'Event planning and surprises', 'Yes', 50000.00, 38, 1, 'Bazaar tariff for student exhibitors.', NOW(), NOW()),
('Event planning and surprises - Non Student', 'Event planning and surprises', 'Not a student', 70000.00, 39, 1, 'Bazaar tariff for non-student exhibitors.', NOW(), NOW()),
('Stationery and printing - Student', 'Stationery and printing', 'Yes', 50000.00, 40, 1, 'Bazaar tariff for student exhibitors.', NOW(), NOW()),
('Stationery and printing - Non Student', 'Stationery and printing', 'Not a student', 70000.00, 41, 1, 'Bazaar tariff for non-student exhibitors.', NOW(), NOW()),
('Safety gear - Student', 'Safety gear', 'Yes', 150000.00, 42, 1, 'Bazaar tariff for student exhibitors.', NOW(), NOW()),
('Safety gear - Non Student', 'Safety gear', 'Not a student', 200000.00, 43, 1, 'Bazaar tariff for non-student exhibitors.', NOW(), NOW()),
('Mobile services - Student', 'Mobile services', 'Yes', 50000.00, 44, 1, 'Bazaar tariff for student exhibitors.', NOW(), NOW()),
('Mobile services - Non Student', 'Mobile services', 'Not a student', 70000.00, 45, 1, 'Bazaar tariff for non-student exhibitors.', NOW(), NOW()),
('Corporate Brand - Student', 'Corporate Brand', 'Yes', 100000.00, 46, 1, 'Bazaar tariff for student exhibitors.', NOW(), NOW()),
('Corporate Brand - Non Student', 'Corporate Brand', 'Not a student', 150000.00, 47, 1, 'Bazaar tariff for non-student exhibitors.', NOW(), NOW()),
('House hold items - Student', 'House hold items', 'Yes', 80000.00, 48, 1, 'Bazaar tariff for student exhibitors.', NOW(), NOW()),
('House hold items - Non Student', 'House hold items', 'Not a student', 100000.00, 49, 1, 'Bazaar tariff for non-student exhibitors.', NOW(), NOW());

INSERT INTO venue_layouts (name, is_active, created_by, created_at, updated_at)
SELECT 'Default U-Shape MUST Pitch', 1, (SELECT id FROM users WHERE role = 'admin' ORDER BY id ASC LIMIT 1), NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM venue_layouts WHERE name = 'Default U-Shape MUST Pitch');

SET @default_layout_id = (SELECT id FROM venue_layouts WHERE name = 'Default U-Shape MUST Pitch' LIMIT 1);

INSERT INTO layout_elements (layout_id, element_type, tent_group_code, tent_type, stall_count, category, u_zone, x, y, width, height, rotation, label, z_index)
SELECT @default_layout_id, 'tent_100', 'TENT-01', '100', 8, 'corporate', 'corporate_sponsors', 520, 120, 190, 110, 0, '1', 1 FROM DUAL WHERE @default_layout_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM layout_elements WHERE layout_id = @default_layout_id)
UNION ALL SELECT @default_layout_id, 'tent_100', 'TENT-02', '100', 8, 'corporate', 'corporate_sponsors', 290, 120, 190, 110, 0, '2', 2 FROM DUAL WHERE @default_layout_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM layout_elements WHERE layout_id = @default_layout_id)
UNION ALL SELECT @default_layout_id, 'tent_100', 'TENT-03', '100', 8, 'corporate', 'corporate_sponsors', 760, 1030, 190, 110, 0, '3', 3 FROM DUAL WHERE @default_layout_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM layout_elements WHERE layout_id = @default_layout_id)
UNION ALL SELECT @default_layout_id, 'tent_100', 'TENT-04', '100', 8, 'sme', 'retail_commercial', 530, 1030, 190, 110, 0, '4', 4 FROM DUAL WHERE @default_layout_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM layout_elements WHERE layout_id = @default_layout_id)
UNION ALL SELECT @default_layout_id, 'tent_100', 'TENT-05', '100', 8, 'sme', 'retail_commercial', 300, 1030, 190, 110, 0, '5', 5 FROM DUAL WHERE @default_layout_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM layout_elements WHERE layout_id = @default_layout_id)
UNION ALL SELECT @default_layout_id, 'tent_50', 'TENT-06', '50', 4, 'sme', 'retail_commercial', 520, 440, 120, 120, 0, '6', 6 FROM DUAL WHERE @default_layout_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM layout_elements WHERE layout_id = @default_layout_id)
UNION ALL SELECT @default_layout_id, 'tent_50', 'TENT-07', '50', 4, 'sme', 'retail_commercial', 520, 610, 120, 120, 0, '7', 7 FROM DUAL WHERE @default_layout_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM layout_elements WHERE layout_id = @default_layout_id)
UNION ALL SELECT @default_layout_id, 'tent_50', 'TENT-08', '50', 4, 'sme', 'retail_commercial', 520, 780, 120, 120, 0, '8', 8 FROM DUAL WHERE @default_layout_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM layout_elements WHERE layout_id = @default_layout_id)
UNION ALL SELECT @default_layout_id, 'tent_50', 'TENT-09', '50', 4, 'student', 'students_innovation', 350, 440, 120, 120, 0, '9', 9 FROM DUAL WHERE @default_layout_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM layout_elements WHERE layout_id = @default_layout_id)
UNION ALL SELECT @default_layout_id, 'tent_50', 'TENT-10', '50', 4, 'student', 'students_innovation', 350, 610, 120, 120, 0, '10', 10 FROM DUAL WHERE @default_layout_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM layout_elements WHERE layout_id = @default_layout_id)
UNION ALL SELECT @default_layout_id, 'tent_50', 'TENT-11', '50', 4, 'student', 'students_innovation', 350, 780, 120, 120, 0, '11', 11 FROM DUAL WHERE @default_layout_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM layout_elements WHERE layout_id = @default_layout_id)
UNION ALL SELECT @default_layout_id, 'tent_50', 'TENT-12', '50', 5, 'corporate', 'corporate_sponsors', 80, 120, 120, 190, 0, '12', 12 FROM DUAL WHERE @default_layout_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM layout_elements WHERE layout_id = @default_layout_id)
UNION ALL SELECT @default_layout_id, 'tent_50', 'TENT-13', '50', 5, 'corporate', 'corporate_sponsors', 80, 330, 120, 190, 0, '13', 13 FROM DUAL WHERE @default_layout_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM layout_elements WHERE layout_id = @default_layout_id)
UNION ALL SELECT @default_layout_id, 'tent_50', 'TENT-14', '50', 5, 'corporate', 'corporate_sponsors', 80, 540, 120, 190, 0, '14', 14 FROM DUAL WHERE @default_layout_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM layout_elements WHERE layout_id = @default_layout_id)
UNION ALL SELECT @default_layout_id, 'tent_50', 'TENT-15', '50', 1, 'food_beverage', 'food_beverage', 80, 750, 120, 190, 0, '15 Beer', 15 FROM DUAL WHERE @default_layout_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM layout_elements WHERE layout_id = @default_layout_id)
UNION ALL SELECT @default_layout_id, 'stage', NULL, NULL, NULL, NULL, NULL, 860, 430, 170, 420, 0, 'STAGE', 16 FROM DUAL WHERE @default_layout_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM layout_elements WHERE layout_id = @default_layout_id)
UNION ALL SELECT @default_layout_id, 'reg_desk', NULL, NULL, NULL, NULL, NULL, 910, 250, 100, 110, 0, 'RD', 17 FROM DUAL WHERE @default_layout_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM layout_elements WHERE layout_id = @default_layout_id)
UNION ALL SELECT @default_layout_id, 'waste_point', NULL, NULL, NULL, NULL, NULL, 80, 1120, 100, 100, 0, 'WCP', 18 FROM DUAL WHERE @default_layout_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM layout_elements WHERE layout_id = @default_layout_id)
UNION ALL SELECT @default_layout_id, 'toilet_m', NULL, NULL, NULL, NULL, NULL, 80, 1380, 100, 110, 0, 'MT (M)', 19 FROM DUAL WHERE @default_layout_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM layout_elements WHERE layout_id = @default_layout_id)
UNION ALL SELECT @default_layout_id, 'toilet_f', NULL, NULL, NULL, NULL, NULL, 230, 1380, 100, 110, 0, 'MT (F)', 20 FROM DUAL WHERE @default_layout_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM layout_elements WHERE layout_id = @default_layout_id)
UNION ALL SELECT @default_layout_id, 'walkway', NULL, NULL, NULL, NULL, NULL, 730, 40, 70, 110, 0, 'ENTRY FLOW', 21 FROM DUAL WHERE @default_layout_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM layout_elements WHERE layout_id = @default_layout_id)
UNION ALL SELECT @default_layout_id, 'walkway', NULL, NULL, NULL, NULL, NULL, 120, 1260, 70, 110, 0, 'EXIT FLOW', 22 FROM DUAL WHERE @default_layout_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM layout_elements WHERE layout_id = @default_layout_id);

-- Default demo logins. Change these passwords immediately after installation.
INSERT INTO form_responses (
    submitted_at, full_name, email, phone, normalized_phone, student_status,
    institution, program, year_of_study, business_name, business_nature,
    business_description, applicant_type, stall_type, number_of_stalls,
    electricity_needed, preferred_payment_method, rules_agreement, created_at, updated_at
)
SELECT
    NOW(), 'Demo Applicant', 'applicant@expo2026.test', '0771234567', '256771234567',
    'Student', 'Mbarara University of Science and Technology', 'Bachelor of Science', 'Year 2',
    'Fresh Coffee Co.', 'Food and Beverages', 'Campus coffee and snacks stall.',
    'Student', '3x3m Premium Corner', 1, 'Yes', 'Mobile Money', 'Agreed', NOW(), NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM form_responses
    WHERE LOWER(email) = 'applicant@expo2026.test' OR normalized_phone = '256771234567'
);

INSERT INTO users (
    full_name, email, phone, normalized_phone, password_hash, role,
    is_verified, account_status, created_at, updated_at
)
SELECT
    'Expo Admin', 'admin@expo2026.test', '0770000000', '256770000000',
    '$2y$10$6M0UX0cuUeumE.wNPuKaRe/4Cqsp3USwoZxXvrAY8TvDaKYm1RtiG',
    'admin', 1, 'active', NOW(), NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM users
    WHERE LOWER(email) = 'admin@expo2026.test' OR normalized_phone = '256770000000'
);

INSERT INTO users (
    form_response_id, full_name, email, phone, normalized_phone, password_hash,
    role, is_verified, account_status, created_at, updated_at
)
SELECT
    fr.id, fr.full_name, fr.email, fr.phone, fr.normalized_phone,
    '$2y$10$hPsPcpaplxPYGIUBU2v5n.He8OFfBMk8gh2nmZTGBIbq6WEOg9/dG',
    'applicant', 1, 'active', NOW(), NOW()
FROM form_responses fr
WHERE (LOWER(fr.email) = 'applicant@expo2026.test' OR fr.normalized_phone = '256771234567')
AND NOT EXISTS (
    SELECT 1 FROM users
    WHERE form_response_id = fr.id
       OR LOWER(email) = 'applicant@expo2026.test'
       OR normalized_phone = '256771234567'
)
ORDER BY fr.id DESC
LIMIT 1;

INSERT INTO applications (
    user_id, form_response_id, application_status, payment_status,
    compliance_status, created_at, updated_at
)
SELECT
    u.id, u.form_response_id, 'Pending Review', 'Pending Verification',
    'Pending Review', NOW(), NOW()
FROM users u
WHERE LOWER(u.email) = 'applicant@expo2026.test'
AND NOT EXISTS (SELECT 1 FROM applications WHERE user_id = u.id)
LIMIT 1;

INSERT INTO form_responses (
    submitted_at, full_name, email, phone, normalized_phone, student_status,
    institution, program, year_of_study, business_name, business_nature,
    business_description, applicant_type, stall_type, number_of_stalls,
    electricity_needed, preferred_payment_method, rules_agreement, created_at, updated_at
)
SELECT
    NOW() - INTERVAL 5 DAY, 'Sarah Smith', 'sarah@expo2026.test', '+256772111222', '256772111222',
    'Non-student', 'N/A', 'N/A', 'N/A', 'TechNova Gadgets', 'Electronics',
    'Phone accessories, earbuds, chargers, and student tech essentials.',
    'Non-student', '3x3m Standard', 1, 'No', 'Bank Transfer', 'Agreed', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM form_responses WHERE LOWER(email) = 'sarah@expo2026.test' OR normalized_phone = '256772111222');

INSERT INTO form_responses (
    submitted_at, full_name, email, phone, normalized_phone, student_status,
    institution, program, year_of_study, business_name, business_nature,
    business_description, applicant_type, stall_type, number_of_stalls,
    electricity_needed, preferred_payment_method, rules_agreement, created_at, updated_at
)
SELECT
    NOW() - INTERVAL 4 DAY, 'Mark Brown', 'mark@expo2026.test', '0773333444', '256773333444',
    'Non-student', 'N/A', 'N/A', 'N/A', 'Sustainable Living', 'Home and Lifestyle',
    'Eco-friendly home products, reusable bottles, and handmade decor.',
    'Non-student', '3x3m Premium Corner', 1, 'Yes', 'Mobile Money', 'Agreed', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM form_responses WHERE LOWER(email) = 'mark@expo2026.test' OR normalized_phone = '256773333444');

INSERT INTO form_responses (
    submitted_at, full_name, email, phone, normalized_phone, student_status,
    institution, program, year_of_study, business_name, business_nature,
    business_description, applicant_type, stall_type, number_of_stalls,
    electricity_needed, preferred_payment_method, rules_agreement, created_at, updated_at
)
SELECT
    NOW() - INTERVAL 3 DAY, 'Lisa Anderson', 'lisa@expo2026.test', '771555666', '256771555666',
    'Non-student', 'N/A', 'N/A', 'N/A', 'Anderson Law Firm', 'Professional Services',
    'Student legal awareness and consultation desk.',
    'Non-student', 'Information Desk', 1, 'No', 'Bank Transfer', 'Pending', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM form_responses WHERE LOWER(email) = 'lisa@expo2026.test' OR normalized_phone = '256771555666');

INSERT INTO form_responses (
    submitted_at, full_name, email, phone, normalized_phone, student_status,
    institution, program, year_of_study, business_name, business_nature,
    business_description, applicant_type, stall_type, number_of_stalls,
    electricity_needed, preferred_payment_method, rules_agreement, created_at, updated_at
)
SELECT
    NOW() - INTERVAL 2 DAY, 'Peter Okello', 'peter@expo2026.test', '0788111222', '256788111222',
    'Student', 'Mbarara University of Science and Technology', 'Business Administration', 'Year 1',
    'Boda Snacks', 'Food and Beverages', 'Fast snacks, juice, and packed meals for freshers.',
    'Student', 'Food Vendor Stall', 1, 'Yes', 'Mobile Money', 'Agreed', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM form_responses WHERE LOWER(email) = 'peter@expo2026.test' OR normalized_phone = '256788111222');

INSERT INTO form_responses (
    submitted_at, full_name, email, phone, normalized_phone, student_status,
    institution, program, year_of_study, business_name, business_nature,
    business_description, applicant_type, stall_type, number_of_stalls,
    electricity_needed, preferred_payment_method, rules_agreement, created_at, updated_at
)
SELECT
    NOW() - INTERVAL 1 DAY, 'Grace Nambi', 'grace@expo2026.test', '+256704555666', '256704555666',
    'Student', 'Mbarara University of Science and Technology', 'Biomedical Sciences', 'Year 3',
    'Campus Crafts', 'Art and Crafts', 'Handmade bracelets, tote bags, and campus souvenirs.',
    'Student', '2x2m Student Stall', 1, 'No', 'Mobile Money', 'Agreed', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM form_responses WHERE LOWER(email) = 'grace@expo2026.test' OR normalized_phone = '256704555666');

INSERT INTO users (
    form_response_id, full_name, email, phone, normalized_phone, password_hash,
    role, is_verified, account_status, created_at, updated_at
)
SELECT
    fr.id, fr.full_name, fr.email, fr.phone, fr.normalized_phone,
    '$2y$10$hPsPcpaplxPYGIUBU2v5n.He8OFfBMk8gh2nmZTGBIbq6WEOg9/dG',
    'applicant', 1, 'active', NOW(), NOW()
FROM form_responses fr
WHERE LOWER(fr.email) IN ('sarah@expo2026.test', 'mark@expo2026.test', 'lisa@expo2026.test', 'peter@expo2026.test', 'grace@expo2026.test')
AND NOT EXISTS (
    SELECT 1 FROM users
    WHERE form_response_id = fr.id OR LOWER(email) = LOWER(fr.email) OR normalized_phone = fr.normalized_phone
);

INSERT INTO applications (
    user_id, form_response_id, application_status, payment_status,
    compliance_status, assigned_stall_number, assigned_stall_location,
    admin_notes, created_at, updated_at
)
SELECT
    u.id, u.form_response_id,
    CASE LOWER(u.email)
        WHEN 'sarah@expo2026.test' THEN 'Approved'
        WHEN 'mark@expo2026.test' THEN 'Approved'
        WHEN 'lisa@expo2026.test' THEN 'Rejected'
        WHEN 'peter@expo2026.test' THEN 'Needs Correction'
        WHEN 'grace@expo2026.test' THEN 'Pending Review'
        ELSE 'Pending Review'
    END,
    CASE LOWER(u.email)
        WHEN 'sarah@expo2026.test' THEN 'Payment Received'
        WHEN 'mark@expo2026.test' THEN 'Payment Received'
        WHEN 'lisa@expo2026.test' THEN 'Payment Rejected'
        WHEN 'grace@expo2026.test' THEN 'Pending Verification'
        ELSE 'Not Paid'
    END,
    CASE LOWER(u.email)
        WHEN 'sarah@expo2026.test' THEN 'Signed'
        WHEN 'mark@expo2026.test' THEN 'Signed'
        WHEN 'grace@expo2026.test' THEN 'Pending Review'
        ELSE 'Not Signed'
    END,
    CASE LOWER(u.email)
        WHEN 'sarah@expo2026.test' THEN 'A-12'
        WHEN 'mark@expo2026.test' THEN 'C-04'
        ELSE NULL
    END,
    CASE LOWER(u.email)
        WHEN 'sarah@expo2026.test' THEN 'Main Walkway, Block A'
        WHEN 'mark@expo2026.test' THEN 'Innovation Lane, Block C'
        ELSE NULL
    END,
    CASE LOWER(u.email)
        WHEN 'lisa@expo2026.test' THEN 'Service category requires committee approval before resubmission.'
        WHEN 'peter@expo2026.test' THEN 'Needs clearer hygiene and food handling details.'
        ELSE 'Seeded demo record.'
    END,
    NOW(), NOW()
FROM users u
WHERE LOWER(u.email) IN ('sarah@expo2026.test', 'mark@expo2026.test', 'lisa@expo2026.test', 'peter@expo2026.test', 'grace@expo2026.test')
AND NOT EXISTS (SELECT 1 FROM applications WHERE user_id = u.id);

UPDATE applications a
INNER JOIN users u ON u.id = a.user_id
SET
    a.application_status = 'Pending Review',
    a.payment_status = 'Pending Verification',
    a.compliance_status = 'Pending Review',
    a.admin_notes = 'Demo applicant for account testing.',
    a.updated_at = NOW()
WHERE LOWER(u.email) = 'applicant@expo2026.test';

INSERT INTO stalls (stall_number, stall_location, stall_type, tent_group_code, tent_code, arrangement_key, layout_zone, is_allocated, allocated_to_user_id, created_at, updated_at)
SELECT 'A-12', 'Main Walkway, Block A', '3x3m Standard', 'TENT-A', '100', 'standard_100', 'retail_commercial', 1, u.id, NOW(), NOW()
FROM users u
WHERE LOWER(u.email) = 'sarah@expo2026.test'
AND NOT EXISTS (SELECT 1 FROM stalls WHERE stall_number = 'A-12')
LIMIT 1;

INSERT INTO stalls (stall_number, stall_location, stall_type, tent_group_code, tent_code, arrangement_key, layout_zone, is_allocated, allocated_to_user_id, created_at, updated_at)
SELECT 'C-04', 'Innovation Lane, Block C', '3x3m Premium Corner', 'TENT-C', '100', 'large_100', 'corporate_sponsors', 1, u.id, NOW(), NOW()
FROM users u
WHERE LOWER(u.email) = 'mark@expo2026.test'
AND NOT EXISTS (SELECT 1 FROM stalls WHERE stall_number = 'C-04')
LIMIT 1;

INSERT INTO stalls (stall_number, stall_location, stall_type, tent_group_code, tent_code, arrangement_key, layout_zone, is_allocated, allocated_to_user_id, created_at, updated_at)
SELECT 'B-07', 'Food Court, Block B', 'Food Vendor Stall', 'TENT-B', '100', 'medium_100', 'food_beverage', 0, NULL, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM stalls WHERE stall_number = 'B-07');

INSERT INTO stalls (stall_number, stall_location, stall_type, tent_group_code, tent_code, arrangement_key, layout_zone, is_allocated, allocated_to_user_id, created_at, updated_at)
SELECT 'D-11', 'Student Market, Block D', '2x2m Student Stall', 'TENT-D', '50', 'small_50', 'students_innovation', 0, NULL, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM stalls WHERE stall_number = 'D-11');

INSERT INTO stalls (stall_number, stall_location, stall_type, tent_group_code, tent_code, arrangement_key, layout_zone, is_allocated, allocated_to_user_id, created_at, updated_at)
SELECT 'F-02', 'Main Gate Entrance', 'Information Desk', 'TENT-F', '50', 'standard_50', 'corporate_sponsors', 0, NULL, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM stalls WHERE stall_number = 'F-02');

INSERT INTO payment_uploads (
    user_id, application_id, file_path, original_filename, file_type,
    file_size, verification_status, uploaded_at, verified_by, verified_at, admin_comment
)
SELECT u.id, a.id, 'uploads/payments/demo-receipt.pdf', 'demo-receipt.pdf', 'pdf', 842,
       'Pending', NOW() - INTERVAL 1 DAY, NULL, NULL, 'Awaiting verification.'
FROM users u
INNER JOIN applications a ON a.user_id = u.id
WHERE LOWER(u.email) = 'applicant@expo2026.test'
AND NOT EXISTS (SELECT 1 FROM payment_uploads WHERE application_id = a.id AND original_filename = 'demo-receipt.pdf')
LIMIT 1;

INSERT INTO payment_uploads (
    user_id, application_id, file_path, original_filename, file_type,
    file_size, verification_status, uploaded_at, verified_by, verified_at, admin_comment
)
SELECT u.id, a.id, 'uploads/payments/sarah-receipt.pdf', 'sarah-receipt.pdf', 'pdf', 846,
       'Verified', NOW() - INTERVAL 4 DAY, admin.id, NOW() - INTERVAL 3 DAY, 'Payment confirmed.'
FROM users u
INNER JOIN applications a ON a.user_id = u.id
INNER JOIN users admin ON LOWER(admin.email) = 'admin@expo2026.test'
WHERE LOWER(u.email) = 'sarah@expo2026.test'
AND NOT EXISTS (SELECT 1 FROM payment_uploads WHERE application_id = a.id AND original_filename = 'sarah-receipt.pdf')
LIMIT 1;

INSERT INTO payment_uploads (
    user_id, application_id, file_path, original_filename, file_type,
    file_size, verification_status, uploaded_at, verified_by, verified_at, admin_comment
)
SELECT u.id, a.id, 'uploads/payments/mark-receipt.pdf', 'mark-receipt.pdf', 'pdf', 840,
       'Verified', NOW() - INTERVAL 3 DAY, admin.id, NOW() - INTERVAL 2 DAY, 'Payment confirmed.'
FROM users u
INNER JOIN applications a ON a.user_id = u.id
INNER JOIN users admin ON LOWER(admin.email) = 'admin@expo2026.test'
WHERE LOWER(u.email) = 'mark@expo2026.test'
AND NOT EXISTS (SELECT 1 FROM payment_uploads WHERE application_id = a.id AND original_filename = 'mark-receipt.pdf')
LIMIT 1;

INSERT INTO payment_uploads (
    user_id, application_id, file_path, original_filename, file_type,
    file_size, verification_status, uploaded_at, verified_by, verified_at, admin_comment
)
SELECT u.id, a.id, 'uploads/payments/grace-receipt.pdf', 'grace-receipt.pdf', 'pdf', 839,
       'Pending', NOW() - INTERVAL 1 DAY, NULL, NULL, 'Needs finance review.'
FROM users u
INNER JOIN applications a ON a.user_id = u.id
WHERE LOWER(u.email) = 'grace@expo2026.test'
AND NOT EXISTS (SELECT 1 FROM payment_uploads WHERE application_id = a.id AND original_filename = 'grace-receipt.pdf')
LIMIT 1;

INSERT INTO compliance_documents (user_id, application_id, document_status, signed_at, file_path, created_at, updated_at)
SELECT u.id, a.id, 'Signed', NOW() - INTERVAL 3 DAY, 'uploads/compliance/sarah-compliance.pdf', NOW(), NOW()
FROM users u
INNER JOIN applications a ON a.user_id = u.id
WHERE LOWER(u.email) = 'sarah@expo2026.test'
AND NOT EXISTS (SELECT 1 FROM compliance_documents WHERE application_id = a.id)
LIMIT 1;

INSERT INTO compliance_documents (user_id, application_id, document_status, signed_at, file_path, created_at, updated_at)
SELECT u.id, a.id, 'Signed', NOW() - INTERVAL 2 DAY, 'uploads/compliance/mark-compliance.pdf', NOW(), NOW()
FROM users u
INNER JOIN applications a ON a.user_id = u.id
WHERE LOWER(u.email) = 'mark@expo2026.test'
AND NOT EXISTS (SELECT 1 FROM compliance_documents WHERE application_id = a.id)
LIMIT 1;

INSERT INTO compliance_documents (user_id, application_id, document_status, signed_at, file_path, created_at, updated_at)
SELECT u.id, a.id, 'Pending Review', NULL, 'uploads/compliance/grace-compliance.pdf', NOW(), NOW()
FROM users u
INNER JOIN applications a ON a.user_id = u.id
WHERE LOWER(u.email) = 'grace@expo2026.test'
AND NOT EXISTS (SELECT 1 FROM compliance_documents WHERE application_id = a.id)
LIMIT 1;

INSERT INTO messages (sender_id, receiver_id, title, body, message_type, is_read, created_at)
SELECT admin.id, NULL, 'Expo Orientation Briefing',
       'All applicants should monitor the portal for payment verification, compliance signing, and final stall allocation updates.',
       'announcement', 0, NOW() - INTERVAL 2 DAY
FROM users admin
WHERE LOWER(admin.email) = 'admin@expo2026.test'
AND NOT EXISTS (SELECT 1 FROM messages WHERE title = 'Expo Orientation Briefing' AND message_type = 'announcement')
LIMIT 1;

INSERT INTO messages (sender_id, receiver_id, title, body, message_type, is_read, created_at)
SELECT admin.id, applicant.id, 'Compliance Verification Required',
       'Please ensure your compliance confirmation is completed before final allocation.',
       'direct', 0, NOW() - INTERVAL 1 DAY
FROM users admin
INNER JOIN users applicant ON LOWER(applicant.email) = 'applicant@expo2026.test'
WHERE LOWER(admin.email) = 'admin@expo2026.test'
AND NOT EXISTS (SELECT 1 FROM messages WHERE title = 'Compliance Verification Required' AND receiver_id = applicant.id)
LIMIT 1;

INSERT INTO messages (sender_id, receiver_id, title, body, message_type, is_read, created_at)
SELECT admin.id, peter.id, 'Food Vendor Details Needed',
       'Please add clearer hygiene and food handling details before the committee can approve your stall.',
       'direct', 0, NOW() - INTERVAL 12 HOUR
FROM users admin
INNER JOIN users peter ON LOWER(peter.email) = 'peter@expo2026.test'
WHERE LOWER(admin.email) = 'admin@expo2026.test'
AND NOT EXISTS (SELECT 1 FROM messages WHERE title = 'Food Vendor Details Needed' AND receiver_id = peter.id)
LIMIT 1;

INSERT INTO announcement_recipients (announcement_id, user_id, is_read)
SELECT m.id, u.id, 0
FROM messages m
INNER JOIN users u ON u.role = 'applicant'
WHERE m.title = 'Expo Orientation Briefing'
AND m.message_type = 'announcement'
AND NOT EXISTS (
    SELECT 1 FROM announcement_recipients ar
    WHERE ar.announcement_id = m.id AND ar.user_id = u.id
);
