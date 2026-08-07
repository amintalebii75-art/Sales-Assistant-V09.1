<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/permissions.php';

$root = dirname(__DIR__);
$results = [];
$test = static function(string $name, bool $ok, string $detail='') use (&$results): void {
    $results[] = ['name'=>$name,'status'=>$ok?'PASS':'FAIL','detail'=>$detail];
};

$test('Customer Access View is a hard ceiling', hippo_interaction_level_for_user(['role'=>'center_call'],'view') === 'view', 'no implicit View→Call upgrade');
$test('Customer Access Call remains usable', hippo_interaction_level_for_user(['role'=>'center_call'],'call') === 'call', 'explicit manager grant required');
try {
    hippo_sanitize_interaction_payload(['id'=>'i1','customerId'=>'c1','channel'=>'call','date'=>'2026-08-05','resultIds'=>['follow_up']], ['role'=>'center_call'], 'view');
    $blocked = false;
} catch (HippoAuthorizationException $e) { $blocked = $e->errorCode === 'interaction_call_access_required'; }
$test('View interaction is rejected server-side', $blocked, 'center call cannot write with view-only grant');
try {
    $safe = hippo_sanitize_interaction_payload(['id'=>'i1','customerId'=>'c1','channel'=>'call','date'=>'2026-08-05','resultIds'=>['follow_up','quote_requested']], ['role'=>'center_call'], 'call');
    $callOk = count($safe['resultIds']) === 2;
} catch (Throwable $e) { $callOk = false; }
$test('Call interaction supports multiple allowed results', $callOk, 'multi-select preserved');

$required = ['manifest.webmanifest','sw.js','offline.html','assets/js/pwa.js','assets/css/pwa.css','assets/icons/icon-192.png','assets/icons/icon-512.png','deployment_check.php','pilot_issues.php','config.production.sample.php'];
foreach ($required as $file) $test('required file: '.$file, is_file($root.'/'.$file), $file);
$sw = (string)@file_get_contents($root.'/sw.js');
$test('Service worker does not cache PHP/API', str_contains($sw,"path.endsWith('.php')") && str_contains($sw,"cache:'no-store'"), 'authenticated pages are network-only');
$ht = (string)@file_get_contents($root.'/.htaccess');
$test('PHP cache-control hardening', str_contains($ht,'no-store, no-cache') && str_contains($ht,'Content-Security-Policy'), 'Apache security headers');
$auth = (string)@file_get_contents($root.'/auth.php');
$test('PHP no-store fallback headers', str_contains($auth,'hippo_send_private_no_store_headers'), 'works when mod_headers is unavailable');
$v05 = (string)@file_get_contents($root.'/assets/js/v05-app.js');
$test('Center Call order/pipeline menus are hidden', str_contains($v05,"['pipeline','operations'].includes(page)"), 'call center stays in customer/call workflow');
$dc = (string)@file_get_contents($root.'/deployment_check.php');
$test('Deployment check is manager-only', str_contains($dc,'!hippo_is_manager($user)') && str_contains($dc,"settings.manage"), 'read-only manager gate');
$issues = (string)@file_get_contents($root.'/pilot_issues.php');
$test('Pilot issue log is browser-local', str_contains($issues,'localStorage') && !str_contains($issues,'INSERT INTO'), 'no customer data write');
$test('Real config is not bundled', !is_file($root.'/config.php'), 'sample files only');
$test('Unsafe create_admin is not bundled', !is_file($root.'/create_admin.php'), 'existing manager workflow preserved');

$pass = count(array_filter($results, static fn($r)=>$r['status']==='PASS'));
$fail = count($results)-$pass;
echo json_encode(['suite'=>'V09 Host Pilot/PWA Complete','pass'=>$pass,'fail'=>$fail,'results'=>$results], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),"\n";
exit($fail ? 1 : 0);
