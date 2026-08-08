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
