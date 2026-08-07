<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/permissions.php';

$results=[];
function t(string $name, callable $fn): void { global $results; try{$detail=$fn();$results[]=['name'=>$name,'status'=>'PASS','detail'=>(string)$detail];}catch(Throwable $e){$results[]=['name'=>$name,'status'=>'FAIL','detail'=>$e->getMessage()];}}
function ok(bool $v,string $m='assertion failed'): void { if(!$v) throw new RuntimeException($m); }

$root=dirname(__DIR__);
$custom=['id'=>'team_abc','label'=>'درخواست تماس کارشناس فروش','category'=>'سفارشی','active'=>true,'teamVisible'=>true,'callCenterAllowed'=>true,'handoffToAssignee'=>true];
$callUser=['id'=>9,'role'=>'center_call','status'=>'active','team_member_id'=>'cc1','team_member_valid'=>true,'permissions'=>hippo_default_permissions_for_role('center_call')];

 t('manager/sales-manager add button exists',function()use($root){$s=file_get_contents($root.'/index.php');ok(str_contains($s,'canManageReplyOptions'));ok(str_contains($s,'reply-option-add'));ok(str_contains($s,"currentMember()?.access||'')==='sales_manager'"));return 'role-aware plus button';});
 t('reply option API is CSRF and revision protected',function()use($root){$s=file_get_contents($root.'/api.php');$start=strpos($s,"reply_option_create");$block=substr($s,$start,7000);foreach(['hippo_require_csrf','hippo_expected_revision','FOR UPDATE','reply_option_manage_forbidden','hippo_insert_backup','hippo_audit'] as $x)ok(str_contains($block,$x),'missing '.$x);return 'CSRF + revision + backup + audit';});
 t('center call receives manager-created team option',function()use($custom,$callUser){$lib=hippo_filter_reply_library([$custom],$callUser);ok(count($lib)===1);ok(($lib[0]['id']??'')==='team_abc');return 'teamVisible option included';});
 t('center call can submit manager-created team option',function()use($custom,$callUser){$safe=hippo_sanitize_interaction_payload(['id'=>'i1','customerId'=>'c1','channel'=>'call','date'=>date(DATE_ATOM),'resultIds'=>['team_abc'],'note'=>'','nextFollowUp'=>'2026-08-20','status'=>'completed'],$callUser,'call',[$custom]);ok(($safe['resultIds'][0]??'')==='team_abc');return 'dynamic option accepted by backend';});
 t('center call still cannot submit purchase outcome',function()use($custom,$callUser){try{hippo_sanitize_interaction_payload(['id'=>'i2','customerId'=>'c1','channel'=>'call','date'=>date(DATE_ATOM),'resultIds'=>['purchase'],'note'=>'','nextFollowUp'=>'','status'=>'completed'],$callUser,'call',[$custom]);}catch(HippoAuthorizationException $e){ok($e->errorCode==='interaction_type_rejected');return 'purchase remains blocked';}throw new RuntimeException('purchase was accepted');});
 t('call-center interaction creates marketer handoff task',function()use($custom,$callUser){$state=['project'=>['activeWeek'=>1],'team'=>[['id'=>'cc1','name'=>'مرکز تماس','access'=>'marketer'],['id'=>'mkt1','name'=>'بازاریاب','access'=>'marketer']],'customers'=>[['id'=>'c1','name'=>'مشتری تست','assignee'=>'mkt1','stage'=>'qualified','nextFollowUp'=>'','status'=>'active']],'interactions'=>[],'replyLibrary'=>[$custom],'weeks'=>[['n'=>1,'tasks'=>[]]],'settings'=>[]];$incoming=hippo_filter_state_with_access($state,$callUser,['c1'=>'call']);$incoming['interactions'][]=['id'=>'i3','customerId'=>'c1','channel'=>'call','date'=>date(DATE_ATOM),'resultIds'=>['team_abc'],'note'=>'درخواست تماس','nextFollowUp'=>'2026-08-20','status'=>'completed'];$merged=hippo_scoped_merge_with_access($state,$incoming,$callUser,['c1'=>'call']);$tasks=$merged['weeks'][0]['tasks']??[];ok(count($tasks)===1,'handoff task missing');ok(($tasks[0]['assignee']??'')==='mkt1','wrong assignee');ok(!empty($tasks[0]['fromCallCenter']),'missing source flag');ok(($tasks[0]['sourceInteractionId']??'')==='i3','missing interaction link');return 'task assigned to customer marketer';});
 t('font stylesheet targets IRANSansXFaNum without bundled binaries',function()use($root){$s=file_get_contents($root.'/assets/css/tokens.css');ok(str_contains($s,'IRANSansXFaNum-Regular.ttf'));ok(str_contains($s,'--font-family:"IRANSansXFaNum"'));$fonts=glob($root.'/assets/fonts/*.ttf')?:[];ok(count($fonts)===0,'font binaries must not be packaged');return 'font references and copy instructions only';});

$pass=count(array_filter($results,fn($r)=>$r['status']==='PASS'));$fail=count($results)-$pass;
$out=['suite'=>'V06.0.5 Team Results, Handoff and Font','pass'=>$pass,'fail'=>$fail,'results'=>$results];
echo json_encode($out,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),"\n";
exit($fail?1:0);
