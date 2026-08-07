# INSTALL — V05.3 Staging Validation

## 1. مشخصات محیط لازم
- Subdomain یا مسیر Staging مستقل از Production
- PHP سازگار با پروژه همراه `PDO` و `pdo_mysql`
- MySQL یا MariaDB مستقل
- HTTPS در صورت امکان
- Session و Log Path قابل نوشتن
- داده، رمز و API Key کاملاً آزمایشی

## 2. Backup قبل از نصب
1. از کل فایل‌های Staging یک Backup کامل و قابل Restore بگیرید.
2. با ابزار مناسب سرور از دیتابیس Staging Dump کامل بگیرید.
3. نسخه PHP، MySQL/MariaDB و Extensionهای فعال را ثبت کنید.
4. Restore آزمایشی Backup را پیش از تغییرات تأیید کنید.

## 3. نصب فایل‌ها
1. ZIP را در مسیر Staging استخراج کنید.
2. تأیید کنید `index.php` مستقیماً داخل پوشه اصلی است.
3. `config.sample.php` را فقط روی سرور به `config.php` تبدیل و با Credential آزمایشی تکمیل کنید.
4. `ai_config.sample.php` را فقط در صورت تست Provider و فقط با Key آزمایشی خارج از ZIP کپی کنید.
5. Config واقعی را داخل ZIP یا Repository قرار ندهید.

## 4. اجرای Migration
1. ابتدا دیتابیس V05.1 آزمایشی و Backup‌شده را آماده کنید.
2. `v05_2_data_boundary_migration.sql` را اجرا کنید.
3. خطا، تعداد حساب‌ها، تعداد Stateها، Roleها، Statusها و `password_hash`ها را بدون نمایش اطلاعات محرمانه مقایسه کنید.
4. Migration را بار دوم اجرا و Idempotency را بررسی کنید.
5. حساب‌های عملیاتی بدون Team Member را بررسی کنید.
6. در صورت خطا، به‌جای اجرای دست‌کاری‌شده SQL، دیتابیس آزمایشی را از Backup Restore کنید.

## 5. حساب‌های تست
حساب‌های Manager، دو Marketer، Center Call، Manager Viewer، Inactive و Locked را با رمزهای مستقل آزمایشی بسازید. برای نقش‌های عملیاتی Team Member معتبر و جداگانه تعریف کنید. رمزها را در Screenshot یا Log قرار ندهید.

## 6. تست چهار نقش
- Manager: کاربران، Permission، Customer Grant، Audit، Backup/Restore، Import و Full-State.
- Marketer A/B: مالکیت، Share، API مستقیم، Scoped Save و Export.
- Center Call: View، Call، Edit، Revoke و Interaction Ownership.
- Manager Viewer: Read-only، Redaction، ممنوعیت Save/Restore/User Management.

## 7. Cache Isolation
در یک Browser Profile مشترک Manager و سپس Marketer را تست کنید. پس از Logout و قطع شبکه نباید داده Manager نمایش داده شود. تغییر Role، Permission و Team Member باید Fingerprint و Cache قبلی را بی‌اعتبار کند.

## 8. Excel Import
XLSX و CSV را با Manager و Marketer مجاز تست کنید. تخصیص مالک، Duplicate Update، 409 Conflict، Payload Limit و Formula Injection Protection را بررسی کنید. Marketer بدون Permission و Center Call باید 403 دریافت کنند.

## 9. Backup/Restore
فقط با Manager دارای مجوزهای کامل اجرا کنید. قبل از Restore یک Backup جدید بسازید، Revision و Audit را بررسی کنید و تأیید کنید پاسخ Restore شامل Full-State خام نیست.

## 10. شرایط انتقال به Production
انتقال فقط زمانی مجاز است که Migration دو بار روی MariaDB/MySQL واقعی موفق باشد، تست چهار نقش و Session/CSRF/Conflict/Cache/Import/Backup/Restore PASS شود، Browser UI در سه اندازه بدون خطای مهم بررسی شود و هیچ نقص امنیتی Critical یا High باز باقی نماند.

## 11. Rollback
روش ترجیحی Rollback، Restore کامل Backup فایل و دیتابیس پیش از V05.3 است. `ROLLBACK-V05-2.sql` را فقط روی کپی Backup‌شده Staging و پس از بررسی حساب‌های ناسازگار اجرا کنید؛ اجرای مستقیم روی Production ممنوع است.

## وضعیت این بسته
در محیط ساخت فعلی MySQL/MariaDB و Driverهای آن موجود نبودند و Chromium توسط Policy محیط مسدود شد. بنابراین مراحل دیتابیس و Browser نقش‌ها باید در Staging واقعی سازمان تکرار شوند.
