# CHANGELOG — Sales Assistant V05.2

## مبنای نسخه
این نسخه بر اساس `Sales-Assistant-V05-1-RBAC-Security-Final` ساخته شده است. V05.1 Overwrite نشده و V05.2 به‌صورت Release مستقل ارائه می‌شود.

## اصلاحات امنیتی V05.2

### Full-State Save
- Full-State Save فقط برای نقش واقعی `manager` مجاز است.
- هر دو Permission شامل `state.view_full` و `state.save_full` الزامی هستند.
- Scope سازمانی، Revision و Permission Fingerprint دریافت‌شده هنگام Load در Save کنترل می‌شوند.
- Conflict موجود با پاسخ 409 حفظ شده و Save بدون تغییر نباید Revision را افزایش دهد.

### Field-Level Access
- خروجی Customer و Interaction با Allowlist مستقل برای سطوح `view`، `call` و `edit` پالایش می‌شود.
- فیلدهای خصوصی، مالی، مدیریتی، سفارش، Fulfillment و فیلدهای ناشناخته برای سطح غیرمجاز ارسال نمی‌شوند.
- سطح `call` فقط فیلدهای لازم تماس و پیگیری را دریافت یا تغییر می‌دهد.

### Customer Access به‌عنوان سقف قطعی
- مقدار `customer_access` سقف نهایی دسترسی است.
- Permissionهای عمومی مانند `customers.edit_all` نمی‌توانند سطح `call` را به `edit` ارتقا دهند.
- در سطح Call تغییر Stage، Source، Assignee، وضعیت فروش و داده مالی مسدود است.

### Interaction Ownership و Schema
- مالک Interaction موجود هنگام Save حفظ می‌شود.
- مالک Interaction جدید فقط از Session احراز‌شده سمت سرور تعیین می‌شود.
- `customerId` و `memberId` در ویرایش قابل انتقال نیستند.
- Payload نقش‌محور است و سطح Call نمی‌تواند سفارش، خرید یا Fulfillment جعلی ایجاد کند.

### Team Member Requirement
- اتصال یا تغییر Team Member فقط برای Manager واقعی دارای `permissions.manage` مجاز است.
- کاربر غیرمدیر دارای `users.manage` حق تغییر Team Member، Role یا Permission حساس را ندارد.
- حساب‌های عملیاتی `marketer` و `center_call` بدون Team Member معتبر مسدود می‌شوند.
- سیاست Migration: حساب عملیاتی فاقد Team Member با `status=inactive` و `rbac_review_required=1` برای بازبینی مدیر علامت‌گذاری می‌شود.
- Team Member در Permission Fingerprint و Cache Scope اثر دارد.

### Backup و Restore
- Backup نیازمند Manager واقعی، `backups.view` و `state.view_full` است.
- Restore نیازمند Manager واقعی، `backups.restore`، `state.view_full`، `state.save_full`، CSRF و Revision معتبر است.
- پاسخ Restore فقط Metadata/Revision لازم را برمی‌گرداند و Full-State خام افشا نمی‌شود.

## Migration و Rollback
- Migration جدید: `v05_2_data_boundary_migration.sql`
- Rollback: `ROLLBACK-V05-2.sql`
- Migration برای اجرای Idempotent طراحی شده است.
- اجرای واقعی روی MariaDB/MySQL در محیط ساخت انجام نشده و باید ابتدا روی Staging اجرا شود.

## فایل‌های امنیتی تغییرکرده در توسعه V05.2
- `api.php`, `auth.php`, `login.php`, `permissions.php`, `users_api.php`, `users.php`, `index.php`, `schema.sql`
- `assets/js/excel-import.js`, `assets/js/users-rbac.js`, `assets/js/v04-pages.js`, `assets/js/v05-app.js`
- `account_review.php`, `v05_2_data_boundary_migration.sql`, `ROLLBACK-V05-2.sql`
- `tests/v05_2_policy_tests.php` و `TEST-EVIDENCE/*`

## اصلاح Release نهایی
در این اصلاح فقط ساختار بسته، مستندات، گزارش تست و معرفی شواهد خودکار هماهنگ شده‌اند. منطق اصلی امنیتی V05.2، Login/Session، Backup/Restore، Excel Import امن، AI Providerها و Promptهای AI بازنویسی یا توسعه داده نشده‌اند.

## خارج از دامنه
V06، برنامه ماهانه چهارهفته‌ای، وظیفه عمومی، دستیار اولویت‌بندی، اتصال جدید ArvanCloud، مهاجرت کامل مشتریان از JSON و هر قابلیت جدید دیگر شروع نشده‌اند.
