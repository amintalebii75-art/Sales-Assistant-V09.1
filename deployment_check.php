<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/permissions.php';
$user = hippo_require_login_page();
if (!hippo_is_manager($user) || !hippo_can($user, 'settings.manage')) { http_response_code(403); exit('دسترسی غیرمجاز'); }
$pdo = hippo_db();

function dc_e(mixed $v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function dc_https(): bool {
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') return true;
    return strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
}
function dc_table_exists(PDO $pdo, string $table): bool {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
    $stmt->execute([$table]);
    return (int)$stmt->fetchColumn() > 0;
}
function dc_column_exists(PDO $pdo, string $table, string $column): bool {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    $stmt->execute([$table, $column]);
    return (int)$stmt->fetchColumn() > 0;
}

$checks = [];
$add = static function(string $title, string $status, string $detail) use (&$checks): void {
    $checks[] = compact('title','status','detail');
};
$add('نسخه PHP', version_compare(PHP_VERSION, '8.1.0', '>=') ? 'pass' : 'fail', PHP_VERSION . ' — حداقل پیشنهادی 8.1');
$add('افزونه PDO MySQL', extension_loaded('pdo_mysql') ? 'pass' : 'fail', extension_loaded('pdo_mysql') ? 'فعال است' : 'روی هاست فعال نیست');
$add('پشتیبانی JSON', extension_loaded('json') ? 'pass' : 'fail', extension_loaded('json') ? 'فعال است' : 'فعال نیست');
$add('mbstring', extension_loaded('mbstring') ? 'pass' : 'warn', extension_loaded('mbstring') ? 'فعال است' : 'فعال نیست؛ برنامه fallback دارد');
$add('HTTPS', dc_https() ? 'pass' : 'warn', dc_https() ? 'اتصال امن تشخیص داده شد' : 'برای پایلوت اینترنتی HTTPS را فعال کن');
$add('config.php', is_file(__DIR__.'/config.php') ? 'pass' : 'fail', is_file(__DIR__.'/config.php') ? 'فایل محلی موجود است' : 'فایل تنظیمات پیدا نشد');
$add('نوشتن Session', is_writable((string)session_save_path()) ? 'pass' : 'warn', session_save_path() ?: 'مسیر پیش‌فرض PHP');
$add('Manifest برنامه', is_file(__DIR__.'/manifest.webmanifest') ? 'pass' : 'fail', 'برای نصب روی Android');
$add('Service Worker', is_file(__DIR__.'/sw.js') ? 'pass' : 'fail', 'فقط فایل‌های عمومی را Cache می‌کند');

$requiredTables = ['users','role_permissions','user_permission_overrides','customer_access','audit_logs','app_state','app_state_backups','monthly_plans','monthly_plan_weeks','monthly_plan_tasks','monthly_task_assignments','monthly_assignment_history'];
foreach ($requiredTables as $table) {
    $exists = dc_table_exists($pdo, $table);
    $add('جدول '.$table, $exists ? 'pass' : 'fail', $exists ? 'موجود' : 'وجود ندارد؛ Migration لازم است');
}
$requiredColumns = [
    ['users','status'],['users','team_member_id'],['users','rbac_review_required'],
    ['app_state','revision'],['app_state_backups','operation'],
    ['monthly_plans','revision'],['monthly_plan_weeks','revision'],['monthly_plan_tasks','revision'],['monthly_task_assignments','revision']
];
foreach ($requiredColumns as [$table,$column]) {
    $ok = dc_table_exists($pdo,$table) && dc_column_exists($pdo,$table,$column);
    $add("ستون {$table}.{$column}", $ok ? 'pass' : 'fail', $ok ? 'موجود' : 'وجود ندارد');
}
try {
    $state = $pdo->query('SELECT revision, updated_at, updated_by FROM app_state WHERE id=1')->fetch();
    $add('رکورد State اصلی', $state ? 'pass' : 'fail', $state ? 'Revision '.(int)$state['revision'].' · '.($state['updated_at'] ?: 'بدون زمان') : 'رکورد id=1 وجود ندارد');
} catch (Throwable $e) {
    $add('رکورد State اصلی','fail','قابل خواندن نیست');
}
try {
    $count = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE status='active'")->fetchColumn();
    $add('حساب فعال', $count > 0 ? 'pass' : 'fail', $count.' حساب فعال');
} catch (Throwable $e) { $add('حساب فعال','fail','جدول کاربران قابل خواندن نیست'); }

$summary = ['pass'=>0,'warn'=>0,'fail'=>0];
foreach ($checks as $c) $summary[$c['status']]++;
?>
<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>بررسی آمادگی هاست | دستیار فروش</title>
<link rel="stylesheet" href="assets/css/tokens.css"><link rel="stylesheet" href="assets/css/base.css"><link rel="stylesheet" href="assets/css/components.css"><link rel="stylesheet" href="assets/css/layout.css"><link rel="stylesheet" href="assets/css/pages.css"><link rel="stylesheet" href="assets/css/responsive.css"><link rel="stylesheet" href="assets/css/v07-ariana.css"><?php require __DIR__.'/pwa_head.php'; ?>
<style>.dc-wrap{max-width:1100px;margin:0 auto;padding:24px}.dc-head{display:flex;justify-content:space-between;gap:16px;align-items:center;margin-bottom:20px}.dc-summary{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:18px}.dc-card,.dc-row{background:#fff;border:1px solid #e8e4f4;border-radius:18px;padding:16px}.dc-card strong{font-size:28px}.dc-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.dc-row{display:flex;justify-content:space-between;gap:18px;align-items:flex-start}.dc-row span{max-width:56%;color:#6b647d;font-size:13px;line-height:1.7}.dc-badge{border-radius:999px;padding:5px 10px;font-size:12px;font-weight:800}.dc-badge.pass{background:#dcfce7;color:#166534}.dc-badge.warn{background:#fef3c7;color:#92400e}.dc-badge.fail{background:#fee2e2;color:#991b1b}@media(max-width:720px){.dc-head{align-items:flex-start;flex-direction:column}.dc-summary,.dc-grid{grid-template-columns:1fr}.dc-row{flex-direction:column}.dc-row span{max-width:100%}}</style></head><body class="v07-ui"><main class="dc-wrap"><header class="dc-head"><div><small>V09 · بررسی فقط‌خواندنی</small><h1>آمادگی هاست و دیتابیس</h1><p>این صفحه هیچ تغییری در دیتابیس ایجاد نمی‌کند.</p></div><div><a class="btn" href="index.php">بازگشت</a> <a class="btn soft" href="pilot_test.php">آزمون پایلوت</a></div></header>
<section class="dc-summary"><article class="dc-card"><small>موفق</small><strong><?=dc_e($summary['pass'])?></strong></article><article class="dc-card"><small>هشدار</small><strong><?=dc_e($summary['warn'])?></strong></article><article class="dc-card"><small>خطا</small><strong><?=dc_e($summary['fail'])?></strong></article></section>
<section class="dc-grid"><?php foreach($checks as $c):?><article class="dc-row"><div><span class="dc-badge <?=dc_e($c['status'])?>"><?=dc_e(strtoupper($c['status']))?></span><h3><?=dc_e($c['title'])?></h3></div><span><?=dc_e($c['detail'])?></span></article><?php endforeach;?></section>
</main></body></html>
