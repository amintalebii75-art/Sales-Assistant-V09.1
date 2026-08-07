<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
$user = hippo_current_user();
if (!$user) { header('Location: login.php'); exit; }
if (hippo_operational_account_ready($user)) { header('Location: index.php'); exit; }
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>حساب نیازمند تکمیل | دستیار فروش</title>
<link rel="stylesheet" href="assets/css/tokens.css"><link rel="stylesheet" href="assets/css/base.css"><link rel="stylesheet" href="assets/css/components.css"><link rel="stylesheet" href="assets/css/compat-login.css">
<script src="assets/js/cache-scope.js"></script>
<?php require __DIR__ . '/pwa_head.php'; ?></head>
<body class="login-page">
<section class="login-intro"><div class="login-mark">RBAC</div><h1>حساب نیازمند تکمیل است</h1><p>این حساب عملیاتی هنوز به عضو معتبر تیم متصل نشده یا بازبینی دسترسی آن پایان نیافته است.</p></section>
<main class="login-panel"><div class="login-card"><h2>دسترسی Workspace مسدود است</h2><div class="alert warn">برای جلوگیری از مشاهده یا تصاحب داده‌های عضو دیگر، ورود به مشتریان، تماس‌ها، پیگیری‌ها و وظایف تا تأیید مدیر غیرفعال مانده است.</div><p class="sub">مدیر باید عضو تیم صحیح را متصل کند، بازبینی RBAC را تکمیل کند و سپس حساب را فعال نگه دارد.</p><a class="btn primary" href="logout.php">خروج امن</a></div></main>
<script>HippoCacheSecurity.clearAll().catch(()=>{});</script>
</body></html>
