# TEST REPORT — V06 Four-Week Team Planning

## نتیجه کلان
**وضعیت انتقال مستقیم به Production: NOT APPROVED**

علت: Migration واقعی MySQL/MariaDB، تست چهار نقش احراز‌شده، وظیفه عمومی با کاربران واقعی آزمایشی، Conflict همزمان با دو Session و تست کامل Browser UI در این محیط اجرا نشده‌اند.

## نتایج اجراشده

| حوزه | وضعیت | نتیجه واقعی |
|---|---|---|
| V06 Policy/Static | PASS | 35 PASS / 0 FAIL |
| V05.2 Security Regression | PASS | 27 PASS / 0 FAIL |
| PHP Syntax | PASS | 24 PASS / 0 FAIL |
| JavaScript Syntax | PASS | 14 PASS / 0 FAIL |
| Inline JavaScript Syntax | PASS | 7 PASS / 0 FAIL |
| Asset path static check | PASS | 39 PASS / 0 FAIL |
| Planning assets via PHP HTTP | PASS | 3 فایل، HTTP 200 |
| Login PHP Runtime | PASS | `login.php` پاسخ 200 |
| Protected planning page | PASS | بدون Session پاسخ 302 به Login |
| Planning API session guard | PASS | بدون Session پاسخ 401 |
| Secret/runtime-config scan | PASS | 0 فایل Config واقعی، 0 الگوی Secret |
| Baseline executable comparison | PASS | هیچ فایل Baseline حذف نشده؛ تغییرات محدود به V06 و نقاط اتصال مستند |

## Migration و Rollback

| تست | وضعیت | توضیح |
|---|---|---|
| اجرای اول Migration روی MySQL/MariaDB | NOT RUN | سرویس/Client و `pdo_mysql`/`mysqli` موجود نبود |
| اجرای دوم و Idempotency واقعی | NOT RUN | اجرای اول ممکن نبود |
| ساخت چهار جدول و Index/FK | PASS | فقط بررسی Static فایل SQL |
| عدم ساخت داده یا حساب فرضی | PASS | بررسی Static |
| Permission تکراری | PASS | بررسی Static `ON DUPLICATE KEY`؛ اجرای واقعی NOT RUN |
| Rollback روی دیتابیس Backupشده | NOT RUN | دیتابیس واقعی موجود نبود |
| Guard حذف داده در Rollback | PASS | بررسی Static؛ وجود داده باعث توقف می‌شود |

## تست نقش‌ها

| نقش | وضعیت | توضیح |
|---|---|---|
| Manager | NOT RUN | حساب و دیتابیس واقعی آزمایشی موجود نبود |
| Marketer | NOT RUN | حساب و دیتابیس واقعی آزمایشی موجود نبود |
| Center Call | NOT RUN | حساب و دیتابیس واقعی آزمایشی موجود نبود |
| Manager Viewer | NOT RUN | حساب و دیتابیس واقعی آزمایشی موجود نبود |

Policy پیش‌فرض نقش‌ها، Manager-only operations، Summary-only Viewer، Override=false، حساب Inactive و الزام Team Member در 35 تست Static بررسی و PASS شده‌اند؛ این نتیجه جایگزین تست Runtime نیست.

## وظیفه عمومی
- Recipient Preview و فیلتر کاربران واجد شرایط: **PASS — Static**
- Assignment مستقل و Unique برای هر Task/User: **PASS — Static**
- Transaction و جلوگیری از Assignment تکراری: **PASS — Static**
- اجرای واقعی با Active/Inactive/Locked/بدون Team Member: **NOT RUN**
- تکمیل یک نفر بدون تغییر دیگران در دیتابیس: **NOT RUN**

## Conflict
- ستون Revision مستقل برای Plan/Week/Task/Assignment: **PASS — Static**
- شرط Revision در Update و پاسخ HTTP 409: **PASS — Static**
- دو Manager یا دو Session همزمان و حفظ تغییر نشست دوم: **NOT RUN**
- Conflict واقعی Assignment و جلوگیری از Lost Update: **NOT RUN**

## اتصال به وظایف فعلی
- منبع `monthly_plan` و Badge: **PASS — Static**
- عدم کپی در State JSON قدیمی: **PASS — Static**
- Update از Endpoint واحد Assignment: **PASS — Static**
- Sync واقعی دو صفحه با حساب احراز‌شده: **NOT RUN**
- حفظ وظایف قدیمی: **PASS — بررسی عدم Migration/حذف Static؛ Runtime NOT RUN**

## Browser UI
Chromium واقعی فراخوانی شد، اما در Runtime کنترل‌شده تکمیل نشد و با timeout/محدودیت محیط متوقف شد. وضعیت تست Desktop، Tablet، Mobile، Modal، Drawer، Toast، Overflow و Console: **BLOCKED**. هیچ Screenshot ساختگی تولید نشده است.

## نقص‌ها
هیچ FAIL یا نقص محصولی قابل بازتولید در تست‌های اجراشده ثبت نشد. نبود FAIL به معنی تأیید Production نیست؛ موارد حیاتی بالا هنوز NOT RUN یا BLOCKED هستند.
