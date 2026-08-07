<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/permissions.php';
$user = hippo_require_login_page();
$canEnter = hippo_can($user, 'plans.view_own') || hippo_can($user, 'plans.view_team') || hippo_can($user, 'plans.view_team_summary');
if (!$canEnter) {
    http_response_code(403);
    exit('<!doctype html><html lang="fa" dir="rtl"><meta charset="utf-8"><title>دسترسی غیرمجاز</title><body><h1>دسترسی غیرمجاز</h1><p>Permission برنامه ماهانه برای این حساب فعال نیست.</p></body></html>');
}
$back = hippo_role_alias((string)$user['role']) === 'manager_viewer' ? 'manager.php' : 'index.php';
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>برنامه ماهانه چهارهفته‌ای | دستیار فروش</title>
<link rel="stylesheet" href="assets/css/tokens.css">
<link rel="stylesheet" href="assets/css/base.css">
<link rel="stylesheet" href="assets/css/components.css">
<link rel="stylesheet" href="assets/css/layout.css">
<link rel="stylesheet" href="assets/css/pages.css">
<link rel="stylesheet" href="assets/css/responsive.css">
<link rel="stylesheet" href="assets/css/v05-rbac.css">
<link rel="stylesheet" href="assets/css/v07-ariana.css">
<link rel="stylesheet" href="assets/css/planning.css">
<link rel="stylesheet" href="assets/css/v07-planning.css">
<link rel="stylesheet" href="assets/css/jalali-date.css">
<?php require __DIR__ . '/pwa_head.php'; ?></head>
<body class="planning-page v07-ui">
<header class="planning-topbar">
  <div class="planning-topbar-main">
    <a class="planning-back" href="<?=htmlspecialchars($back,ENT_QUOTES,'UTF-8')?>" aria-label="بازگشت به سامانه">←</a>
    <span class="planning-brand-mark" aria-hidden="true">گ</span>
    <div class="planning-brand-copy"><span class="planning-kicker">V07 · برنامه اجرایی فروش</span><h1>برنامه ماهانه و وظایف تیم</h1><p>ساخت برنامه چهارهفته‌ای، تخصیص مستقل و پایش پیشرفت واقعی</p></div>
  </div>
  <div class="planning-user"><span class="planning-user-avatar" aria-hidden="true"><?=htmlspecialchars(mb_substr((string)$user['display_name'],0,1),ENT_QUOTES,'UTF-8')?></span><span class="planning-user-copy"><b><?=htmlspecialchars((string)$user['display_name'],ENT_QUOTES,'UTF-8')?></b><small><?=htmlspecialchars((string)$user['role_label'],ENT_QUOTES,'UTF-8')?></small></span><a href="pilot_issues.php">ثبت ایراد</a><a href="logout.php">خروج امن</a></div>
</header>
<main class="planning-shell">
  <section id="planningLoading" class="planning-loading"><span class="planning-spinner" aria-hidden="true"></span><div><b>در حال دریافت برنامه</b><p>اطلاعات مجاز این حساب از API مستقل بارگذاری می‌شود.</p></div></section>
  <section id="planningApp" hidden></section>
</main>
<div id="planningModal" class="planning-modal" aria-hidden="true"><div class="planning-modal-backdrop" data-planning-close></div><div class="planning-modal-card" role="dialog" aria-modal="true"><div id="planningModalBody"></div></div></div>
<div id="planningToast" class="planning-toast" role="status" aria-live="polite"></div>
<script>
window.HIPPO_PLANNING = <?=json_encode([
  'csrf'=>(string)$user['csrf_token'],
  'user'=>['id'=>(int)$user['id'],'display_name'=>(string)$user['display_name'],'role'=>(string)$user['role'],'role_label'=>(string)$user['role_label']],
  'permissions'=>(array)$user['permissions'],
  'permission_fingerprint'=>(string)$user['permission_fingerprint'],
], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?>;
</script>
<script src="assets/js/jalali-date.js"></script>
<script src="assets/js/planning.js"></script>
</body>
</html>
