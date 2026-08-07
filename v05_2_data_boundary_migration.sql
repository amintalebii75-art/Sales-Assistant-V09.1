-- Sales Assistant V05.2 — idempotent RBAC data-boundary migration
-- Run on a staging MariaDB/MySQL database after a full database backup.

DELIMITER $$
DROP PROCEDURE IF EXISTS hippo_v052_migrate $$
CREATE PROCEDURE hippo_v052_migrate()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'rbac_review_required'
    ) THEN
        ALTER TABLE users
            ADD COLUMN rbac_review_required TINYINT(1) NOT NULL DEFAULT 0 AFTER updated_at;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND INDEX_NAME = 'idx_users_rbac_review'
    ) THEN
        ALTER TABLE users ADD INDEX idx_users_rbac_review (rbac_review_required, role, status);
    END IF;
END $$
CALL hippo_v052_migrate() $$
DROP PROCEDURE hippo_v052_migrate $$
DELIMITER ;

-- Preferred safe migration policy: operational accounts without a Team Member
-- are deactivated and explicitly queued for RBAC review. Existing data is retained.
UPDATE users
SET status = 'inactive',
    rbac_review_required = 1
WHERE role IN ('marketer', 'center_call')
  AND (team_member_id IS NULL OR TRIM(team_member_id) = '');

-- A non-empty but stale Team Member id is also blocked at runtime until a real manager
-- reconnects it to an id that exists in app_state.team. The migration deliberately does
-- not guess or automatically reassign ownership.
