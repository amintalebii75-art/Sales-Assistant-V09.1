<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/permissions.php';
$user = hippo_require_login_page();
$role = hippo_role_alias((string)$user['role']);
$roleLabels = [
    'manager' => 'مدیر',
    'marketer' => 'بازاریاب / کارشناس فروش',
    'center_call' => 'مرکز تماس',
    'manager_viewer' => 'ناظر مدیریتی',
];
function h(mixed $v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function add_check(array &$checks, string $id, string $label, bool $ok, string $detail = '', string $severity = 'required'): void {
    $checks[] = ['id'=>$id,'label'=>$label,'ok'=>$ok,'detail'=>$detail,'severity'=>$severity];
}
$checks = [];
add_check($checks, 'php_version', 'نسخه PHP مناسب است', version_compare(PHP_VERSION, '8.1.0', '>='), 'PHP ' . PHP_VERSION);
add_check($checks, 'pdo_mysql', 'درایور PDO MySQL فعال است', extension_loaded('pdo_mysql'), extension_loaded('pdo_mysql') ? 'فعال' : 'غیرفعال');
add_check($checks, 'config', 'فایل config.php موجود است', is_file(__DIR__ . '/config.php'), is_file(__DIR__ . '/config.php') ? 'موجود' : 'ناموجود');
add_check($checks, 'session', 'Session کاربر فعال است', session_status() === PHP_SESSION_ACTIVE, session_name());
add_check($checks, 'csrf', 'توکن CSRF معتبر تولید شده', is_string($user['csrf_token'] ?? null) && strlen((string)$user['csrf_token']) >= 32, 'توکن در پاسخ نمایش داده نمی‌شود');
add_check($checks, 'team_link', 'اتصال حساب عملیاتی به عضو تیم معتبر است', !hippo_role_requires_team_member($role) || !empty($user['team_member_valid']), hippo_role_requires_team_member($role) ? (!empty($user['team_member_valid']) ? 'معتبر' : 'نیازمند اصلاح') : 'برای این نقش الزامی نیست');

$dbMeta = ['connected'=>false,'revision'=>null,'updated_at'=>null,'missing_tables'=>[],'table_count'=>0];
try {
    $pdo = hippo_db();
    $pdo->query('SELECT 1')->fetchColumn();
    $dbMeta['connected'] = true;
    $required = ['users','role_permissions','user_permission_overrides','customer_access','audit_logs','app_state','app_state_backups','monthly_plans','monthly_plan_weeks','monthly_plan_tasks','monthly_task_assignments','monthly_assignment_history'];
    $rows = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    $present = array_map('strval', $rows ?: []);
    $dbMeta['table_count'] = count($present);
    $dbMeta['missing_tables'] = array_values(array_diff($required, $present));
    $stateRow = $pdo->query('SELECT revision, updated_at FROM app_state WHERE id=1')->fetch();
    if ($stateRow) {
        $dbMeta['revision'] = (int)$stateRow['revision'];
        $dbMeta['updated_at'] = $stateRow['updated_at'];
    }
} catch (Throwable $e) {
    error_log('Pilot test diagnostics failed: ' . $e->getMessage());
}
add_check($checks, 'db', 'اتصال خواندنی به پایگاه داده برقرار است', (bool)$dbMeta['connected'], $dbMeta['connected'] ? 'اتصال برقرار' : 'اتصال ناموفق');
add_check($checks, 'tables', 'جدول‌های اصلی سیستم موجود هستند', $dbMeta['connected'] && count($dbMeta['missing_tables']) === 0, count($dbMeta['missing_tables']) ? ('کمبود: ' . implode('، ', $dbMeta['missing_tables'])) : ($dbMeta['table_count'] . ' جدول قابل مشاهده'));
add_check($checks, 'state', 'رکورد وضعیت مرکزی قابل خواندن است', $dbMeta['revision'] !== null, $dbMeta['revision'] !== null ? ('Revision ' . $dbMeta['revision']) : 'رکورد app_state پیدا نشد');

$assets = [
    'assets/css/v07-ariana.css',
    'assets/css/v07-dashboard-reports.css',
    'assets/css/v07-settings-deployment.css',
    'assets/js/v07-customer-profile.js',
    'assets/js/v07-dashboard-reports.js',
    'assets/js/v07-settings-deployment.js',
    'assets/js/jalali-date.js',
];
$missingAssets = array_values(array_filter($assets, static fn(string $p): bool => !is_file(__DIR__ . '/' . $p)));
add_check($checks, 'assets', 'دارایی‌های اصلی V07 موجود هستند', count($missingAssets) === 0, $missingAssets ? ('کمبود: ' . implode('، ', $missingAssets)) : (count($assets) . ' فایل بررسی شد'));
$https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
add_check($checks, 'https', 'اتصال HTTPS فعال است', $https, $https ? 'فعال' : 'در localhost قابل قبول؛ روی هاست الزامی است', 'production');

$automaticPassed = count(array_filter($checks, static fn(array $c): bool => $c['ok']));
$automaticTotal = count($checks);
$backUrl = $role === 'manager_viewer' ? 'manager.php' : 'index.php';
$authPayload = [
    'id'=>(int)$user['id'],
    'display_name'=>(string)$user['display_name'],
    'role'=>$role,
    'role_label'=>$roleLabels[$role] ?? (string)$user['role_label'],
    'team_member_id'=>$user['team_member_id'],
    'permissions'=>$user['permissions'],
    'scope_version'=>$user['scope_version'],
];
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>آزمون پایلوت V09.0 | دستیار فروش</title>
<link rel="stylesheet" href="assets/css/tokens.css">
<link rel="stylesheet" href="assets/css/base.css">
<link rel="stylesheet" href="assets/css/components.css">
<link rel="stylesheet" href="assets/css/v07-ariana.css?v=0900">
<link rel="stylesheet" href="assets/css/v07-pilot-test.css?v=0900">
<?php require __DIR__ . '/pwa_head.php'; ?></head>
<body class="v078-pilot-body">
<header class="v078-topbar">
  <div class="v078-brand"><span class="v078-mark">✓</span><div><b>آزمون پایلوت V09.0</b><small>کنترل فنی و تست چهار نقش در پایلوت هاست و موبایل</small></div></div>
  <div class="v078-top-actions"><span class="v078-user-chip"><?=h($user['display_name'])?> · <?=h($roleLabels[$role] ?? $user['role_label'])?></span><a class="btn" href="pilot_issues.php">ثبت ایراد</a><a class="btn" href="<?=h($backUrl)?>">بازگشت به برنامه</a><a class="btn danger" href="logout.php">خروج</a></div>
</header>
<main class="v078-shell">
<section class="v078-hero">
  <div><span class="v078-kicker">نسخه کامل یکپارچه پایلوت</span><h1>نسخه یکپارچه کامل برای اتصال یک‌باره به localhost</h1><p>بررسی‌های خودکار فقط خواندنی هستند. تست‌های عملی را با هر چهار حساب انجام بده و وضعیت هر مورد را ثبت کن.</p></div>
  <div class="v078-score"><strong id="overallPercent">۰٪</strong><span>پیشرفت کل تست‌ها</span><div class="v078-progress"><i id="overallBar"></i></div></div>
</section>

<section class="v078-metrics">
  <article><small>بررسی خودکار</small><strong><?=h($automaticPassed)?> / <?=h($automaticTotal)?></strong><span><?=h($automaticPassed === $automaticTotal ? 'بدون خطای الزامی' : 'نیازمند بررسی')?></span></article>
  <article><small>Revision فعلی</small><strong><?=h($dbMeta['revision'] ?? '—')?></strong><span><?=h($dbMeta['updated_at'] ?? 'ثبت نشده')?></span></article>
  <article><small>نقش فعال</small><strong><?=h($roleLabels[$role] ?? $role)?></strong><span><?=h($user['team_member_id'] ?: 'بدون شناسه تیم')?></span></article>
  <article><small>وضعیت انتشار</small><strong>پایلوت محدود</strong><span>Production عمومی هنوز تأیید نشده</span></article>
</section>

<div class="v078-grid">
<section class="v078-card v078-auto-card">
  <div class="v078-card-head"><div><h2>بررسی‌های خودکار</h2><p>این موارد هنگام بازشدن صفحه از PHP، Session، دیتابیس و فایل‌ها خوانده شده‌اند.</p></div><button class="btn" type="button" onclick="location.reload()">اجرای دوباره</button></div>
  <div class="v078-check-list">
  <?php foreach($checks as $c): ?>
    <div class="v078-auto-row <?= $c['ok'] ? 'pass' : ($c['severity']==='production' ? 'warn' : 'fail') ?>">
      <span class="v078-auto-icon"><?= $c['ok'] ? '✓' : ($c['severity']==='production' ? '!' : '×') ?></span>
      <div><b><?=h($c['label'])?></b><small><?=h($c['detail'])?></small></div>
      <em><?= $c['ok'] ? 'PASS' : ($c['severity']==='production' ? 'LOCAL' : 'FAIL') ?></em>
    </div>
  <?php endforeach; ?>
  </div>
</section>

<section class="v078-card">
  <div class="v078-card-head"><div><h2>بررسی مرورگر</h2><p>پس از بارگذاری، JavaScript اتصال API، Storage و Responsive را بررسی می‌کند.</p></div><span id="browserSummary" class="v078-status pending">در حال اجرا</span></div>
  <div id="browserChecks" class="v078-check-list"><div class="v078-skeleton"></div><div class="v078-skeleton"></div><div class="v078-skeleton"></div></div>
</section>
</div>

<section class="v078-card v078-manual">
  <div class="v078-card-head"><div><h2>تست عملی نقش‌ها</h2><p>هر تست را با حساب مربوط انجام بده. وضعیت‌ها در همین مرورگر ذخیره می‌شوند و با تعویض حساب باقی می‌مانند.</p></div><div class="v078-actions"><button class="btn" id="downloadReportBtn" type="button">دانلود گزارش</button><button class="btn danger" id="resetCurrentBtn" type="button">پاک‌کردن تست نقش فعلی</button></div></div>
  <div class="v078-role-tabs" id="roleTabs"></div>
  <div id="manualChecklist"></div>
</section>

<section class="v078-card v078-release-gate">
  <div><h2>دروازه انتقال به هاست پایلوت</h2><p id="releaseMessage">تا پایان تست‌ها، نسخه فقط روی XAMPP نگه داشته شود.</p></div>
  <span id="releaseBadge" class="v078-release-badge hold">HOLD</span>
</section>
</main>
<script>
window.HIPPO_PILOT_AUTH=<?=json_encode($authPayload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?>;
window.HIPPO_PILOT_SERVER_CHECKS=<?=json_encode($checks, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?>;
</script>
<script src="assets/js/v07-pilot-test.js?v=0900"></script>
</body></html>
