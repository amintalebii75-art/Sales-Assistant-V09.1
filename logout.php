<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/permissions.php';
hippo_session_start();
$user = hippo_current_user();
if ($user) {
    try { hippo_audit(hippo_db(), (int)$user['id'], 'logout', 'user', (string)$user['id']); } catch (Throwable $e) {}
}
hippo_destroy_session();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
?>
<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>خروج امن</title><script src="assets/js/cache-scope.js"></script></head><body><p>در حال پاک‌سازی داده محلی و خروج امن…</p><script>(async()=>{try{await HippoCacheSecurity.clearAll()}catch(e){}location.replace('login.php')})();</script></body></html>
