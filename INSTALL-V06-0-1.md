# INSTALL — V06.0.1 Planning Security Fix

## هشدار
این نسخه یک اصلاح امنیتی برای V06 است و نباید مستقیم روی Production نصب شود. نصب ابتدا روی Staging مستقل با Backup کامل فایل و دیتابیس الزامی است.

## ۱. Backup
1. از تمام فایل‌های نسخه فعلی Backup کامل بگیرید.
2. از دیتابیس MySQL/MariaDB Backup کامل و قابل Restore بگیرید.
3. Config واقعی سرور را خارج از ZIP حفظ کنید.
4. نسخه PHP، دیتابیس و Extensionهای فعال را ثبت کنید.

## ۲. نصب فایل‌ها
1. ZIP را روی مسیر مستقل Staging استخراج کنید.
2. پوشه `Sales-Assistant-V06-0-1-Planning-Security-Fix` را جایگزین نسخه آزمایشی کنید.
3. `config.php` و `ai_config.php` واقعی سرور را از Backup محیط بازگردانید؛ فایل Sample را به Config واقعی تبدیل نکنید.
4. Permission مسیر Session و Log را بررسی کنید.

## ۳. Migration
فایل زیر را روی دیتابیس Staging اجرا کنید:

`v06_four_week_planning_migration.sql`

Migration را بار دوم نیز اجرا و Idempotency را کنترل کنید. اجرای مجدد باید جدول `monthly_assignment_history` را بدون حذف یا تغییر داده‌های V05/V06 ایجاد یا حفظ کند.

## ۴. تست اجباری Reactivate
1. با Manager یک Assignment را لغو کنید.
2. با همان Marketer یا Center Call صفحه برنامه و «وظایف من» را باز کنید؛ Assignment باید فقط خواندنی باشد.
3. Request مستقیم `update_my_assignment` برای تغییر آن به pending، in_progress، blocked، needs_decision یا completed ارسال کنید؛ پاسخ باید 409 یا 403 باشد.
4. با Manager دارای `plans.assign` عملیات صریح Reactivate را همراه `expected_revision` و دلیل اجرا کنید.
5. Revision قدیمی باید 409 بدهد.
6. رویداد `plan.assignment_reactivate` و Snapshot جدول `monthly_assignment_history` را بررسی کنید.
7. مطمئن شوید Note، Blocked Reason و زمان‌های قبلی در Snapshot قابل بازیابی‌اند.

## ۵. تست Progress و Copy Plan
- یک کاربر با یک Assignment دارای پیشرفت ۱۰۰ و کاربر دیگر با ۹ Assignment دارای پیشرفت صفر بسازید؛ Progress کل باید ۱۰٪ باشد.
- Plan دارای Task فعال، لغوشده و آرشیوشده را کپی کنید؛ فقط Task فعال باید در ماه جدید ساخته شود.
- هیچ Assignment قبلی نباید کپی شود.
- `due_date` و `start_date`/`end_date` Weekهای جدید باید NULL باشند.

## ۶. Regression
تست Manager، Marketer، Center Call، Manager Viewer، Permission Override=false، CSRF، Conflict، وظیفه عمومی، اتصال «وظایف من»، Audit، Backup/Restore و Excel Import را دوباره اجرا کنید.

## ۷. Rollback
روش ترجیحی Restore کامل Backup قبل از V06.0.1 است. `ROLLBACK-V06.sql` در صورت وجود هرگونه داده Planning متوقف می‌شود و جدول تاریخچه را نیز در Guard و ترتیب حذف امن لحاظ می‌کند.

## شرایط انتقال به Production
فقط پس از PASS واقعی Migration اول و دوم، Endpoint Runtime cancelled/reactivate، تاریخچه، Weighted Progress، Copy Plan، چهار نقش و Browser UI روی Staging، انتقال به Production بررسی شود.
