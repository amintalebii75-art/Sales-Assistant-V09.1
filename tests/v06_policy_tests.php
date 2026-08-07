<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/permissions.php';
require_once dirname(__DIR__) . '/planning_policy.php';

$root = dirname(__DIR__);
$results = [];
$failed = 0;
function v06_test(string $name, callable $fn): void {
    global $results, $failed;
    try {
        $detail = $fn();
        $results[] = ['name'=>$name,'status'=>'PASS','detail'=>(string)$detail];
    } catch (Throwable $e) {
        $failed++;
        $results[] = ['name'=>$name,'status'=>'FAIL','detail'=>$e->getMessage()];
    }
}
function v06_assert(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
function v06_user(string $role, array $overrides=[]): array {
    $p = hippo_default_permissions_for_role($role);
    foreach ($overrides as $k=>$v) $p[$k]=$v;
    return ['id'=>10,'role'=>$role,'status'=>'active','team_member_id'=>in_array($role,['marketer','center_call'],true)?'m1':null,'permissions'=>$p,'rbac_review_required'=>false,'team_member_valid'=>true];
}

v06_test('permission keys registered', function(){
    foreach (['plans.view_own','plans.view_team','plans.view_team_summary','plans.manage','plans.publish','plans.assign','plans.update_own','plans.close','plans.copy_month'] as $k) v06_assert(in_array($k,hippo_permission_keys(),true),'missing '.$k);
    return '9 planning permissions';
});
v06_test('manager planning defaults', function(){ $u=v06_user('manager'); foreach(['plans.view_team','plans.manage','plans.publish','plans.assign','plans.close','plans.copy_month'] as $k)v06_assert(hippo_can($u,$k),'manager missing '.$k); return 'manager defaults complete';});
v06_test('marketer own-only defaults', function(){ $u=v06_user('marketer');v06_assert(hippo_can($u,'plans.view_own')&&hippo_can($u,'plans.update_own'),'own permissions missing');v06_assert(!hippo_can($u,'plans.view_team')&&!hippo_can($u,'plans.manage')&&!hippo_can($u,'plans.assign'),'manager permission leaked');return 'own only';});
v06_test('center-call own-only defaults', function(){ $u=v06_user('center_call');v06_assert(hippo_can($u,'plans.view_own')&&hippo_can($u,'plans.update_own'),'own permissions missing');v06_assert(!hippo_can($u,'plans.view_team_summary')&&!hippo_can($u,'plans.manage'),'broad permission leaked');return 'own only';});
v06_test('manager-viewer summary-only defaults', function(){ $u=v06_user('manager_viewer');v06_assert(hippo_can($u,'plans.view_team_summary'),'summary missing');foreach(['plans.view_team','plans.view_own','plans.manage','plans.update_own','plans.assign'] as $k)v06_assert(!hippo_can($u,$k),'raw/write permission leaked '.$k);return 'summary only';});
v06_test('override false revokes immediately', function(){ $u=v06_user('marketer',['plans.view_own'=>false]);v06_assert(!hippo_can($u,'plans.view_own'),'override false ignored');return 'revoked';});
v06_test('inactive account denied', function(){ $u=v06_user('manager');$u['status']='inactive';v06_assert(!hippo_can($u,'plans.manage'),'inactive account allowed');return 'inactive blocked';});
v06_test('operational account requires team member', function(){ $u=v06_user('marketer');$u['team_member_id']='';v06_assert(!hippo_operational_account_ready($u),'missing team member accepted');return 'team member required';});
v06_test('fingerprint changes with planning override', function(){ $a=v06_user('marketer');$b=$a;$b['permissions']['plans.update_own']=false;v06_assert(hippo_permission_fingerprint($a)!==hippo_permission_fingerprint($b),'fingerprint unchanged');return 'cache invalidation covered';});
v06_test('fingerprint changes with team member', function(){ $a=v06_user('marketer');$b=$a;$b['team_member_id']='m2';v06_assert(hippo_permission_fingerprint($a)!==hippo_permission_fingerprint($b),'team member missing from fingerprint');return 'team scope covered';});

$migration = file_get_contents($root.'/v06_four_week_planning_migration.sql');
$rollback = file_get_contents($root.'/ROLLBACK-V06.sql');
$api = file_get_contents($root.'/planning_api.php');
$page = file_get_contents($root.'/planning.php');
$js = file_get_contents($root.'/assets/js/planning.js');
$integration = file_get_contents($root.'/assets/js/planning-tasks-integration.js');
$planningPolicy = file_get_contents($root.'/planning_policy.php');

foreach (['monthly_plans','monthly_plan_weeks','monthly_plan_tasks','monthly_task_assignments'] as $table) {
    v06_test('migration table '.$table, fn()=>str_contains($migration,'CREATE TABLE IF NOT EXISTS '.$table) ? 'present' : throw new RuntimeException('missing table'));
}
v06_test('migration idempotent permissions', function()use($migration){v06_assert(str_contains($migration,'ON DUPLICATE KEY UPDATE'),'permission insert not idempotent');return 'idempotent insert';});
v06_test('migration no sample data', function()use($migration){foreach(['manager_test','marketer_a_test','INSERT INTO monthly_plans (','INSERT INTO monthly_plan_tasks ('] as $x)v06_assert(!str_contains($migration,$x),'sample data found '.$x);return 'no sample records';});
v06_test('assignment uniqueness', function()use($migration){v06_assert(str_contains($migration,'UNIQUE KEY uq_monthly_task_assignment (task_id, user_id)'),'unique assignment missing');return 'one assignment per task/user';});
v06_test('week uniqueness and range', function()use($migration){v06_assert(str_contains($migration,'uq_monthly_plan_week')&&str_contains($migration,'week_number BETWEEN 1 AND 4'),'week constraint missing');return 'week constrained';});
v06_test('optimistic locking columns', function()use($migration){v06_assert(substr_count($migration,'revision BIGINT UNSIGNED NOT NULL DEFAULT 1')>=4,'revision columns missing');return '4 revisions';});
v06_test('guarded rollback', function()use($rollback){v06_assert(str_contains($rollback,'SIGNAL SQLSTATE')&&str_contains($rollback,'v06_data_count > 0'),'rollback guard missing');return 'data-aware rollback';});

v06_test('API session guard', function()use($api){v06_assert(str_contains($api,'hippo_require_login_api()'),'session guard missing');return 'session required';});
v06_test('API CSRF guard', function()use($api){v06_assert(str_contains($api,'hippo_require_csrf($body)'),'CSRF guard missing');return 'CSRF required';});
v06_test('API prepared statements', function()use($api){v06_assert(substr_count($api,'->prepare(')>=20,'prepared statement coverage too low');return 'prepared statements used';});
v06_test('API transaction coverage', function()use($api){v06_assert(substr_count($api,'beginTransaction()')>=12,'transaction coverage too low');return 'transactions used';});
v06_test('API conflict response', function()use($api){v06_assert(str_contains($api,"HippoAuthorizationException('conflict', 409")||str_contains($api,"HippoAuthorizationException('conflict',409"),'409 conflict missing');v06_assert(str_contains($api,'expected_revision'),'expected revision missing');return 'optimistic locking';});
v06_test('personal assignment bound to session user', function()use($api){v06_assert(str_contains($api,'WHERE a.id=? AND a.user_id=?')&&str_contains($api,"(int)\$user['id']"),'assignment ownership check missing');return 'server identity enforced';});
v06_test('manager assignment management is role-gated and revisioned', function()use($api){v06_assert(str_contains($api,"case 'update_assignment':")&&str_contains($api,"planning_require_manager(\$user,'plans.assign')")&&str_contains($api,"'manager_update'=>true")&&str_contains($api,'WHERE id=? AND revision=?'),'manager assignment guard missing');return 'manager endpoint protected';});
v06_test('manager assignment update preserves user note', function()use($api){$start=strpos($api,"case 'update_assignment':");$end=strpos($api,"case 'revoke_assignment':",$start);$chunk=substr($api,$start,$end-$start);v06_assert(!str_contains($chunk,'user_note'),'manager update overwrites user note');return 'user note preserved';});
v06_test('viewer summary excludes private notes', function()use($api){$start=strpos($api,'function planning_team_summary');$end=strpos($api,'function planning_personal_plan_list',$start);$chunk=substr($api,$start,$end-$start);v06_assert(!str_contains($chunk,'user_note')&&!str_contains($chunk,'blocked_reason'),'private fields in summary');return 'summary redacted';});
v06_test('eligible users exclude unsafe accounts', function()use($api){foreach(["status='active'","role IN ('marketer','center_call')",'rbac_review_required=0','locked_until IS NULL'] as $x)v06_assert(str_contains($api,$x),'eligibility guard missing '.$x);return 'active operational users only';});
v06_test('everyone assignment preview exists', function()use($api,$js){v06_assert(str_contains($api,"case 'assign_task_everyone'")&&str_contains($api,"!empty(\$body['preview'])"),'preview endpoint missing');v06_assert(str_contains($js,'previewEveryone'),'preview UI missing');return 'recipient preview';});
v06_test('task integration has monthly source', function()use($integration){v06_assert(str_contains($integration,'برنامه ماهانه')&&str_contains($integration,'update_my_assignment'),'integration incomplete');return 'single source assignment update';});
v06_test('planning page permission gate', function()use($page){v06_assert(str_contains($page,"plans.view_own")&&str_contains($page,"plans.view_team_summary"),'page gate missing');return 'permission gated page';});
v06_test('UI output escaping', function()use($js){v06_assert(str_contains($js,'function')&&str_contains($js,'const $=')&&substr_count($js,'esc(')>30,'escaping coverage insufficient');return 'escaped rendering';});
v06_test('no baseline AI changes in V06 files', function()use($api,$js){foreach(['ai_provider','arvancloud'] as $x)v06_assert(!str_contains(strtolower($api.$js),$x),'unrelated AI change '.$x);return 'scope preserved';});



// V06.0.1 security regression tests.
v06_test('operational user cannot reactivate cancelled assignment', function(){
    v06_assert(!planning_operational_assignment_transition_allowed('cancelled','pending'),'cancelled -> pending allowed');
    v06_assert(!planning_operational_assignment_transition_allowed('cancelled','completed'),'cancelled -> completed allowed');
    return 'cancelled is terminal for operational users';
});
v06_test('direct API cancelled assignment is rejected', function()use($api){
    $start=strpos($api,"case 'update_my_assignment':");$end=strpos($api,"case 'team_summary':",$start);$chunk=substr($api,$start,$end-$start);
    v06_assert(str_contains($chunk,"assignment_cancelled_read_only")&&str_contains($chunk,"WHERE a.id=? AND a.user_id=?"),'cancelled/session ownership guard missing');
    return 'backend rejects direct update';
});
v06_test('reactivate requires real manager and plans.assign', function()use($api){
    $start=strpos($api,"case 'reactivate_assignment':");$end=strpos($api,"case 'update_my_assignment':",$start);$chunk=substr($api,$start,$end-$start);
    v06_assert(str_contains($chunk,'planning_require_manager($user,\'plans.assign\')'),'manager permission guard missing');
    return 'manager plus plans.assign required';
});
v06_test('reactivate requires current revision', function()use($api){
    $start=strpos($api,"case 'reactivate_assignment':");$end=strpos($api,"case 'update_my_assignment':",$start);$chunk=substr($api,$start,$end-$start);
    v06_assert(str_contains($chunk,'planning_assert_revision($a,planning_expected_revision($body))')&&str_contains($chunk,"WHERE id=? AND revision=? AND status='cancelled'"),'revision lock missing');
    return 'expected revision enforced';
});
v06_test('reactivate stale revision maps to 409', function()use($api){
    v06_assert(str_contains($api,"HippoAuthorizationException('conflict', 409")||str_contains($api,"HippoAuthorizationException('conflict',409"),'409 conflict path missing');
    return 'stale revision conflict available';
});
v06_test('my tasks cancelled assignment has no update button', function()use($integration){
    v06_assert(str_contains($integration,"const cancelled=t.assignment.status==='cancelled'")&&str_contains($integration,'لغوشده · فقط خواندنی')&&str_contains($integration,"if(t.assignment.status==='cancelled')"),'cancelled UI guard missing');
    return 'read-only UI and preflight guard';
});
v06_test('history snapshot is preserved before reactivate', function()use($migration,$api){
    foreach(['monthly_assignment_history','old_status','old_progress_percent','old_user_note','old_blocked_reason','old_started_at','old_completed_at','changed_by','changed_at','change_reason'] as $x)v06_assert(str_contains($migration,$x),'history field missing '.$x);
    $insertPos=strpos($api,'planning_record_assignment_history($pdo,$a');$updatePos=strpos($api,"SET team_member_id=?,status='pending'",$insertPos);
    v06_assert($insertPos!==false&&$updatePos!==false&&$insertPos<$updatePos,'history snapshot not recorded before reset');
    return 'snapshot table and ordering present';
});
v06_test('team progress is assignment weighted', function(){
    $rows=[['status'=>'completed','progress_percent'=>100]];
    for($i=0;$i<9;$i++)$rows[]=['status'=>'pending','progress_percent'=>0];
    v06_assert(planning_weighted_progress($rows)===10,'expected 10 percent');
    return '1x100 plus 9x0 = 10';
});
v06_test('plan copy only copies active tasks', function()use($api){
    $start=strpos($api,"case 'copy_plan':");$end=strpos($api,"case 'create_week':",$start);$chunk=substr($api,$start,$end-$start);
    v06_assert(str_contains($chunk,'if((string)$task[\'status\']!==\'active\')continue;')&&str_contains($chunk,"assignments_copied'=>false"),'active-only copy policy missing');
    return 'active tasks only; assignments excluded';
});
v06_test('cancelled task is not copied to new month', function()use($api){
    $start=strpos($api,"case 'copy_plan':");$end=strpos($api,"case 'create_week':",$start);$chunk=substr($api,$start,$end-$start);
    v06_assert(!str_contains($chunk,"status']==='archived')continue"),'cancelled tasks still pass copy filter');
    v06_assert(str_contains($chunk,"due_date,created_by,status) VALUES(?,?,?,?,?,NULL,?,?)"),'new due date not reset');
    return 'cancelled/archived excluded and dates reset';
});
v06_test('implicit reactivation removed from assignment function', function()use($api){
    $start=strpos($api,'function planning_assign_users');$end=strpos($api,'try {',$start);$chunk=substr($api,$start,$end-$start);
    v06_assert(!str_contains($chunk,"user_note=NULL")&&str_contains($chunk,'assignment_cancelled_requires_reactivation'),'implicit destructive reactivation remains');
    return 'explicit endpoint only';
});
v06_test('reactivate audit event registered', function()use($api){
    v06_assert(str_contains($api,"'plan.assignment_reactivate'"),'reactivate audit missing');
    return 'audit event present';
});

$pass = count($results)-$failed;
echo json_encode(['pass'=>$pass,'fail'=>$failed,'tests'=>$results],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),"\n";
exit($failed?1:0);
