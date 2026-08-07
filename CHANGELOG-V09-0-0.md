# V09.0.0 — Host Pilot / PWA Complete

## هدف
یک Release واحد برای نصب روی localhost یا هاست اشتراکی و شروع پایلوت واقعی یک‌ماهه با دو کاربر؛ بدون ادامه تحویل‌های تکه‌ای.

## تغییرات قطعی
- رفع نهایی ارتقای ضمنی `Customer Access=view` به `call` برای نقش مرکز تماس.
- مرکز تماس فقط با Grant صریح `call` می‌تواند یک یا چند نتیجه مذاکره ثبت کند.
- UI فرم مذاکره برای مشتری `view` در تمام نقش‌ها Read-only می‌شود.
- افزودن PWA قابل نصب روی Android با Manifest، Service Worker و Icon.
- Service Worker هیچ PHP، API یا داده حساب را Cache نمی‌کند؛ در قطع اتصال فقط صفحه امن Offline نمایش داده می‌شود.
- افزودن Cache-Control و Headerهای امنیتی در PHP و `.htaccess`.
- افزودن صفحه مدیرمحور `deployment_check.php` برای بررسی PHP، HTTPS، DB، جداول، ستون‌ها، State و PWA.
- افزودن `pilot_issues.php` برای ثبت ایرادهای یک‌ماهه در مرورگر و خروجی JSON/CSV؛ داده به دیتابیس CRM نوشته نمی‌شود.
- افزودن نمونه Config مخصوص Production و پشتیبانی `db_port`/`db_charset`.
- به‌روزرسانی نسخه ظاهری و آزمون پایلوت به V09.

## دیتابیس
- Migration جدید ندارد.
- دیتابیس سازگار با V08/V06.0.9 بدون تغییر استفاده می‌شود.

## محدودیت صادقانه
- Runtime واقعی هاست، چهار نقش، Restore، دو Session و Responsive هنوز باید توسط کاربر در محیط واقعی اجرا شود.
