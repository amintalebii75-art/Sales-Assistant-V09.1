-- Sales Assistant V05.1 — RBAC Security Final
-- Idempotent migration for an existing V04.2.1 database.
-- IMPORTANT: take a full database backup before running this file.

SET @schema_name = DATABASE();

-- Add user security/profile columns only when missing.
SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='users' AND COLUMN_NAME='status')=0,
  "ALTER TABLE users ADD COLUMN status ENUM('active','inactive','locked') NOT NULL DEFAULT 'active' AFTER role", 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='users' AND COLUMN_NAME='team_member_id')=0,
  'ALTER TABLE users ADD COLUMN team_member_id VARCHAR(80) NULL AFTER status', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='users' AND COLUMN_NAME='last_login_at')=0,
  'ALTER TABLE users ADD COLUMN last_login_at DATETIME NULL AFTER team_member_id', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='users' AND COLUMN_NAME='failed_attempts')=0,
  'ALTER TABLE users ADD COLUMN failed_attempts INT UNSIGNED NOT NULL DEFAULT 0 AFTER last_login_at', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='users' AND COLUMN_NAME='locked_until')=0,
  'ALTER TABLE users ADD COLUMN locked_until DATETIME NULL AFTER failed_attempts', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='users' AND COLUMN_NAME='password_changed_at')=0,
  'ALTER TABLE users ADD COLUMN password_changed_at DATETIME NULL AFTER locked_until', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='users' AND COLUMN_NAME='must_change_password')=0,
  'ALTER TABLE users ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0 AFTER password_changed_at', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='users' AND COLUMN_NAME='created_by')=0,
  'ALTER TABLE users ADD COLUMN created_by INT NULL AFTER must_change_password', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='users' AND COLUMN_NAME='updated_at')=0,
  'ALTER TABLE users ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='users' AND COLUMN_NAME='rbac_review_required')=0,
  'ALTER TABLE users ADD COLUMN rbac_review_required TINYINT(1) NOT NULL DEFAULT 0 AFTER updated_at', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- Widen the legacy ENUM and keep role as VARCHAR. Role validity is enforced centrally
-- in permissions.php so adding a future role does not require a risky table rebuild.
ALTER TABLE users MODIFY role VARCHAR(40) NOT NULL DEFAULT 'manager_viewer';
UPDATE users SET role='manager' WHERE role='editor';
UPDATE users SET role='manager_viewer' WHERE role IN ('viewer','viewer_manager');
UPDATE users SET status='active' WHERE status IS NULL OR status='';
UPDATE users SET password_changed_at=COALESCE(password_changed_at, created_at, CURRENT_TIMESTAMP);
UPDATE users SET rbac_review_required=1 WHERE role IN ('marketer','center_call') AND (team_member_id IS NULL OR team_member_id='');
UPDATE users SET rbac_review_required=0 WHERE role NOT IN ('marketer','center_call') OR (team_member_id IS NOT NULL AND team_member_id<>'');

CREATE TABLE IF NOT EXISTS role_permissions (
  role VARCHAR(40) NOT NULL,
  permission_key VARCHAR(100) NOT NULL,
  allowed TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (role, permission_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS user_permission_overrides (
  user_id INT NOT NULL,
  permission_key VARCHAR(100) NOT NULL,
  allowed TINYINT(1) NOT NULL,
  updated_by INT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, permission_key),
  INDEX idx_user_permission_updated_by (updated_by),
  CONSTRAINT fk_user_permission_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS customer_access (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  customer_id VARCHAR(120) NOT NULL,
  user_id INT NOT NULL,
  access_level ENUM('view','call','edit') NOT NULL DEFAULT 'view',
  assigned_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_customer_user (customer_id, user_id),
  INDEX idx_customer_access_user (user_id),
  INDEX idx_customer_access_customer (customer_id),
  CONSTRAINT fk_customer_access_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS audit_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  action VARCHAR(100) NOT NULL,
  entity_type VARCHAR(50) NOT NULL DEFAULT 'system',
  entity_id VARCHAR(120) NULL,
  result VARCHAR(30) NOT NULL DEFAULT 'ok',
  metadata_json LONGTEXT NULL,
  ip_address VARCHAR(45) NULL,
  user_agent VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_audit_created_at (created_at),
  INDEX idx_audit_user (user_id),
  INDEX idx_audit_action (action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO role_permissions (role, permission_key, allowed) VALUES
('manager','dashboard.view_personal',1),
('manager','dashboard.view_team',1),
('manager','customers.view_own',1),
('manager','customers.view_all',1),
('manager','customers.create',1),
('manager','customers.edit_own',1),
('manager','customers.edit_all',1),
('manager','customers.delete',1),
('manager','customers.assign',1),
('manager','customers.share',1),
('manager','interactions.create',1),
('manager','interactions.edit_own',1),
('manager','followups.manage_own',1),
('manager','followups.manage_all',1),
('manager','tasks.view_own',1),
('manager','tasks.view_all',1),
('manager','tasks.create_personal',1),
('manager','tasks.assign',1),
('manager','reports.view_personal',1),
('manager','reports.view_team',1),
('manager','orders.view_own',1),
('manager','orders.view_all',1),
('manager','excel_import.use',1),
('manager','ai.use',1),
('manager','users.manage',1),
('manager','permissions.manage',1),
('manager','backups.view',1),
('manager','backups.restore',1),
('manager','settings.manage',1),
('manager','audit.view',1),
('manager','state.view_full',1),
('manager','state.save_full',1),
('marketer','dashboard.view_personal',1),
('marketer','customers.view_own',1),
('marketer','customers.create',1),
('marketer','customers.edit_own',1),
('marketer','interactions.create',1),
('marketer','interactions.edit_own',1),
('marketer','followups.manage_own',1),
('marketer','tasks.view_own',1),
('marketer','tasks.create_personal',1),
('marketer','reports.view_personal',1),
('marketer','orders.view_own',1),
('marketer','ai.use',1),
('center_call','dashboard.view_personal',1),
('center_call','customers.view_own',1),
('center_call','interactions.create',1),
('center_call','followups.manage_own',1),
('center_call','tasks.view_own',1),
('center_call','reports.view_personal',1),
('center_call','orders.view_own',1),
('manager_viewer','dashboard.view_team',1),
('manager_viewer','reports.view_team',1)
ON DUPLICATE KEY UPDATE allowed=VALUES(allowed);

-- Non-unique index supports lookup; one-active-account-per-member is also enforced by users_api.php.
SET @sql = IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='users' AND INDEX_NAME='idx_users_team_member')=0,
  'CREATE INDEX idx_users_team_member ON users(team_member_id)', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
