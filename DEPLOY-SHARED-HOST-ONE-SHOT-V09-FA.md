# نصب یک‌مرحله‌ای V09 روی هاست اشتراکی

## روش پیشنهادی برای حفظ نسخه قبلی
1. از فایل‌های سایت و دیتابیس فعلی Backup کامل بگیر.
2. V09 را در یک پوشه یا Subdomain مستقل مانند `pilot` نصب کن؛ نسخه قبلی را حذف نکن.
3. ZIP را Extract کن تا `index.php` مستقیماً داخل پوشه اصلی V09 باشد.
4. از `config.production.sample.php` یک کپی با نام `config.php` بساز و اطلاعات MySQL cPanel را وارد کن.
5. فایل‌های فونت مجاز خودت را فقط روی هاست در `assets/fonts` کپی کن؛ داخل ZIP مرجع قرار نده.
6. چون V09 Migration جدید ندارد، برای دیتابیس سازگار V08 هیچ SQL اجرا نکن.
7. با حساب مدیر وارد شو و `deployment_check.php` را باز کن.
8. ابتدا Backup سرور را بررسی کن و بعد تست چهار نقش را انجام بده.
9. دامنه/Subdomain باید HTTPS داشته باشد؛ بعد از بازکردن سایت در Chrome Android، دکمه «نصب روی گوشی» ظاهر می‌شود.

## اتصال PWA به سرور
PWA همان سایت روی هاست است. تمام اطلاعات از مسیر زیر خوانده/ثبت می‌شوند:
`گوشی → HTTPS → PHP روی هاست → MariaDB/MySQL`

هیچ دیتابیس جداگانه‌ای داخل گوشی ساخته نمی‌شود. Service Worker فقط CSS/JS/Icon عمومی را Cache می‌کند و PHP/API/اطلاعات حساب را Cache نمی‌کند.

## لینک‌های مهم بعد از نصب
- ورود: `https://DOMAIN/PATH/login.php`
- بررسی هاست: `https://DOMAIN/PATH/deployment_check.php`
- آزمون پایلوت: `https://DOMAIN/PATH/pilot_test.php`
- دفترچه ایراد: `https://DOMAIN/PATH/pilot_issues.php`

## ممنوع
- حذف نسخه قبلی پیش از پایان پایلوت
- اجرای Migration بدون Backup
- قرار دادن `config.php` واقعی در ZIP یا ارسال آن در چت
- تست Restore روی داده واقعی بدون Backup جدا
