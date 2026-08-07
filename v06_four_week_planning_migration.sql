-- Sales Assistant V06 — Four-Week Team Planning
-- Idempotent migration for MySQL 8+ and MariaDB 10.4+
-- Run only after a complete file and database backup on Staging.

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
  INDEX idx_monthly_plans_status_month (status, month_key),
  INDEX idx_monthly_plans_created_by (created_by),
  CONSTRAINT fk_monthly_plans_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
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
  UNIQUE KEY uq_monthly_plan_week (plan_id, week_number),
  INDEX idx_monthly_plan_weeks_dates (start_date, end_date),
  CONSTRAINT fk_monthly_plan_weeks_plan FOREIGN KEY (plan_id) REFERENCES monthly_plans(id) ON DELETE RESTRICT,
  CONSTRAINT chk_monthly_plan_week_number CHECK (week_number BETWEEN 1 AND 4)
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
  INDEX idx_monthly_tasks_plan_week (plan_id, week_id),
  INDEX idx_monthly_tasks_due_status (due_date, status),
  INDEX idx_monthly_tasks_created_by (created_by),
  CONSTRAINT fk_monthly_tasks_plan FOREIGN KEY (plan_id) REFERENCES monthly_plans(id) ON DELETE RESTRICT,
  CONSTRAINT fk_monthly_tasks_week FOREIGN KEY (week_id) REFERENCES monthly_plan_weeks(id) ON DELETE RESTRICT,
  CONSTRAINT fk_monthly_tasks_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
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
  UNIQUE KEY uq_monthly_task_assignment (task_id, user_id),
  INDEX idx_monthly_assignments_user_status (user_id, status),
  INDEX idx_monthly_assignments_team_member (team_member_id),
  INDEX idx_monthly_assignments_task_status (task_id, status),
  CONSTRAINT fk_monthly_assignments_task FOREIGN KEY (task_id) REFERENCES monthly_plan_tasks(id) ON DELETE RESTRICT,
  CONSTRAINT fk_monthly_assignments_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_monthly_assignments_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT chk_monthly_assignment_progress CHECK (progress_percent BETWEEN 0 AND 100)
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


-- V06 permissions. Existing role customizations are not overwritten.
INSERT INTO role_permissions (role, permission_key, allowed) VALUES
('manager','plans.view_own',1),
('manager','plans.view_team',1),
('manager','plans.view_team_summary',1),
('manager','plans.manage',1),
('manager','plans.publish',1),
('manager','plans.assign',1),
('manager','plans.update_own',1),
('manager','plans.close',1),
('manager','plans.copy_month',1),
('marketer','plans.view_own',1),
('marketer','plans.update_own',1),
('center_call','plans.view_own',1),
('center_call','plans.update_own',1),
('manager_viewer','plans.view_team_summary',1)
ON DUPLICATE KEY UPDATE permission_key = VALUES(permission_key);
