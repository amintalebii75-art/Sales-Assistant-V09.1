<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/permissions.php';

$hippoUser = hippo_require_login_page();
$canPermissions = hippo_is_manager($hippoUser) && hippo_can($hippoUser, 'permissions.manage');
$canAccess = hippo_is_manager($hippoUser) && hippo_can($hippoUser, 'customers.share');
$canAudit = hippo_is_manager($hippoUser) && hippo_can($hippoUser, 'audit.view');

if (!hippo_can($hippoUser, 'users.manage')) {
    http_response_code(403);
    echo '<!doctype html><html lang="fa" dir="rtl"><meta charset="utf-8"><title>دسترسی غیرمجاز</title><body><h1>دسترسی غیرمجاز</h1><p>این صفحه فقط برای مدیر دارای مجوز مدیریت کاربران است.</p></body></html>';
    exit;
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>کاربران و دسترسی‌ها | دستیار فروش</title>
    <link rel="stylesheet" href="assets/css/tokens.css">
    <link rel="stylesheet" href="assets/css/base.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/layout.css">
    <link rel="stylesheet" href="assets/css/pages.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
    <link rel="stylesheet" href="assets/css/v04-product.css">
    <link rel="stylesheet" href="assets/css/v04-1-final.css">
    <link rel="stylesheet" href="assets/css/v05-rbac.css">
    <link rel="stylesheet" href="assets/css/v07-ariana.css">
    <link rel="stylesheet" href="assets/css/v07-admin-security.css">
<?php require __DIR__ . '/pwa_head.php'; ?></head>
<body class="v05-admin-page v07-ui v076-admin-page">
    <header class="v076-admin-header">
        <div class="v076-header-inner">
            <div class="v076-header-brand">
                <div class="v076-brand-mark">G</div>
                <div>
                    <div class="v076-header-kicker">V07.6 · مدیریت امن تیم</div>
                    <h1>کاربران، نقش‌ها و دسترسی مشتریان</h1>
                    <p>مدیریت حساب‌های واقعی، Permissionها، سطح دسترسی مشتری و رویدادهای امنیتی</p>
                </div>
            </div>
            <div class="v076-header-actions">
                <a class="btn soft" href="index.php">بازگشت به برنامه</a><a class="btn" href="deployment_check.php">بررسی هاست</a><a class="btn" href="pilot_issues.php">ثبت ایراد</a>
                <div class="v076-user-chip">
                    <span class="v076-user-avatar"><?=htmlspecialchars(mb_substr((string)$hippoUser['display_name'], 0, 1), ENT_QUOTES, 'UTF-8')?></span>
                    <span><b><?=htmlspecialchars($hippoUser['display_name'], ENT_QUOTES, 'UTF-8')?></b><small><?=htmlspecialchars($hippoUser['role_label'], ENT_QUOTES, 'UTF-8')?></small></span>
                    <a href="logout.php">خروج</a>
                </div>
            </div>
        </div>
    </header>

    <main class="v05-admin-main v076-admin-main">
        <section class="v076-admin-metrics" aria-label="خلاصه مدیریت کاربران">
            <article><span class="v076-metric-icon">U</span><div><small>کل حساب‌ها</small><strong id="v076MetricUsers">—</strong></div></article>
            <article><span class="v076-metric-icon ok">A</span><div><small>حساب فعال</small><strong id="v076MetricActive">—</strong></div></article>
            <article><span class="v076-metric-icon warn">!</span><div><small>نیازمند بازبینی</small><strong id="v076MetricReview">—</strong></div></article>
            <article><span class="v076-metric-icon info">↔</span><div><small>دسترسی اشتراکی</small><strong id="v076MetricGrants">—</strong></div></article>
        </section>

        <section class="v076-security-banner">
            <div class="v076-security-shield">✓</div>
            <div>
                <b>کنترل سمت سرور فعال است</b>
                <span>مخفی‌شدن دکمه‌ها فقط بخشی از رابط است؛ تمام عملیات حساس در API، CSRF، نقش و Permission دوباره کنترل می‌شوند.</span>
            </div>
        </section>

        <nav class="v05-tabs v076-tabs" aria-label="بخش‌های مدیریت">
            <button class="active" data-tab="users"><span>01</span>کاربران</button>
            <?php if($canPermissions):?><button data-tab="permissions"><span>02</span>نقش‌ها و Permissionها</button><?php endif;?>
            <?php if($canAccess):?><button data-tab="access"><span>03</span>دسترسی مشتریان</button><?php endif;?>
            <?php if($canAudit):?><button data-tab="audit"><span>04</span>رویدادهای امنیتی</button><?php endif;?>
        </nav>

        <section id="v05Loading" class="v05-loading v076-loading">در حال دریافت اطلاعات امن…</section>
        <section id="tab-users" class="v05-tab active"></section>
        <section id="tab-permissions" class="v05-tab"></section>
        <section id="tab-access" class="v05-tab"></section>
        <section id="tab-audit" class="v05-tab"></section>
    </main>

    <div id="v05Modal" class="v05-modal" aria-hidden="true">
        <div class="v05-modal-backdrop" data-close-modal></div>
        <div class="v05-modal-card" role="dialog" aria-modal="true">
            <div id="v05ModalContent"></div>
        </div>
    </div>

    <div id="v05Toast" class="toast" role="status"></div>

    <script>
        window.HIPPO_V05 = {
            csrf: <?=json_encode($hippoUser['csrf_token'], JSON_UNESCAPED_UNICODE)?>,
            currentUserId: <?=(int)$hippoUser['id']?>,
            capabilities: <?=json_encode(['users'=>$canPermissions, 'sensitive_users'=>$canPermissions, 'permissions'=>$canPermissions, 'customer_access'=>$canAccess, 'audit'=>$canAudit, 'team_management'=>$canPermissions], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?>
        };
    </script>
    <script src="assets/js/users-rbac.js"></script>
</body>
</html>
