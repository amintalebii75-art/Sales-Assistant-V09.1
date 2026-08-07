-- Sales Assistant V06 — schema for a NEW installation.
-- Existing installations must run V05 migrations first, then v06_four_week_planning_migration.sql.

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(60) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  display_name VARCHAR(120) NOT NULL,
  role VARCHAR(40) NOT NULL DEFAULT 'manager_viewer',
  status ENUM('active','inactive','locked') NOT NULL DEFAULT 'active',
  team_member_id VARCHAR(80) NULL,
  last_login_at DATETIME NULL,
  failed_attempts INT UNSIGNED NOT NULL DEFAULT 0,
  locked_until DATETIME NULL,
  password_changed_at DATETIME NULL,
  must_change_password TINYINT(1) NOT NULL DEFAULT 0,
  created_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  rbac_review_required TINYINT(1) NOT NULL DEFAULT 0,
  INDEX idx_users_team_member (team_member_id),
  INDEX idx_users_role_status (role,status),
  INDEX idx_users_rbac_review (rbac_review_required,role,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS role_permissions (
  role VARCHAR(40) NOT NULL,
  permission_key VARCHAR(100) NOT NULL,
  allowed TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY(role,permission_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS user_permission_overrides (
  user_id INT NOT NULL,
  permission_key VARCHAR(100) NOT NULL,
  allowed TINYINT(1) NOT NULL,
  updated_by INT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY(user_id,permission_key),
  CONSTRAINT fk_user_permission_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS customer_access (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  customer_id VARCHAR(120) NOT NULL,
  user_id INT NOT NULL,
  access_level ENUM('view','call','edit') NOT NULL DEFAULT 'view',
  assigned_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_customer_user(customer_id,user_id),
  INDEX idx_customer_access_user(user_id),
  INDEX idx_customer_access_customer(customer_id),
  CONSTRAINT fk_customer_access_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
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
  INDEX idx_audit_created_at(created_at),
  INDEX idx_audit_user(user_id),
  INDEX idx_audit_action(action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS app_state (
  id INT NOT NULL PRIMARY KEY,
  data LONGTEXT NOT NULL,
  revision BIGINT UNSIGNED NOT NULL DEFAULT 0,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_by VARCHAR(120)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS app_state_backups (
  id INT AUTO_INCREMENT PRIMARY KEY,
  data LONGTEXT NOT NULL,
  revision BIGINT UNSIGNED NOT NULL DEFAULT 0,
  operation VARCHAR(30) NOT NULL DEFAULT 'save',
  source_backup_id INT NULL,
  saved_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  saved_by VARCHAR(120),
  INDEX idx_backups_saved_at(saved_at),
  INDEX idx_backups_revision(revision)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ai_requests (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  action_name VARCHAR(50) NOT NULL,
  status_name VARCHAR(20) NOT NULL,
  error_message VARCHAR(500) NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ai_user_date(user_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO app_state(id,data,updated_by,revision) VALUES(1,'{}','setup',0)
ON DUPLICATE KEY UPDATE id=id;

-- Default V05 role permissions. User-specific overrides are stored separately.
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

-- V06 Four-Week Team Planning tables for a new installation.
CREATE TABLE IF NOT EXISTS monthly_plans (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  month_key CHAR(7) NOT NULL,
  title VARCHAR(180) NOT NULL,
  description TEXT NULL,
  status ENUM('draft','published','closed','archived') NOT NULL DEFAULT 'draft',
  created_by INT NOT NULL,
  published_at DATETIME NULL,
  closed_at DATETIME NULL,
  archived_at DATETIME NULL,
  revision BIGINT UNSIGNED NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_monthly_plans_month_key (month_key),
  INDEX idx_monthly_plans_status_month (status,month_key),
  CONSTRAINT fk_monthly_plans_created_by FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS monthly_plan_weeks (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  plan_id BIGINT UNSIGNED NOT NULL,
  week_number TINYINT UNSIGNED NOT NULL,
  title VARCHAR(180) NOT NULL,
  goal_text TEXT NULL,
  start_date DATE NULL,
  end_date DATE NULL,
  revision BIGINT UNSIGNED NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_monthly_plan_week(plan_id,week_number),
  CONSTRAINT fk_monthly_plan_weeks_plan FOREIGN KEY(plan_id) REFERENCES monthly_plans(id) ON DELETE RESTRICT,
  CONSTRAINT chk_monthly_plan_week_number CHECK(week_number BETWEEN 1 AND 4)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS monthly_plan_tasks (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  plan_id BIGINT UNSIGNED NOT NULL,
  week_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(240) NOT NULL,
  description TEXT NULL,
  priority ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
  due_date DATE NULL,
  created_by INT NOT NULL,
  status ENUM('active','cancelled','archived') NOT NULL DEFAULT 'active',
  revision BIGINT UNSIGNED NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_monthly_tasks_plan_week(plan_id,week_id),
  INDEX idx_monthly_tasks_due_status(due_date,status),
  CONSTRAINT fk_monthly_tasks_plan FOREIGN KEY(plan_id) REFERENCES monthly_plans(id) ON DELETE RESTRICT,
  CONSTRAINT fk_monthly_tasks_week FOREIGN KEY(week_id) REFERENCES monthly_plan_weeks(id) ON DELETE RESTRICT,
  CONSTRAINT fk_monthly_tasks_created_by FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS monthly_task_assignments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  task_id BIGINT UNSIGNED NOT NULL,
  user_id INT NOT NULL,
  team_member_id VARCHAR(80) NOT NULL,
  status ENUM('pending','in_progress','blocked','needs_decision','completed','cancelled') NOT NULL DEFAULT 'pending',
  progress_percent TINYINT UNSIGNED NOT NULL DEFAULT 0,
  user_note TEXT NULL,
  blocked_reason VARCHAR(1000) NULL,
  started_at DATETIME NULL,
  completed_at DATETIME NULL,
  updated_by INT NULL,
  revision BIGINT UNSIGNED NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_monthly_task_assignment(task_id,user_id),
  INDEX idx_monthly_assignments_user_status(user_id,status),
  INDEX idx_monthly_assignments_team_member(team_member_id),
  CONSTRAINT fk_monthly_assignments_task FOREIGN KEY(task_id) REFERENCES monthly_plan_tasks(id) ON DELETE RESTRICT,
  CONSTRAINT fk_monthly_assignments_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_monthly_assignments_updated_by FOREIGN KEY(updated_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT chk_monthly_assignment_progress CHECK(progress_percent BETWEEN 0 AND 100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS monthly_assignment_history (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  assignment_id BIGINT UNSIGNED NOT NULL,
  old_status ENUM('pending','in_progress','blocked','needs_decision','completed','cancelled') NOT NULL,
  old_progress_percent TINYINT UNSIGNED NOT NULL,
  old_user_note TEXT NULL,
  old_blocked_reason VARCHAR(1000) NULL,
  old_started_at DATETIME NULL,
  old_completed_at DATETIME NULL,
  changed_by INT NULL,
  changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  change_reason VARCHAR(500) NOT NULL,
  INDEX idx_monthly_assignment_history_assignment (assignment_id, changed_at),
  INDEX idx_monthly_assignment_history_changed_by (changed_by),
  CONSTRAINT fk_monthly_assignment_history_assignment FOREIGN KEY (assignment_id) REFERENCES monthly_task_assignments(id) ON DELETE RESTRICT,
  CONSTRAINT fk_monthly_assignment_history_changed_by FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT chk_monthly_assignment_history_progress CHECK (old_progress_percent BETWEEN 0 AND 100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


INSERT INTO role_permissions(role,permission_key,allowed) VALUES
('manager','plans.view_own',1),('manager','plans.view_team',1),('manager','plans.view_team_summary',1),
('manager','plans.manage',1),('manager','plans.publish',1),('manager','plans.assign',1),
('manager','plans.update_own',1),('manager','plans.close',1),('manager','plans.copy_month',1),
('marketer','plans.view_own',1),('marketer','plans.update_own',1),
('center_call','plans.view_own',1),('center_call','plans.update_own',1),
('manager_viewer','plans.view_team_summary',1)
ON DUPLICATE KEY UPDATE permission_key=VALUES(permission_key);
