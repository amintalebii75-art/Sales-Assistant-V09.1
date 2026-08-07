# INSTALL — Sales Assistant V05.2

## 1. مبنا و Backup الزامی
1. این نسخه بر اساس V05.1 ساخته شده و باید در مسیر مستقل نصب شود؛ V05.1 را Overwrite نکنید.
2. قبل از نصب، از تمام فایل‌های نسخه فعال Backup کامل خارج از `public_html` تهیه کنید.
3. از دیتابیس MySQL/MariaDB یک Dump کامل شامل State، Backupها، کاربران، RBAC و Audit بگیرید.
4. فایل واقعی `config.php` و فایل واقعی تنظیمات AI را جداگانه و امن نگهداری کنید؛ این فایل‌ها داخل ZIP انتشار نیستند.
5. Migration را بدون Backup معتبر روی Production اجرا نکنید.

## 2. نصب ابتدا روی Staging
1. ZIP را Extract کنید؛ باید فقط پوشه `Sales-Assistant-V05-2-RBAC-Data-Boundary-Final` در سطح اول وجود داشته باشد.
2. بررسی کنید `index.php` مستقیماً داخل همین پوشه است و پوشه هم‌نام تو‌در‌تو یا پوشه دارای `(1)` وجود ندارد.
3. محتوای Release را در مسیر Staging مستقل قرار دهید.
4. فایل واقعی `config.php` و تنظیمات AI سرور را از محیط امن Staging اضافه کنید؛ فایل‌های Sample را جایگزین تنظیمات واقعی نکنید.
5. Permission فایل‌ها، HTTPS، Cookie و Session را مطابق تنظیمات سالم V05.1 حفظ کنید.

## 3. Migration روی Staging
1. تأیید کنید Migration مربوط به V05.1 قبلاً اجرا شده است.
2. `v05_2_data_boundary_migration.sql` را روی دیتابیس Staging اجرا کنید.
3. همان Migration را بار دوم اجرا کنید تا Idempotency و نبود تغییر مخرب بررسی شود.
4. حساب‌های `marketer` و `center_call` با `rbac_review_required=1` یا `status=inactive` را بازبینی کنید.
5. برای هر حساب عملیاتی Team Member معتبر و یکتا متصل کنید؛ سپس Review را صفر و حساب را فقط با تأیید مدیر فعال کنید.

> Migration واقعی MariaDB/MySQL در محیط ساخت این Release اجرا نشده است؛ تست Staging الزامی است.

## 4. تست الزامی چهار نقش پس از نصب
- **Manager:** Full-State View/Save، Backup، Restore، Permission و Team Member.
- **Marketer:** مالکیت و Share مشتری، Interaction مجاز و عدم دسترسی سازمانی حساس.
- **Call Center:** سطح View بدون ثبت و سطح Call با فیلدهای محدود؛ بدون تغییر Stage/Source/Assignee و بدون ساخت سفارش.
- **Manager Viewer:** مشاهده مجاز بدون Full-State Save، Restore یا عملیات مدیریتی فاقد Permission.

## 5. تست Full-State، Revision و Cache
1. State را Load و Context، Scope، Revision و Fingerprint را ثبت کنید.
2. Permission، Role یا Team Member را در Session مدیر تغییر دهید؛ Context قبلی باید رد شود.
3. مشتری پنهان، Formula و Settings را قبل و بعد Scoped Save مقایسه کنید.
4. Save بدون تغییر نباید Revision را افزایش دهد.
5. Conflict هم‌زمان باید پاسخ 409 موجود را حفظ کند.
6. Cache قدیمی پس از تغییر Team Member یا Permission نباید نمایش داده شود.

## 6. تست Backup و Restore
1. Marketer با Permission Override مربوط به Backup/Restore باید 403 بگیرد.
2. Manager بدون `state.view_full` نباید Backup را مشاهده کند.
3. Manager بدون `state.save_full` نباید Restore انجام دهد.
4. Restore موفق نباید Full-State خام در Response برگرداند؛ Frontend باید State مجاز را مجدداً دریافت کند.
5. Backupهای قبل و بعد Restore و Revision نهایی را بررسی کنید.

## 7. Rollback
1. سرویس را در حالت نگهداری قرار دهید.
2. فایل‌ها را از Backup نسخه V05.1 بازگردانید.
3. در صورت نیاز `ROLLBACK-V05-2.sql` را اجرا کنید.
4. حساب‌هایی که Migration غیرفعال کرده است خودکار فعال نمی‌شوند؛ مالکیت و Team Member آن‌ها را دستی بررسی کنید.
5. برای Rollback کامل داده، Dump دیتابیس قبل از نصب را Restore کنید.

## 8. کنترل قبل از Production
- چهار نقش بالا روی Staging واقعی تست شده باشند.
- Migration دو بار بدون خطا روی کپی دیتابیس اجرا شده باشد.
- Login/Logout، Session هم‌زمان، 409 Revision، Backup/Restore و Cache در Chrome واقعی بررسی شده باشند.
- هیچ `config.php` واقعی، کلید API، Token، Session یا رمز دیتابیس داخل ZIP نباشد.
- ورود امن CSV/XLSX و AI Providerهای موجود بدون تغییر ناخواسته کار کنند.
