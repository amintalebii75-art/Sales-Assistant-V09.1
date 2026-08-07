<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/permissions.php';
hippo_session_start();
$loginCsrf = hippo_csrf_token();

if (!empty($_SESSION['user_id'])) {
    $existing = hippo_current_user();
    if ($existing) {
        header('Location: ' . (!hippo_operational_account_ready($existing) ? 'account_review.php' : ($existing['must_change_password'] ? 'change_password.php' : (hippo_role_alias($existing['role']) === 'manager_viewer' ? 'manager.php' : 'index.php'))));
        exit;
    }
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hippo_verify_csrf($_POST)) {
        $error = 'درخواست امنیتی معتبر نیست. صفحه را تازه‌سازی و دوباره تلاش کنید.';
    } else {
        $username = strtolower(trim((string)($_POST['username'] ?? '')));
        $password = (string)($_POST['password'] ?? '');
        if ($username === '' || $password === '') {
            $error = 'نام کاربری و رمز عبور را وارد کنید.';
        } else {
        $pdo = hippo_db();
        $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        $row = $stmt->fetch();
        $valid = false;
        if ($row) {
            $lockedUntil = $row['locked_until'] ?? null;
            if ((string)($row['status'] ?? '') === 'locked' && $lockedUntil && strtotime((string)$lockedUntil) <= time()) {
                $unlock = $pdo->prepare("UPDATE users SET status='active', failed_attempts=0, locked_until=NULL WHERE id=?");
                $unlock->execute([(int)$row['id']]);
                $row['status'] = 'active'; $row['failed_attempts'] = 0; $row['locked_until'] = null;
            }
            $active = (string)($row['status'] ?? '') === 'active';
            $notLocked = empty($row['locked_until']) || strtotime((string)$row['locked_until']) <= time();
            $valid = $active && $notLocked && password_verify($password, (string)$row['password_hash']);
        }

        if ($valid && $row) {
            $update = $pdo->prepare('UPDATE users SET last_login_at=CURRENT_TIMESTAMP, failed_attempts=0, locked_until=NULL, status=\'active\' WHERE id=?');
            $update->execute([(int)$row['id']]);
            $row['last_login_at'] = date('Y-m-d H:i:s');
            hippo_login_session($row);
            hippo_audit($pdo, (int)$row['id'], 'login_success', 'user', (string)$row['id']);
            $role = hippo_role_alias((string)$row['role']);
            $teamReady = !hippo_role_requires_team_member($role) || (!empty($row['team_member_id']) && empty($row['rbac_review_required']) && hippo_team_member_exists($pdo, (string)$row['team_member_id']));
            header('Location: ' . (!$teamReady ? 'account_review.php' : (!empty($row['must_change_password']) ? 'change_password.php' : ($role === 'manager_viewer' ? 'manager.php' : 'index.php'))));
            exit;
        }

        if ($row && hippo_role_requires_team_member((string)($row['role'] ?? '')) && (empty($row['team_member_id']) || !empty($row['rbac_review_required']))) {
            hippo_audit($pdo, (int)$row['id'], 'operational_account_blocked_without_team_member', 'user', (string)$row['id'], 'denied', ['status'=>(string)($row['status'] ?? 'inactive')]);
        }
        if ($row && (string)($row['status'] ?? '') !== 'inactive') {
            $attempts = (int)($row['failed_attempts'] ?? 0) + 1;
            if ($attempts >= 5) {
                $until = date('Y-m-d H:i:s', time() + 15 * 60);
                $update = $pdo->prepare("UPDATE users SET failed_attempts=?, status='locked', locked_until=? WHERE id=?");
                $update->execute([$attempts, $until, (int)$row['id']]);
                hippo_audit($pdo, (int)$row['id'], 'account_locked', 'user', (string)$row['id'], 'locked', ['minutes'=>15]);
            } else {
                $update = $pdo->prepare('UPDATE users SET failed_attempts=? WHERE id=?');
                $update->execute([$attempts, (int)$row['id']]);
            }
            hippo_audit($pdo, (int)$row['id'], 'login_failed', 'user', (string)$row['id'], 'denied', ['attempts'=>$attempts]);
        } else {
            hippo_audit($pdo, null, 'login_failed', 'user', null, 'denied', ['username_hash'=>hash('sha256',$username)]);
        }
        // Same message for unknown username, wrong password, inactive and locked accounts.
        $error = 'نام کاربری یا رمز عبور اشتباه است یا حساب موقتاً در دسترس نیست.';
        }
    }
}
?>
<!DOCTYPE html><html lang="fa" dir="rtl"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>ورود | دستیار فروش</title>
<link rel="stylesheet" href="assets/css/compat-login.css"><link rel="stylesheet" href="assets/css/tokens.css"><link rel="stylesheet" href="assets/css/base.css"><link rel="stylesheet" href="assets/css/components.css"><link rel="stylesheet" href="assets/css/layout.css"><link rel="stylesheet" href="assets/css/pages.css"><link rel="stylesheet" href="assets/css/responsive.css"><link rel="stylesheet" href="assets/css/v04-product.css"><link rel="stylesheet" href="assets/css/v04-1-final.css"><link rel="stylesheet" href="assets/css/v05-rbac.css"><script src="assets/js/cache-scope.js"></script><script>window.addEventListener("DOMContentLoaded",()=>HippoCacheSecurity.clearLegacy());</script><?php require __DIR__ . '/pwa_head.php'; ?></head>
<body class="login-page"><section class="login-intro" aria-label="معرفی سامانه"><div class="login-mark">فروش</div><h1>دستیار فروش</h1><p>ورود امن به فضای مشتریان، پیگیری‌ها و کارهای روزانه.</p><div class="alert info" style="max-width:520px;margin-top:20px">نقش، وضعیت و دسترسی حساب در هر درخواست از سرور بررسی می‌شود.</div></section>
<main class="login-panel"><form class="login-card" method="post" autocomplete="off"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars($loginCsrf,ENT_QUOTES,'UTF-8')?>"><h2>ورود به حساب</h2><p class="sub">پس از پنج تلاش ناموفق، حساب ۱۵ دقیقه قفل می‌شود.</p><div class="field"><label for="u">نام کاربری</label><input id="u" name="username" autocomplete="username" required autofocus></div><div class="field" style="margin-top:14px"><label for="p">رمز عبور</label><input id="p" name="password" type="password" autocomplete="current-password" required></div><button class="btn primary" type="submit">ورود</button><?php if($error):?><div class="alert danger" role="alert" style="margin-top:14px"><?=htmlspecialchars($error,ENT_QUOTES,'UTF-8')?></div><?php endif;?><div class="login-help">برای فعال‌سازی یا بازیابی حساب با مدیر سیستم تماس بگیرید.</div></form></main></body></html>
