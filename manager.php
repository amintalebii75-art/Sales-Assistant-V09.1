<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/permissions.php';
$user=hippo_require_login_page();
if(hippo_role_alias((string)$user['role'])!=='manager_viewer'){header('Location: index.php');exit;}
hippo_require_permission($user,'dashboard.view_team');
$pdo=hippo_db();
$row=$pdo->query('SELECT data,revision,updated_at,updated_by FROM app_state WHERE id=1')->fetch();
$state=$row?json_decode((string)$row['data'],true):[];if(!is_array($state))$state=[];
$customers=array_values(array_filter((array)($state['customers']??[]),'is_array'));
$interactions=array_values(array_filter((array)($state['interactions']??[]),'is_array'));
$team=array_values(array_filter((array)($state['team']??[]),'is_array'));
$weeks=array_values(array_filter((array)($state['weeks']??[]),'is_array'));
$today=date('Y-m-d');
$stageLabels=['new'=>'سرنخ جدید','contacted'=>'ارتباط اولیه','qualified'=>'نیاز تأییدشده','sample'=>'نمونه/تست','negotiation'=>'مذاکره','trial'=>'سفارش آزمایشی','won'=>'خرید/تکرار','paused'=>'متوقف'];
$stageCounts=array_fill_keys(array_keys($stageLabels),0);$overdue=0;$noFollowup=0;$unassigned=0;
foreach($customers as $c){$st=(string)($c['stage']??'new');if(isset($stageCounts[$st]))$stageCounts[$st]++;$nf=(string)($c['nextFollowUp']??'');if($nf!==''&&$nf<$today&&!in_array($st,['won','paused'],true))$overdue++;if($nf===''&&!in_array($st,['won','paused'],true))$noFollowup++;if(empty($c['assignee']))$unassigned++;}
$memberStats=[];foreach($team as $m){$id=(string)($m['id']??'');if($id==='')continue;$memberStats[$id]=['name'=>(string)($m['name']??'عضو تیم'),'customers'=>0,'interactions'=>0,'open_tasks'=>0];}
foreach($customers as $c){$id=(string)($c['assignee']??'');if(isset($memberStats[$id]))$memberStats[$id]['customers']++;}
foreach($interactions as $i){$id=(string)($i['memberId']??'');if(isset($memberStats[$id]))$memberStats[$id]['interactions']++;}
foreach($weeks as $w)foreach((array)($w['tasks']??[]) as $t){if(!is_array($t)||($t['status']??'')==='done')continue;$id=(string)($t['assignee']??'');if(isset($memberStats[$id]))$memberStats[$id]['open_tasks']++;}
function he(mixed $v):string{return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function hf(int|float $v):string{return number_format((float)$v,0,'.',',');}
?>
<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>خلاصه مدیریتی | دستیار فروش</title>
<link rel="stylesheet" href="assets/css/tokens.css"><link rel="stylesheet" href="assets/css/base.css"><link rel="stylesheet" href="assets/css/components.css"><link rel="stylesheet" href="assets/css/layout.css"><link rel="stylesheet" href="assets/css/pages.css"><link rel="stylesheet" href="assets/css/responsive.css"><link rel="stylesheet" href="assets/css/v04-product.css"><link rel="stylesheet" href="assets/css/v04-1-final.css"><link rel="stylesheet" href="assets/css/v05-rbac.css"><?php require __DIR__ . '/pwa_head.php'; ?></head>
<body class="v05-admin-page"><header class="v05-admin-header"><div><h1>خلاصه مدیریتی Read-only</h1><p>نمای تجمیعی بدون شماره تماس، آدرس، یادداشت خصوصی یا جزئیات کامل پرونده‌ها</p></div><div class="v05-header-user"><b><?=he($user['display_name'])?></b><span><?=he($user['role_label'])?></span><?php if(hippo_can($user,'plans.view_team_summary')):?><a href="planning.php">برنامه ماهانه</a><?php endif;?><a href="pilot_test.php">آزمون پایلوت</a><a href="pilot_issues.php">ثبت ایراد</a><a href="logout.php">خروج امن</a></div></header>
<main class="v05-admin-main"><div class="v05-security-note"><b>سیاست Summary-only فعال است</b><span>این حساب Full-State، Backup، Restore، مدیریت کاربر یا Export جزئیات کامل دریافت نمی‌کند.</span></div>
<section class="v04-metrics"><article class="v04-metric"><small>کل مشتریان</small><strong><?=hf(count($customers))?></strong></article><article class="v04-metric"><small>تعامل‌های ثبت‌شده</small><strong><?=hf(count($interactions))?></strong></article><article class="v04-metric"><small>پیگیری عقب‌افتاده</small><strong><?=hf($overdue)?></strong></article><article class="v04-metric"><small>بدون مسئول</small><strong><?=hf($unassigned)?></strong></article></section>
<div class="v04-report-grid"><section class="v04-report-block"><h2>هشدارهای تجمیعی</h2><div class="v04-mini-list"><div class="v04-mini-row"><span>پیگیری عقب‌افتاده</span><b><?=hf($overdue)?></b></div><div class="v04-mini-row"><span>بدون پیگیری آینده</span><b><?=hf($noFollowup)?></b></div><div class="v04-mini-row"><span>مشتری بدون مسئول</span><b><?=hf($unassigned)?></b></div></div></section><section class="v04-report-block"><h2>توزیع قیف</h2><div class="v04-mini-list"><?php foreach($stageLabels as $id=>$label):?><div class="v04-mini-row"><span><?=he($label)?></span><b><?=hf($stageCounts[$id])?></b></div><?php endforeach;?></div></section><section class="v04-report-block" style="grid-column:1/-1"><h2>خلاصه عملکرد اعضا</h2><div class="v05-table-wrap"><table class="v05-table"><thead><tr><th>عضو</th><th>تعداد مشتری</th><th>تعداد تعامل</th><th>کار باز</th></tr></thead><tbody><?php foreach($memberStats as $s):?><tr><td><b><?=he($s['name'])?></b></td><td><?=hf($s['customers'])?></td><td><?=hf($s['interactions'])?></td><td><?=hf($s['open_tasks'])?></td></tr><?php endforeach;?></tbody></table></div></section></div>
<p class="mini muted">Revision <?=hf((int)($row['revision']??0))?> · آخرین به‌روزرسانی <?=he((string)($row['updated_at']??'ثبت نشده'))?></p></main></body></html>
