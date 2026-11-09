CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id CHAR(6) NOT NULL,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(180) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('sub_editor', 'team_leader', 'telecaller') NOT NULL,
    mobile VARCHAR(40) NOT NULL DEFAULT '',
    date_of_birth DATE NOT NULL DEFAULT '1970-01-01',
    address VARCHAR(500) NOT NULL DEFAULT '',
    profile_image_path VARCHAR(255) DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_users_employee_id (employee_id)
);

CREATE TABLE IF NOT EXISTS employee_salaries (
    user_id BIGINT UNSIGNED PRIMARY KEY,
    joining_date DATE NOT NULL,
    salary_amount DECIMAL(12, 2) NOT NULL,
    configured_by BIGINT UNSIGNED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_employee_salary_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_employee_salary_configurer FOREIGN KEY (configured_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS company_budgets (
    id TINYINT UNSIGNED PRIMARY KEY,
    total_budget DECIMAL(14, 2) NOT NULL,
    bank_name VARCHAR(120) NOT NULL,
    account_holder_name VARCHAR(120) NOT NULL,
    account_number VARCHAR(34) NOT NULL,
    ifsc_code CHAR(11) NOT NULL,
    configured_by BIGINT UNSIGNED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_company_budget_configurer FOREIGN KEY (configured_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS budget_allocations (
    category_key VARCHAR(40) PRIMARY KEY,
    allocated_amount DECIMAL(14, 2) NOT NULL,
    configured_by BIGINT UNSIGNED,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_budget_allocation_configurer FOREIGN KEY (configured_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS office_locations (
    id TINYINT UNSIGNED PRIMARY KEY,
    address VARCHAR(255) NOT NULL,
    latitude DECIMAL(10, 7) NOT NULL,
    longitude DECIMAL(10, 7) NOT NULL,
    configured_by BIGINT UNSIGNED,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_office_location_configurer FOREIGN KEY (configured_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS employee_kyc (
    user_id BIGINT UNSIGNED PRIMARY KEY,
    full_name VARCHAR(120) NOT NULL,
    email VARCHAR(180) NOT NULL,
    mobile VARCHAR(40) NOT NULL,
    bank_name VARCHAR(120) NOT NULL,
    account_holder_name VARCHAR(120) NOT NULL,
    account_number_encrypted TEXT NOT NULL,
    ifsc_code CHAR(11) NOT NULL,
    branch_name VARCHAR(120) NOT NULL,
    aadhaar_number_encrypted TEXT NOT NULL,
    pan_number_encrypted TEXT NOT NULL,
    aadhaar_document_path VARCHAR(80) NOT NULL,
    aadhaar_document_name VARCHAR(180) NOT NULL,
    aadhaar_document_mime VARCHAR(40) NOT NULL,
    pan_document_path VARCHAR(80) NOT NULL,
    pan_document_name VARCHAR(180) NOT NULL,
    pan_document_mime VARCHAR(40) NOT NULL,
    status ENUM('submitted') NOT NULL DEFAULT 'submitted',
    submitted_at DATETIME NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_employee_kyc_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

INSERT INTO office_locations (id, address, latitude, longitude)
VALUES (1, '745Q+HPG Auto Stand, Mamillagudem Road, Mamillagudaem, Khammam, Telangana 507001, India', 17.2589440, 80.1392630)
ON DUPLICATE KEY UPDATE
    address = VALUES(address),
    latitude = VALUES(latitude),
    longitude = VALUES(longitude);

CREATE TABLE IF NOT EXISTS attendance_records (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    attendance_date DATE NOT NULL,
    check_in_at DATETIME NOT NULL,
    check_in_latitude DECIMAL(10, 7) NOT NULL,
    check_in_longitude DECIMAL(10, 7) NOT NULL,
    check_in_distance_m DECIMAL(8, 2) NOT NULL,
    check_in_selfie VARCHAR(500) NOT NULL,
    late_deduction DECIMAL(10, 2) NOT NULL DEFAULT 0,
    on_time_bonus DECIMAL(10, 2) NOT NULL DEFAULT 0,
    check_out_at DATETIME,
    check_out_latitude DECIMAL(10, 7),
    check_out_longitude DECIMAL(10, 7),
    check_out_distance_m DECIMAL(8, 2),
    check_out_selfie VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_attendance_user_date (user_id, attendance_date),
    CONSTRAINT fk_attendance_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS attendance_settings (
    id TINYINT UNSIGNED PRIMARY KEY,
    on_time_bonus_amount DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    configured_by BIGINT UNSIGNED,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_attendance_setting_configurer FOREIGN KEY (configured_by) REFERENCES users(id) ON DELETE SET NULL
);

INSERT IGNORE INTO attendance_settings (id, on_time_bonus_amount)
VALUES (1, 0.00);

CREATE TABLE IF NOT EXISTS attendance_holidays (
    holiday_date DATE PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    configured_by BIGINT UNSIGNED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_attendance_holiday_configurer FOREIGN KEY (configured_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS leave_requests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    leave_type ENUM('casual', 'sick', 'emergency', 'personal') NOT NULL DEFAULT 'casual',
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    reason VARCHAR(500) NOT NULL,
    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    reviewed_by BIGINT UNSIGNED,
    reviewed_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_leave_user_dates (user_id, start_date, end_date),
    INDEX idx_leave_status_created (status, created_at),
    CONSTRAINT fk_leave_request_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_leave_request_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS support_tickets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    subject VARCHAR(140) NOT NULL,
    category ENUM('account', 'attendance', 'salary', 'sales', 'technical', 'other') NOT NULL,
    priority ENUM('low', 'normal', 'high', 'urgent') NOT NULL DEFAULT 'normal',
    status ENUM('open', 'in_progress', 'waiting_on_employee', 'resolved', 'closed') NOT NULL DEFAULT 'open',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_support_tickets_status_updated (status, updated_at),
    INDEX idx_support_tickets_user_updated (user_id, updated_at),
    CONSTRAINT fk_support_ticket_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS support_ticket_messages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_id BIGINT UNSIGNED NOT NULL,
    sender_id BIGINT UNSIGNED NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_support_ticket_messages_ticket (ticket_id, created_at, id),
    CONSTRAINT fk_support_ticket_message_ticket FOREIGN KEY (ticket_id) REFERENCES support_tickets(id) ON DELETE CASCADE,
    CONSTRAINT fk_support_ticket_message_sender FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS daily_activities (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    platform VARCHAR(40) NOT NULL,
    bonus_amount DECIMAL(10, 2) NULL,
    submitted_at DATETIME NOT NULL,
    status ENUM('pending', 'verified', 'rejected') NOT NULL DEFAULT 'pending',
    verified_by BIGINT UNSIGNED,
    verified_at DATETIME,
    reviewed_at DATETIME,
    rejection_reason VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_daily_activity_user_time (user_id, submitted_at),
    INDEX idx_daily_activity_status_time (status, submitted_at),
    CONSTRAINT fk_daily_activity_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_daily_activity_reviewer FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS daily_activity_bonus_rates (
    platform VARCHAR(40) PRIMARY KEY,
    amount DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    configured_by BIGINT UNSIGNED,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_daily_activity_bonus_configurer FOREIGN KEY (configured_by) REFERENCES users(id) ON DELETE SET NULL
);

INSERT IGNORE INTO daily_activity_bonus_rates (platform, amount) VALUES
    ('Instagram', 2.00),
    ('Facebook', 2.00),
    ('WhatsApp Status', 2.00),
    ('LinkedIn', 0.00),
    ('YouTube', 0.00),
    ('X (Twitter)', 0.00),
    ('Telegram', 0.00),
    ('Snapchat', 0.00),
    ('Pinterest', 0.00),
    ('Threads', 0.00),
    ('Reddit', 0.00),
    ('Other', 0.00);

CREATE TABLE IF NOT EXISTS daily_activity_bonus_limits (
    id TINYINT UNSIGNED PRIMARY KEY,
    daily_cap_amount DECIMAL(10, 2) NOT NULL DEFAULT 6.00,
    configured_by BIGINT UNSIGNED,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_daily_activity_limit_configurer FOREIGN KEY (configured_by) REFERENCES users(id) ON DELETE SET NULL
);

INSERT IGNORE INTO daily_activity_bonus_limits (id, daily_cap_amount) VALUES (1, 6.00);

CREATE TABLE IF NOT EXISTS daily_activity_screenshots (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    activity_id BIGINT UNSIGNED NOT NULL,
    file_name VARCHAR(80) NOT NULL UNIQUE,
    original_name VARCHAR(180) NOT NULL,
    mime_type ENUM('image/jpeg', 'image/png', 'image/webp') NOT NULL,
    file_size INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_daily_activity_screenshots_activity (activity_id),
    CONSTRAINT fk_daily_activity_screenshot_activity FOREIGN KEY (activity_id) REFERENCES daily_activities(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS daily_activity_videos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    activity_id BIGINT UNSIGNED NOT NULL,
    file_name VARCHAR(80) NOT NULL UNIQUE,
    original_name VARCHAR(180) NOT NULL,
    mime_type ENUM('video/mp4', 'video/webm') NOT NULL,
    file_size INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_daily_activity_videos_activity (activity_id),
    CONSTRAINT fk_daily_activity_video_activity FOREIGN KEY (activity_id) REFERENCES daily_activities(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS activity_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED,
    action VARCHAR(120) NOT NULL,
    metadata JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_activity_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS credential_downloads (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL UNIQUE,
    encrypted_password TEXT,
    expires_at DATETIME NOT NULL,
    redeemed_at DATETIME,
    email_sent_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_credential_user (user_id, id),
    CONSTRAINT fk_credential_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS team_assignments (
    team_leader_id BIGINT UNSIGNED NOT NULL,
    telecaller_id BIGINT UNSIGNED NOT NULL,
    assigned_by BIGINT UNSIGNED,
    assigned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (team_leader_id, telecaller_id),
    UNIQUE KEY uq_team_assignments_telecaller (telecaller_id),
    INDEX idx_team_assignments_leader (team_leader_id),
    CONSTRAINT fk_team_assignment_leader FOREIGN KEY (team_leader_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_team_assignment_telecaller FOREIGN KEY (telecaller_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_team_assignment_creator FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS team_leader_sales_targets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    team_leader_id BIGINT UNSIGNED NOT NULL,
    target_month DATE NOT NULL,
    target_amount DECIMAL(14, 2) NOT NULL,
    configured_by BIGINT UNSIGNED,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_team_leader_sales_target_month (team_leader_id, target_month),
    INDEX idx_team_sales_target_month (target_month),
    CONSTRAINT fk_team_sales_target_leader FOREIGN KEY (team_leader_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_team_sales_target_configurer FOREIGN KEY (configured_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS team_leader_sales_target_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    target_id BIGINT UNSIGNED NOT NULL,
    team_leader_id BIGINT UNSIGNED NOT NULL,
    target_month DATE NOT NULL,
    target_amount DECIMAL(14, 2) NOT NULL,
    configured_by BIGINT UNSIGNED,
    changed_at DATETIME(6) NOT NULL,
    INDEX idx_team_target_history_leader_month (team_leader_id, target_month, changed_at),
    INDEX idx_team_target_history_target (target_id),
    CONSTRAINT fk_team_target_history_target FOREIGN KEY (target_id) REFERENCES team_leader_sales_targets(id) ON DELETE CASCADE,
    CONSTRAINT fk_team_target_history_leader FOREIGN KEY (team_leader_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_team_target_history_configurer FOREIGN KEY (configured_by) REFERENCES users(id) ON DELETE SET NULL
);

INSERT INTO team_leader_sales_target_history
    (target_id, team_leader_id, target_month, target_amount, configured_by, changed_at)
SELECT target.id, target.team_leader_id, target.target_month, target.target_amount,
       target.configured_by, target.updated_at
FROM team_leader_sales_targets AS target
WHERE NOT EXISTS (
    SELECT 1
    FROM team_leader_sales_target_history AS history
    WHERE history.target_id = target.id
);

CREATE TABLE IF NOT EXISTS team_leader_target_reward_settings (
    id TINYINT UNSIGNED PRIMARY KEY,
    reward_amount DECIMAL(14, 2) NOT NULL DEFAULT 0.00,
    configured_by BIGINT UNSIGNED,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_team_target_reward_configurer FOREIGN KEY (configured_by) REFERENCES users(id) ON DELETE SET NULL
);

INSERT IGNORE INTO team_leader_target_reward_settings (id, reward_amount) VALUES (1, 0.00);

CREATE TABLE IF NOT EXISTS team_leader_target_rewards (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    team_leader_id BIGINT UNSIGNED NOT NULL,
    target_month DATE NOT NULL,
    target_amount DECIMAL(14, 2) NOT NULL,
    achieved_sales_amount DECIMAL(14, 2) NOT NULL,
    reward_amount DECIMAL(14, 2) NOT NULL,
    granted_at DATETIME NOT NULL,
    UNIQUE KEY uq_team_leader_target_reward_month (team_leader_id, target_month),
    INDEX idx_team_target_reward_month (target_month),
    CONSTRAINT fk_team_target_reward_leader FOREIGN KEY (team_leader_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS team_grant_incentive_settings (
    id TINYINT UNSIGNED PRIMARY KEY,
    leader_share_percent DECIMAL(5, 2) NOT NULL DEFAULT 0.00,
    configured_by BIGINT UNSIGNED,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_team_grant_incentive_configurer FOREIGN KEY (configured_by) REFERENCES users(id) ON DELETE SET NULL
);

INSERT IGNORE INTO team_grant_incentive_settings (id, leader_share_percent) VALUES (1, 0.00);

CREATE TABLE IF NOT EXISTS team_grant_incentives (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    team_leader_id BIGINT UNSIGNED NOT NULL,
    target_month DATE NOT NULL,
    team_target_amount DECIMAL(14, 2) NOT NULL,
    member_target_amount DECIMAL(14, 2) NOT NULL,
    team_member_count INT UNSIGNED NOT NULL,
    team_incentive_amount DECIMAL(14, 2) NOT NULL,
    leader_share_percent DECIMAL(5, 2) NOT NULL,
    leader_incentive_amount DECIMAL(14, 2) NOT NULL,
    granted_at DATETIME NOT NULL,
    UNIQUE KEY uq_team_grant_incentive_month (team_leader_id, target_month),
    INDEX idx_team_grant_incentive_month (target_month),
    CONSTRAINT fk_team_grant_incentive_leader FOREIGN KEY (team_leader_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS telecaller_daily_sales (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    telecaller_id BIGINT UNSIGNED NOT NULL,
    sold_on DATE NOT NULL,
    amount DECIMAL(14, 2) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_telecaller_daily_sales (telecaller_id, sold_on),
    INDEX idx_telecaller_sales_date (sold_on),
    CONSTRAINT fk_telecaller_sales_user FOREIGN KEY (telecaller_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS telecaller_leads (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    telecaller_id BIGINT UNSIGNED NOT NULL,
    customer_name VARCHAR(120) NOT NULL,
    mobile_number VARCHAR(25) NOT NULL,
    district VARCHAR(100) NOT NULL,
    note VARCHAR(500) NOT NULL DEFAULT '',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_telecaller_leads_owner_created (telecaller_id, created_at),
    CONSTRAINT fk_telecaller_lead_owner FOREIGN KEY (telecaller_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS unlock_package_incentives (
    package_amount DECIMAL(10, 2) PRIMARY KEY,
    incentive_amount DECIMAL(10, 2) NULL,
    configured_by BIGINT UNSIGNED,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_unlock_package_configurer FOREIGN KEY (configured_by) REFERENCES users(id) ON DELETE SET NULL
);

INSERT IGNORE INTO unlock_package_incentives (package_amount)
VALUES (50.00), (150.00), (350.00), (750.00), (1550.00), (3150.00), (6350.00), (12750.00);

CREATE TABLE IF NOT EXISTS unlock_sales (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    telecaller_id BIGINT UNSIGNED NOT NULL,
    customer_reference VARCHAR(80) NOT NULL,
    package_amount DECIMAL(10, 2) NOT NULL,
    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    incentive_amount DECIMAL(10, 2) NULL,
    rejection_reason VARCHAR(500) NULL,
    reviewed_by BIGINT UNSIGNED,
    reviewed_at DATETIME NULL,
    sold_on DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_unlock_sales_status_date (status, sold_on),
    INDEX idx_unlock_sales_telecaller_date (telecaller_id, sold_on),
    CONSTRAINT fk_unlock_sale_telecaller FOREIGN KEY (telecaller_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_unlock_sale_package FOREIGN KEY (package_amount) REFERENCES unlock_package_incentives(package_amount),
    CONSTRAINT fk_unlock_sale_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS role_permissions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role ENUM('sub_editor', 'team_leader', 'telecaller') NOT NULL,
    module_name VARCHAR(80) NOT NULL,
    can_view TINYINT(1) NOT NULL DEFAULT 0,
    can_edit TINYINT(1) NOT NULL DEFAULT 0,
    can_publish TINYINT(1) NOT NULL DEFAULT 0,
    UNIQUE KEY uq_role_module (role, module_name)
);

INSERT INTO role_permissions (role, module_name, can_view, can_edit, can_publish)
SELECT 'sub_editor', 'editorial_queue', 1, 1, 0
WHERE NOT EXISTS (SELECT 1 FROM role_permissions WHERE role = 'sub_editor' AND module_name = 'editorial_queue');

INSERT INTO role_permissions (role, module_name, can_view, can_edit, can_publish)
SELECT 'team_leader', 'editorial_queue', 1, 1, 1
WHERE NOT EXISTS (SELECT 1 FROM role_permissions WHERE role = 'team_leader' AND module_name = 'editorial_queue');

INSERT INTO role_permissions (role, module_name, can_view, can_edit, can_publish)
SELECT 'telecaller', 'call_center', 1, 1, 0
WHERE NOT EXISTS (SELECT 1 FROM role_permissions WHERE role = 'telecaller' AND module_name = 'call_center');
