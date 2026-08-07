-- Sales Assistant V01 — Migration for an existing database
-- یک‌بار در phpMyAdmin و روی همان دیتابیس فعلی اجرا شود.
-- این Migration داده‌های موجود را حذف یا بازنویسی نمی‌کند.

SET @db_name = DATABASE();

SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='app_state' AND COLUMN_NAME='revision') = 0,
  'ALTER TABLE app_state ADD COLUMN revision BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER data',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='app_state_backups' AND COLUMN_NAME='revision') = 0,
  'ALTER TABLE app_state_backups ADD COLUMN revision BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER data',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='app_state_backups' AND COLUMN_NAME='operation') = 0,
  'ALTER TABLE app_state_backups ADD COLUMN operation VARCHAR(30) NOT NULL DEFAULT ''save'' AFTER revision',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='app_state_backups' AND COLUMN_NAME='source_backup_id') = 0,
  'ALTER TABLE app_state_backups ADD COLUMN source_backup_id INT NULL AFTER operation',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

UPDATE app_state SET revision = 0 WHERE revision IS NULL;
UPDATE app_state_backups SET revision = 0 WHERE revision IS NULL;
