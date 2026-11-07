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

CREATE TABLE IF NOT EXISTS office_locations (
    id TINYINT UNSIGNED PRIMARY KEY,
    address VARCHAR(255) NOT NULL,
    latitude DECIMAL(10, 7) NOT NULL,
    longitude DECIMAL(10, 7) NOT NULL,
    configured_by BIGINT UNSIGNED,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_office_location_configurer FOREIGN KEY (configured_by) REFERENCES users(id) ON DELETE SET NULL
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
    check_out_at DATETIME,
    check_out_latitude DECIMAL(10, 7),
    check_out_longitude DECIMAL(10, 7),
    check_out_distance_m DECIMAL(8, 2),
    check_out_selfie VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_attendance_user_date (user_id, attendance_date),
    CONSTRAINT fk_attendance_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS attendance_holidays (
    holiday_date DATE PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    configured_by BIGINT UNSIGNED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_attendance_holiday_configurer FOREIGN KEY (configured_by) REFERENCES users(id) ON DELETE SET NULL
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
