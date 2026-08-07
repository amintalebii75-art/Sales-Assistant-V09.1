<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/permissions.php';

$results = [];
function record_result(string $id, string $title, callable $test): void {
    global $results;
    try {
        $detail = $test();
        $results[] = ['id'=>$id,'title'=>$title,'status'=>'PASS','detail'=>is_string($detail)?$detail:''];
    } catch (Throwable $e) {
        $results[] = ['id'=>$id,'title'=>$title,'status'=>'FAIL','detail'=>$e->getMessage()];
    }
}
function assert_true(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
function assert_same(mixed $expected, mixed $actual, string $message): void { if ($expected !== $actual) throw new RuntimeException($message . ' expected=' . json_encode($expected) . ' actual=' . json_encode($actual)); }
function assert_absent(array $row, array $keys, string $context): void { foreach ($keys as $key) if (array_key_exists($key,$row)) throw new RuntimeException("$context leaked $key"); }
function expect_auth_error(string $code, callable $fn): void {
    try { $fn(); } catch (HippoAuthorizationException $e) { assert_same($code,$e->errorCode,'wrong authorization error'); return; }
    throw new RuntimeException("expected $code");
}
function user_for(string $role, string $team = '', array $overrides = []): array {
    $permissions = hippo_default_permissions_for_role($role);
    foreach ($overrides as $key=>$value) $permissions[$key]=(bool)$value;
    return [
        'id'=>match($role){'manager'=>1,'marketer'=>2,'center_call'=>3,default=>4},
        'role'=>$role,'status'=>'active','team_member_id'=>$team?:null,
        'team_member_valid'=>!hippo_role_requires_team_member($role)||$team!=='',
        'rbac_review_required'=>false,'permissions'=>$permissions,
    ];
}

$team = [
    ['id'=>'m1','name'=>'بازاریاب الف','role'=>'sales'],
    ['id'=>'m2','name'=>'بازاریاب ب','role'=>'sales'],
];
$customer1 = [
    'id'=>'c1','name'=>'مشتری قابل تماس','company'=>'شرکت یک','contact'=>'آقای الف','phone'=>'09120000001','phone2'=>'0210000001',
    'city'=>'تهران','province'=>'تهران','industry'=>'بسته‌بندی','product'=>'گرانول','stage'=>'negotiation','source'=>'private-referral',
    'nextFollowUp'=>'2026-07-30','status'=>'active','assignee'=>'m2','address'=>'آدرس کامل محرمانه','privateManagerNote'=>'یادداشت مدیر',
    'paymentPreference'=>'اعتبار 90 روزه','managerScore'=>95,'competitor'=>'رقیب محرمانه','financialInternal'=>['margin'=>25],
    'technicalNeed'=>'MFI 2','estimatedVolume'=>10,'createdAt'=>'2026-01-01','updatedAt'=>'2026-07-01',
];
$customer2 = [
    'id'=>'c2','name'=>'مشتری پنهان','contact'=>'خانم ب','phone'=>'09120000002','city'=>'شیراز','stage'=>'new','source'=>'secret',
    'assignee'=>'m2','address'=>'آدرس پنهان','paymentPreference'=>'نقدی','managerScore'=>88,
];
$interaction1 = [
    'id'=>'i1','customerId'=>'c1','memberId'=>'m1','date'=>'2026-07-20T10:00:00+03:30','channel'=>'call',
    'resultIds'=>['follow_up','purchase'],'note'=>'تماس عمومی','nextFollowUp'=>'2026-07-30','duration'=>90,'status'=>'done','week'=>4,
    'volume'=>4,'price'=>100,'value'=>400,'analysis'=>['summary'=>'خلاصه فروش','managerDecisionRequired'=>true],
    'fulfillment'=>['status'=>'delivered'],'orderValue'=>999,'payment'=>['term'=>'90d'],'managerDecision'=>'approve',
];
$interactionOther = [
    'id'=>'i2','customerId'=>'c1','memberId'=>'m2','date'=>'2026-07-21T10:00:00+03:30','channel'=>'call',
    'resultIds'=>['follow_up'],'note'=>'ثبت کاربر دیگر','nextFollowUp'=>'2026-08-01','status'=>'done','week'=>4,
];
$interactionHidden = [
    'id'=>'i3','customerId'=>'c2','memberId'=>'m2','date'=>'2026-07-22T10:00:00+03:30','channel'=>'meeting',
    'resultIds'=>['purchase'],'note'=>'تعامل پنهان','fulfillment'=>['status'=>'pending'],'orderValue'=>1234,
];
$current = [
    'version'=>2,'project'=>['name'=>'فروش','activeWeek'=>4,'internalSetting'=>'keep'],
    'team'=>$team,'customers'=>[$customer1,$customer2],
    'interactions'=>[$interaction1,$interactionOther,$interactionHidden],
    'weeks'=>[['n'=>1,'tasks'=>[],'outputs'=>[['id'=>'o1','done'=>true]],'metrics'=>[['id'=>'x']]]],
    'formula'=>['cashPrice'=>123,'notes'=>'حفظ شود'],'settings'=>['crmView'=>'grid','adminOnly'=>'keep'],
    'replyLibrary'=>[['id'=>'follow_up','label'=>'پیگیری','category'=>'follow','response'=>'متن فروش'],['id'=>'purchase','label'=>'خرید','category'=>'order','response'=>'داخلی']],
];

record_result('FS-01','مدیر با View و Save کامل می‌تواند Full-State ذخیره کند',function(){
    $u=user_for('manager');assert_true(hippo_can_save_full_state($u),'full save should be allowed');return 'manager + state.view_full + state.save_full';
});
record_result('FS-02','مدیر با View و بدون Save نمی‌تواند Full-State ذخیره کند',function(){
    $u=user_for('manager','',['state.save_full'=>false]);assert_true(!hippo_can_save_full_state($u),'save without permission allowed');return 'view-only manager rejected for full save';
});
record_result('FS-03','مدیر با Save و بدون View نمی‌تواند Full-State ذخیره کند',function(){
    $u=user_for('manager','',['state.view_full'=>false]);assert_true(!hippo_can_save_full_state($u),'save without view allowed');return 'save-only manager rejected for full save';
});
record_result('FS-04','Override غیرمدیر مرز سازمانی را دور نمی‌زند',function(){
    $u=user_for('marketer','m1',['state.view_full'=>true,'state.save_full'=>true]);assert_true(!hippo_can_view_full_state($u)&&!hippo_can_save_full_state($u),'non-manager override escalated');return 'real manager role remains mandatory';
});

record_result('FS-05','مالک Interaction در Full-State فقط سمت سرور تعیین می‌شود',function()use($current){
    $u=user_for('manager','m1');$incoming=$current;
    foreach($incoming['interactions'] as &$i)if($i['id']==='i1')$i['memberId']='m2';unset($i);
    $incoming['interactions'][]=[
        'id'=>'i-new','customerId'=>'c1','memberId'=>'m2','channel'=>'call','date'=>'2026-07-25',
        'resultIds'=>['follow_up'],'note'=>'تعامل جدید مدیر','status'=>'done'
    ];
    $normalized=hippo_normalize_full_state_payload($current,$incoming,$u);
    $byId=hippo_index_by_id($normalized['interactions']);
    assert_same('m1',$byId['i1']['memberId']??null,'existing owner was changed by browser');
    assert_same('m1',$byId['i-new']['memberId']??null,'new owner was not taken from session');
    return 'existing owner preserved; new owner set from authenticated session';
});

record_result('CF-01','Field-Level مشتری در سطح Call فقط فیلدهای تماس را می‌فرستد',function()use($customer1,$team){
    $u=user_for('center_call','m1');$f=hippo_filter_customer_fields_for_level($customer1,'call',$u,hippo_team_name_map($team));
    assert_same('09120000001',$f['phone']??null,'phone missing');assert_same('negotiation',$f['stage']??null,'read-only stage missing');
    assert_absent($f,['address','privateManagerNote','paymentPreference','managerScore','competitor','financialInternal','source','technicalNeed','estimatedVolume'],'call customer');
    assert_same('بازاریاب ب',$f['assignee']??null,'assignee must be display only');return implode(', ',array_keys($f));
});
record_result('CF-02','Field-Level مشتری در سطح View از Call محدودتر است',function()use($customer1,$team){
    $u=user_for('center_call','m1');$f=hippo_filter_customer_fields_for_level($customer1,'view',$u,hippo_team_name_map($team));
    assert_absent($f,['phone','phone2','contact','address','paymentPreference','managerScore'],'view customer');return implode(', ',array_keys($f));
});
record_result('CF-03','customer_access=call سقف customers.edit_all است',function()use($customer1){
    $u=user_for('center_call','m1',['customers.view_all'=>true,'customers.edit_all'=>true]);
    assert_same('call',hippo_customer_level($u,$customer1,['c1'=>'call']),'explicit access was bypassed');return 'call ceiling preserved despite customers.edit_all';
});

record_result('IF-01','تعامل Call فاقد سفارش، Fulfillment و مالی است',function()use($interaction1,$team){
    $u=user_for('center_call','m1');$f=hippo_filter_interaction_fields_for_level($interaction1,'call',$u,hippo_team_name_map($team));
    assert_absent($f,['fulfillment','orderValue','payment','managerDecision','volume','price','value','analysis','week'],'call interaction');
    assert_true(!in_array('purchase',$f['resultIds']??[],true),'purchase result leaked to call');return implode(', ',array_keys($f));
});
record_result('IF-02','تعامل View فقط خلاصه لازم را دارد',function()use($interaction1,$team){
    $u=user_for('center_call','m1');$f=hippo_filter_interaction_fields_for_level($interaction1,'view',$u,hippo_team_name_map($team));
    assert_absent($f,['note','nextFollowUp','duration','fulfillment','orderValue','analysis'],'view interaction');return implode(', ',array_keys($f));
});
record_result('IF-03','Payload سفارش جعلی در سطح Call رد می‌شود',function(){
    $u=user_for('center_call','m1');expect_auth_error('interaction_field_rejected',fn()=>hippo_sanitize_interaction_payload([
        'id'=>'ix','customerId'=>'c1','channel'=>'call','date'=>'2026-07-25','resultIds'=>['follow_up'],'note'=>'ok',
        'fulfillment'=>['status'=>'delivered'],'orderValue'=>999999,
    ],$u,'call'));return 'fulfillment and orderValue rejected';
});
record_result('IF-04','Result خرید جعلی در سطح Call رد می‌شود',function(){
    $u=user_for('center_call','m1');expect_auth_error('interaction_type_rejected',fn()=>hippo_sanitize_interaction_payload([
        'id'=>'ix','customerId'=>'c1','channel'=>'call','date'=>'2026-07-25','resultIds'=>['purchase'],'note'=>'fake',
    ],$u,'call'));return 'purchase result rejected';
});

record_result('SM-01','Scoped Merge مشتری پنهان، Formula و Settings را حفظ می‌کند',function()use($current){
    $u=user_for('center_call','m1',['customers.view_all'=>true,'customers.edit_all'=>true,'interactions.edit_own'=>true]);
    $incoming=hippo_filter_state_with_access($current,$u,['c1'=>'call']);
    $merged=hippo_scoped_merge_with_access($current,$incoming,$u,['c1'=>'call']);
    assert_same(2,count($merged['customers']),'hidden customer deleted');assert_same('مشتری پنهان',$merged['customers'][1]['name'],'hidden customer changed');
    assert_same($current['formula'],$merged['formula'],'formula deleted');assert_same($current['settings'],$merged['settings'],'settings deleted');
    assert_same(3,count($merged['interactions']),'hidden interaction deleted');return 'omitted organization data preserved';
});
record_result('SM-02','Call fields change while Stage/Source/Assignee remain unchanged',function()use($current){
    $u=user_for('center_call','m1',['customers.view_all'=>true,'customers.edit_all'=>true,'interactions.edit_own'=>true]);
    $incoming=hippo_filter_state_with_access($current,$u,['c1'=>'call']);
    foreach($incoming['customers'] as &$c)if($c['id']==='c1'){$c['contact']='تماس جدید';$c['stage']='won';$c['source']='malicious';$c['assignee']='عضو دیگر';}unset($c);
    $merged=hippo_scoped_merge_with_access($current,$incoming,$u,['c1'=>'call']);$c=$merged['customers'][0];
    assert_same('تماس جدید',$c['contact'],'allowed call field not changed');assert_same('negotiation',$c['stage'],'stage changed');assert_same('private-referral',$c['source'],'source changed');assert_same('m2',$c['assignee'],'assignee changed');
    $events=hippo_policy_take_events();assert_true(count($events)>0,'sensitive attempt not recorded');return 'allowed patch applied; sensitive fields stripped';
});
record_result('SM-03','کاربر View نمی‌تواند Interaction ثبت کند',function()use($current){
    $u=user_for('center_call','m1');$incoming=hippo_filter_state_with_access($current,$u,['c1'=>'view']);
    $incoming['interactions'][]=['id'=>'new','customerId'=>'c1','channel'=>'call','date'=>'2026-07-25','resultIds'=>['follow_up'],'note'=>'x'];
    expect_auth_error('interaction_call_access_required',fn()=>hippo_scoped_merge_with_access($current,$incoming,$u,['c1'=>'view']));return 'view blocked';
});
record_result('SM-04','Interaction متعلق به کاربر دیگر قابل ویرایش نیست',function()use($current){
    $u=user_for('center_call','m1',['interactions.edit_own'=>true]);$incoming=hippo_filter_state_with_access($current,$u,['c1'=>'call']);
    foreach($incoming['interactions'] as &$i)if($i['id']==='i2')$i['note']='دستکاری';unset($i);
    expect_auth_error('interaction_edit_forbidden',fn()=>hippo_scoped_merge_with_access($current,$incoming,$u,['c1'=>'call']));return 'ownership enforced';
});
record_result('SM-05','حذف Interaction برای غیرمدیر با omission انجام نمی‌شود',function()use($current){
    $u=user_for('center_call','m1',['interactions.edit_own'=>true]);$incoming=hippo_filter_state_with_access($current,$u,['c1'=>'call']);$incoming['interactions']=[];
    $merged=hippo_scoped_merge_with_access($current,$incoming,$u,['c1'=>'call']);assert_same(3,count($merged['interactions']),'interaction deleted');return 'server retained all existing interactions';
});
record_result('SM-06','Revoke دسترسی بلافاصله ثبت تعامل را متوقف می‌کند',function()use($current){
    $u=user_for('center_call','m1');$incoming=['customers'=>[],'interactions'=>[['id'=>'new','customerId'=>'c1','channel'=>'call','date'=>'2026-07-25','resultIds'=>['follow_up'],'note'=>'x']]];
    expect_auth_error('interaction_customer_forbidden',fn()=>hippo_scoped_merge_with_access($current,$incoming,$u,[]));return 'no access row, no operation';
});

record_result('TM-01','حساب عملیاتی بدون Team Member مسدود است',function(){
    $u=user_for('marketer','');$u['team_member_valid']=false;assert_true(!hippo_operational_account_ready($u),'operational account allowed');expect_auth_error('operational_account_review_required',fn()=>hippo_require_operational_account($u));return 'workspace and APIs blocked';
});
record_result('TM-02','Team Member در Permission Fingerprint اثر می‌گذارد',function(){
    $a=user_for('marketer','m1');$b=user_for('marketer','m2');assert_true(hippo_permission_fingerprint($a)!==hippo_permission_fingerprint($b),'fingerprint unchanged');return 'cache/session scope changes on relink';
});
record_result('TM-03','Team Member نامعتبر حساب عملیاتی را مسدود می‌کند',function(){
    $u=user_for('center_call','stale');$u['team_member_valid']=false;assert_true(!hippo_operational_account_ready($u),'stale member accepted');return 'non-empty stale id is not enough';
});
record_result('SP-01','Permission حساس بدون نقش واقعی Manager رد می‌شود',function(){
    $u=user_for('marketer','m1',['permissions.manage'=>true,'backups.restore'=>true]);expect_auth_error('manager_role_required',fn()=>hippo_require_manager_permission($u,'permissions.manage'));return 'override cannot create organization authority';
});

$root=dirname(__DIR__);
record_result('ST-01','Backup/Restore دارای گارد Manager و Full-State است',function()use($root){
    $api=file_get_contents($root.'/api.php');
    foreach(["hippo_require_manager_permission(\$user,'backups.view')","hippo_require_manager_permission(\$user,'state.view_full')","hippo_require_manager_permission(\$user,'backups.restore')","hippo_require_manager_permission(\$user,'state.save_full')"] as $needle)assert_true(str_contains($api,$needle),'missing guard '.$needle);
    assert_true(str_contains($api,'hippo_require_csrf($body)'),'restore csrf missing');assert_true(str_contains($api,'hippo_expected_revision($body)'),'restore revision missing');return 'static endpoint guard verification';
});
record_result('ST-02','Restore response خام Full-State برنمی‌گرداند',function()use($root){
    $api=file_get_contents($root.'/api.php');$start=strpos($api,"if(\$action==='restore'");$end=strpos($api,"if(\$action==='addManagerTask'",$start);$block=substr($api,$start,$end-$start);
    $tail=substr($block,strrpos($block,'// Never return restored full state'));
    assert_true(!str_contains($tail,"'data'=>"),'restore response contains data');return 'response is metadata + revision + context token only';
});
record_result('ST-03','Team Member linking در users_api نیازمند Manager است',function()use($root){
    $api=file_get_contents($root.'/users_api.php');assert_true(str_contains($api,"ua_require_manager_sensitive(\$user,'permissions.manage')"),'manager guard missing');assert_true(str_contains($api,"team_member_link_denied"),'denied audit missing');return 'create and sensitive update guarded';
});
record_result('ST-04','State context token و Fingerprint در Save بررسی می‌شوند',function()use($root){
    $api=file_get_contents($root.'/api.php');foreach(['state_context_token','permission_fingerprint','full_state_scope_mismatch','state_context_stale'] as $x)assert_true(str_contains($api,$x),'missing '.$x);return 'load/save invariant present';
});
record_result('ST-05','No-op Save مسیر افزایش Revision ندارد',function()use($root){
    $api=file_get_contents($root.'/api.php');$noop=strpos($api,'if($merged==$current)');$increment=strpos($api,'$newRevision=$currentRevision+1');assert_true($noop!==false&&$increment!==false&&$noop<$increment,'revision increment precedes no-op exit');return 'no-op exits before revision increment and backup';
});

$pass=count(array_filter($results,fn($r)=>$r['status']==='PASS'));
$fail=count($results)-$pass;
$output=['suite'=>'V05.2 RBAC Data Boundary','generated_at'=>date(DATE_ATOM),'environment'=>['php'=>PHP_VERSION,'database'=>'not used; pure policy/static tests'],'pass'=>$pass,'fail'=>$fail,'results'=>$results];
file_put_contents(dirname(__DIR__).'/TEST-EVIDENCE/policy-test-results.json',json_encode($output,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
$md="# V05.2 Policy Test Results\n\n- PASS: $pass\n- FAIL: $fail\n- PHP: ".PHP_VERSION."\n- Database: not used in this suite\n\n| ID | Result | Test | Detail |\n|---|---|---|---|---|\n";
foreach($results as $r)$md.='| '.$r['id'].' | '.$r['status'].' | '.str_replace('|','/',$r['title']).' | '.str_replace(["|","\n"],['/',' '],$r['detail'])." |\n";
file_put_contents(dirname(__DIR__).'/TEST-EVIDENCE/policy-test-results.md',$md);
echo json_encode($output,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),"\n";
exit($fail?1:0);
