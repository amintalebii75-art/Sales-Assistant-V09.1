-- Sales Assistant V06 guarded rollback
-- Preferred rollback: restore the complete database backup taken immediately before V06.
-- This procedure never silently deletes a plan, task or assignment.

DROP PROCEDURE IF EXISTS hippo_v06_guarded_rollback;
DELIMITER //
CREATE PROCEDURE hippo_v06_guarded_rollback()
BEGIN
  DECLARE v06_data_count BIGINT DEFAULT 0;

  SELECT
    (SELECT COUNT(*) FROM monthly_plans) +
    (SELECT COUNT(*) FROM monthly_plan_weeks) +
    (SELECT COUNT(*) FROM monthly_plan_tasks) +
    (SELECT COUNT(*) FROM monthly_task_assignments) +
    (SELECT COUNT(*) FROM monthly_assignment_history)
  INTO v06_data_count;

  IF v06_data_count > 0 THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'V06 rollback stopped: planning data exists. Restore the pre-V06 backup or remove data only after explicit approval.';
  ELSE
    DELETE FROM user_permission_overrides
    WHERE permission_key IN (
      'plans.view_own','plans.view_team','plans.view_team_summary','plans.manage','plans.publish',
      'plans.assign','plans.update_own','plans.close','plans.copy_month'
    );

    DELETE FROM role_permissions
    WHERE permission_key IN (
      'plans.view_own','plans.view_team','plans.view_team_summary','plans.manage','plans.publish',
      'plans.assign','plans.update_own','plans.close','plans.copy_month'
    );

    DROP TABLE IF EXISTS monthly_assignment_history;
    DROP TABLE IF EXISTS monthly_task_assignments;
    DROP TABLE IF EXISTS monthly_plan_tasks;
    DROP TABLE IF EXISTS monthly_plan_weeks;
    DROP TABLE IF EXISTS monthly_plans;
  END IF;
END//
DELIMITER ;

CALL hippo_v06_guarded_rollback();
DROP PROCEDURE IF EXISTS hippo_v06_guarded_rollback;
