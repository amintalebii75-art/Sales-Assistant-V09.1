# INSTALL — V06 Four-Week Team Planning

## هشدار اصلی
این نسخه بر پایه `Sales-Assistant-V05-3-Staging-Validation-Final` ساخته شده است. آن را مستقیم روی Production نصب نکنید. ابتدا نصب و تست کامل روی Staging مستقل الزامی است.

## پیش‌نیاز
- PHP سازگار با Baseline و افزونه PDO MySQL.
- MySQL یا MariaDB مستقل Staging.
- HTTPS در محیط Staging در صورت امکان.
- دسترسی نوشتن Session و Log.
- Config واقعی فقط روی سرور و خارج از ZIP نگهداری شود.

## ۱. Backup قبل از نصب
1. از کل پوشه فعلی برنامه Backup کامل بگیرید.
2. از دیتابیس Backup کامل و قابل Restore بگیرید.
3. نسخه PHP، MySQL/MariaDB و Extensionهای فعال را ثبت کنید.
4. صحت Restore Backup را پیش از Migration کنترل کنید.

## ۲. نصب فایل‌ها روی Staging
1. ZIP را خارج از مسیر Production استخراج کنید.
2. فقط پوشه `Sales-Assistant-V06-Four-Week-Team-Planning` را روی مسیر Staging قرار دهید.
3. فایل واقعی `config.php` و `ai_config.php` سرور را حفظ کنید؛ فایل Sample را جایگزین Config واقعی نکنید.
4. Permission فایل‌ها و مسیر Session/Log را کنترل کنید.

## ۳. Migration
ابتدا روی دیتابیس Staging اجرا کنید:

```sql
SOURCE v06_four_week_planning_migration.sql;
```

سپس همان Migration را بار دوم اجرا کنید و Idempotency، نبود Permission تکراری و حفظ کاربران، Customer Access، Audit، Revision و Backupها را بررسی کنید.

Migration نباید برنامه، Task، حساب یا رمز فرضی ایجاد کند.

## ۴. تست اجباری پس از نصب
- Manager: Create/Edit/Publish/Close/Archive/Copy Plan، چهار Week، Task و Assignment.
- Marketer: فقط Plan و Assignment خود، Update پیشرفت و جلوگیری از دسترسی دیگران.
- Center Call: فقط وظایف خود و Update محدود.
- Manager Viewer: فقط Summary غیرحساس و تمام Writeها با 403.
- Override Permission=false و تغییر Fingerprint در Session فعال.
- CSRF معتبر، غایب، اشتباه و متعلق به Session دیگر.
- وظیفه عمومی: Preview، حذف Inactive/Locked/بدون Team Member، Assignment مستقل.
- Conflict با دو Session برای Plan، Task و Assignment و پاسخ 409.
- اتصال «وظایف من» و صفحه برنامه بدون داده تکراری.
- Desktop 1440×900، Tablet 768×1024 و Mobile 390×844.
- Audit Log، Secret scan و نبود خطای Console/SQL.

## ۵. Rollback
روش ترجیحی، Restore کامل Backup فایل و دیتابیس قبل از V06 است.

`ROLLBACK-V06.sql` فقط در Staging و پس از Backup اجرا شود. اگر داده‌ای در جداول برنامه وجود داشته باشد، Rollback متوقف می‌شود و حذف بی‌هشدار انجام نمی‌دهد. برای حذف داده واقعی باید تصمیم صریح مدیر پروژه و Backup قابل Restore وجود داشته باشد.

## ۶. شرایط انتقال به Production
انتقال فقط زمانی مجاز است که موارد زیر واقعاً PASS شوند:

- Migration اول و دوم روی نسخه واقعی MySQL/MariaDB.
- Rollback روی کپی Backupشده.
- تست چهار نقش.
- وظیفه عمومی و فیلتر کاربران واجد شرایط.
- Conflict واقعی با دو Session.
- Cache/Fingerprint بعد از تغییر Role و Permission.
- اتصال دوطرفه صفحه برنامه و وظایف من.
- Backup/Restore قبلی و Excel Import بدون Regression.
- تست کامل Browser در سه اندازه و نبود Console Error.

تا آن زمان وضعیت Release برای Production، **NOT APPROVED** است.
