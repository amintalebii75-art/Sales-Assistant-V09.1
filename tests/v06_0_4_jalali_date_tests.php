<?php
declare(strict_types=1);
$root = dirname(__DIR__);
$results=[];$failed=0;
function t(string $name, callable $fn): void {global $results,$failed;try{$detail=$fn();$results[]=['name'=>$name,'status'=>'PASS','detail'=>(string)$detail];}catch(Throwable $e){$failed++;$results[]=['name'=>$name,'status'=>'FAIL','detail'=>$e->getMessage()];}}
function a(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
$planning=file_get_contents($root.'/assets/js/planning.js');
$jalali=file_get_contents($root.'/assets/js/jalali-date.js');
$page=file_get_contents($root.'/planning.php');
$api=file_get_contents($root.'/planning_api.php');
$index=file_get_contents($root.'/index.php');
t('shared Jalali asset loaded',function()use($page){a(str_contains($page,'assets/js/jalali-date.js'),'JS not loaded');a(str_contains($page,'assets/css/jalali-date.css'),'CSS not loaded');return 'planning assets loaded';});
t('native planning date inputs removed',function()use($planning){a(!preg_match('/[\'\"]date[\'\"]/', $planning),'native date input remains');return 'no native date input';});
t('planning date selectors are Jalali',function()use($planning){foreach(['datePickerHtml(\'pwStart\'','datePickerHtml(\'pwEnd\'','datePickerHtml(\'ptDue\'','datePickerHtml(\'pteDue\''] as $needle)a(str_contains($planning,$needle),'missing '.$needle);return 'week/task selectors';});
t('planning month selectors are Jalali',function()use($planning){a(str_contains($planning,"monthPickerHtml('pcMonth'")&&str_contains($planning,"monthPickerHtml('pcoMonth'"),'month selectors missing');return 'create/copy month';});
t('Jalali month key accepted server-side',function()use($api){a(str_contains($api,'(?:13|14|15)\\d{2}'),'Jalali year validation missing');a(str_contains($api,'20\\d{2}'),'legacy Gregorian support missing');return 'new plus legacy keys';});
t('mixed legacy and Jalali plans sort by id',function()use($api){a(!str_contains($api,'ORDER BY month_key DESC'),'mixed month keys still sorted lexically');a(substr_count($api,'ORDER BY id DESC')>=2,'id ordering missing');return 'latest plan ordering preserved';});
t('daily dates remain ISO hidden values',function()use($jalali){a(str_contains($jalali,'type="hidden"')&&str_contains($jalali,'jalaliToIso'),'ISO hidden storage missing');return 'ISO backend contract';});
t('explicit Persian calendar display',function()use($planning,$index,$jalali){a(str_contains($jalali,'fa-IR-u-ca-persian'),'shared formatter missing');a(str_contains($planning,'formatDate')&&str_contains($index,'fa-IR-u-ca-persian'),'Persian display missing');return 'Persian calendar locale';});
foreach($results as $r)echo $r['status'].' | '.$r['name'].' | '.$r['detail'].PHP_EOL;
exit($failed?1:0);
