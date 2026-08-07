# CHANGELOG — V06.0.3 Localhost Security Fix

## Scope

اصلاح نسخه `sales_assistant_staging(1).zip` برای نصب localhost و بازگرداندن مرزهای امنیتی سند انتقال V06.0.2. قابلیت تجاری جدید خارج از Scope اضافه نشده است.

## اصلاحات

- حذف Endpoint داخلی و بدون Login از ابتدای `users.php` که مستقیماً `app_state` را تغییر می‌داد.
- حذف امکان ساخت `custom_roles`؛ نقش‌های سرور همچنان فقط نقش‌های قفل‌شده پروژه هستند.
- افزودن Action رسمی `create_team_member` در `users_api.php` با Manager واقعی، `permissions.manage`، CSRF، Revision، Transaction، Backup و Audit.
- رفع خطاهای Syntax در `assets/js/users-rbac.js`.
- رفع خطای تایپی `customers.comap` در رابط دسترسی مشتریان.
- رد کردن Team Member غیرفعال در Session، اتصال حساب و فهرست کاربران واجد تخصیص Planning.
- ایزوله‌سازی Session با نام `HIPPOSESSID`، Cookie Path مختص پوشه برنامه و فعال‌سازی Strict Mode.
- افزودن CSRF به فرم و پردازش Login.
- افزودن CSRF به مسیر ساخت مدیر اولیه، علاوه بر Setup Token.
- اصلاح `planning_expected_revision` برای پذیرش Revision صفر در اولین Update رکوردهای جدید.
- حذف لینک به فایل ناموجود `team_manager.php`.
- حذف ابزارهای موقت و ناامن نصب/عیب‌یابی:
  - `add.php`
  - `add_team.php`
  - `ajax_add_team.php`
  - `diag.php`
  - `final.php`
  - `fix.php`
  - `force_db.php`
  - `go.php`
- حذف `config.php` واقعی از Release.
- افزودن `config.localhost.sample.php` و راهنمای نصب فارسی.
- تقویت `.htaccess` برای جلوگیری از دسترسی مستقیم به Config، SQL، گزارش‌ها، Evidence و تست‌ها.

## سازگاری

- ساختار جدول‌های موجود تغییر نکرده است.
- Migration جدیدی اضافه نشده است.
- APIهای Planning و CRM بازنویسی نشده‌اند.
- AI Providerها و Promptها تغییر نکرده‌اند.
