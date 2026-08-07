<?php
declare(strict_types=1);
require_once __DIR__ . '/../permissions.php';

$root = realpath(__DIR__ . '/..');
$index = file_get_contents($root . '/index.php') ?: '';
$api = file_get_contents($root . '/api.php') ?: '';
$js = file_get_contents($root . '/assets/js/v09-1-configurable-forms.js') ?: '';
$css = file_get_contents($root . '/assets/css/v09-1-configurable-forms.css') ?: '';
$sw = file_get_contents($root . '/sw.js') ?: '';
$results = [];
$add = static function(string $id, string $title, bool $ok, string $detail='') use (&$results): void {
    $results[] = ['id'=>$id,'title'=>$title,'status'=>$ok?'PASS':'FAIL','detail'=>$detail];
};

$cfg = hippo_normalize_form_config_payload([
    'customer'=>[
        'name'=>['enabled'=>false,'required'=>false,'label'=>'نام جدید','roles'=>['manager'=>false]],
        'city'=>['enabled'=>false,'required'=>true,'label'=>'شهر سفارشی','roles'=>['manager'=>true,'marketer'=>false,'center_call'=>true]],
    ],
    'interaction'=>[
        'results'=>['enabled'=>false,'required'=>false],
        'route'=>['enabled'=>true,'required'=>true,'masterKey'=>'route'],
    ],
]);
$add('FC-01','فیلدهای سیستمی قابل خاموش‌شدن نیستند', $cfg['customer']['name']['enabled']===true && $cfg['customer']['name']['required']===true && $cfg['interaction']['results']['enabled']===true, 'name/customer/results locked');
$add('FC-02','مدیر می‌تواند فیلد غیرسیستمی را مخفی و اجباری کند', $cfg['customer']['city']['enabled']===false && $cfg['customer']['city']['required']===true, 'city config retained');
$add('FC-03','نمایش فیلد برای نقش‌ها مستقل ذخیره می‌شود', $cfg['customer']['city']['roles']['marketer']===false && $cfg['customer']['city']['roles']['center_call']===true, 'role visibility retained');
$add('FC-04','MasterKey فقط از فهرست مجاز پذیرفته می‌شود', ($cfg['interaction']['route']['masterKey']??'')==='route', 'route accepted');

$md = hippo_normalize_master_data_payload([
    'source'=>['label'=>'نحوه آشنایی','addMode'=>'approval','options'=>[
        ['id'=>'x','label'=>'تماس تلفنی','active'=>true,'status'=>'active'],
        ['id'=>'y','label'=>'تماس تلفنی','active'=>true,'status'=>'active'],
        ['id'=>'bad space','label'=>'پیشنهاد تازه','active'=>false,'status'=>'pending','createdBy'=>'کاربر'],
    ]],
    'currency'=>['addMode'=>'unsafe','options'=>[]],
]);
$add('MD-01','گزینه تکراری اطلاعات پایه حذف می‌شود', count($md['source']['options'])===2, 'duplicate label removed');
$add('MD-02','حالت پیشنهاد با تأیید مدیر حفظ می‌شود', $md['source']['addMode']==='approval' && $md['source']['options'][1]['status']==='pending', 'approval/pending');
$add('MD-03','حالت افزودن نامعتبر به حالت امن برمی‌گردد', $md['currency']['addMode']==='manager_only', 'unsafe mode rejected');

$editor = ['role'=>'marketer','permissions'=>[],'team_member_id'=>'m1'];
$filtered = hippo_filter_master_data(['source'=>$md['source']], $editor);
$add('MD-04','گزینه در انتظار برای کاربر عادی نمایش داده نمی‌شود', count($filtered['source']['options'])===1 && $filtered['source']['options'][0]['status']==='active', 'pending hidden');

$callFields = hippo_customer_output_allowlist('call');
$editFields = hippo_customer_output_allowlist('edit');
$add('SEC-01','سطح Call اطلاعات مالی و سفارشی پرونده را دریافت نمی‌کند', !in_array('currency',$callFields,true) && !in_array('estimatedVolume',$callFields,true), implode(',',$callFields));
$add('SEC-02','سطح Edit فیلدهای جدید مشتری را دریافت می‌کند', count(array_diff(['productGroup','consumptionType','packaging','currency'],$editFields))===0, 'custom customer fields allowed for edit');
$interactionCall = hippo_interaction_allowed_fields(['role'=>'center_call'],'call');
$add('SEC-03','مرکز تماس اطلاعات پایه مذاکره را ثبت می‌کند اما فیلد مالی ندارد', count(array_diff(['contactFor','route','currency'],$interactionCall))===0 && count(array_intersect(['price','value','payment','purchase'],$interactionCall))===0, implode(',',$interactionCall));
$add('SEC-04','سطح View به Call ارتقا داده نمی‌شود', hippo_interaction_level_for_user(['role'=>'center_call'],'view')==='view' && str_contains($index,'function interactionAccessLevel(c){return customerAccessLevel(c)}'), 'hard ceiling');

$add('UI-01','صفحه مدیریت فرم‌ها و اطلاعات پایه در منوی مدیر وجود دارد', str_contains($index,'data-page="baseinfo"') && str_contains($index,'id="page-baseinfo"'), 'menu and page');
$add('UI-02','CSS و JavaScript نسخه V09.1 بارگذاری می‌شوند', str_contains($index,'v09-1-configurable-forms.css') && str_contains($index,'v09-1-configurable-forms.js') && strlen($css)>1000 && strlen($js)>5000, 'assets present');
$add('UI-03','فرم مشتری و مذاکره از تنظیمات پویا استفاده می‌کنند', str_contains($js,"window.openCustomerModal=function") && str_contains($js,"window.renderActivities=function") && str_contains($js,"window.saveInteraction=function") && str_contains($js,"roleVisible('customer'") && str_contains($js,"roleVisible('interaction'"), 'dynamic renderers');
$add('UI-04','کاربر مجاز می‌تواند با دکمه مثبت گزینه پیشنهاد کند', str_contains($js,'openAddOption') && str_contains($js,'base_option_create') && str_contains($api,"\$action==='base_option_create'"), 'plus/add endpoint');
$add('UI-05','مدیر می‌تواند نمایش، اجبار و نقش‌ها را تنظیم کند', str_contains($js,'updateField(entity,key,prop,value)') && str_contains($js,'updateRole(entity,key,role,value)'), 'manager controls');
$add('UI-06','خاموش‌کردن گزینه داده قبلی را حذف نمی‌کند', str_contains($js,"o.status=status;o.active=status==='active'") && !str_contains($js,'.splice('), 'status based deactivation');
$add('PWA-01','Service Worker دارایی‌های V09.1 را Cache می‌کند', str_contains($sw,"hippo-static-v09-1-0") && str_contains($sw,'v09-1-configurable-forms.css') && str_contains($sw,'v09-1-configurable-forms.js'), 'cache updated');
$fontFiles = glob($root . '/assets/fonts/*.{ttf,otf,woff,woff2}', GLOB_BRACE) ?: [];
$add('PKG-01','فایل باینری فونت داخل بسته قرار نگرفته است', count($fontFiles)===0, 'font binaries absent');

$pass=count(array_filter($results,static fn($r)=>$r['status']==='PASS'));
$fail=count($results)-$pass;
$out=['suite'=>'V09.1 Configurable Forms and Base Data','generated_at'=>date(DATE_ATOM),'pass'=>$pass,'fail'=>$fail,'results'=>$results];
echo json_encode($out,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT),PHP_EOL;
exit($fail===0?0:1);
