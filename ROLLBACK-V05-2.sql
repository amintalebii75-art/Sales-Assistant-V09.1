-- Sales Assistant V05.2 rollback helper
-- Restore application files and database backup first. This script only removes the
-- V05.2-only supporting index; it intentionally does not reactivate reviewed accounts.

DELIMITER $$
DROP PROCEDURE IF EXISTS hippo_v052_rollback $$
CREATE PROCEDURE hippo_v052_rollback()
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND INDEX_NAME = 'idx_users_rbac_review'
    ) THEN
        ALTER TABLE users DROP INDEX idx_users_rbac_review;
    END IF;
END $$
CALL hippo_v052_rollback() $$
DROP PROCEDURE hippo_v052_rollback $$
DELIMITER ;

-- Do not automatically set inactive users back to active. Review each account and its
-- Team Member ownership before manual reactivation.
