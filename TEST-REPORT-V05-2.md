# TEST REPORT — Sales Assistant V05.2

## دامنه گزارش
این گزارش برای Release نهایی `Sales-Assistant-V05-2-RBAC-Data-Boundary-Final` تهیه شده است. در اصلاح این Release منطق امنیتی V05.2 بازنویسی نشده و فقط بسته‌بندی، مستندات و نحوه معرفی شواهد اصلاح شده‌اند.

## نتایج واقعی اجراشده
- Policy/Authorization: **27 PASS / 0 FAIL**
- نوع Policy test: تست‌های Policy و Static؛ **بدون اتصال به دیتابیس واقعی**
- PHP Syntax: **21 PASS / 0 FAIL** با PHP CLI 8.4.16
- JavaScript Syntax: **12 PASS / 0 FAIL** با Node.js `--check`
- Inline JavaScript اصلی `index.php`: **PASS**؛ بلوک اصلی بدون تغییر استخراج و Syntax آن بررسی شد.
- Bootstrap Inline JavaScript: Wrapper با جایگزینی خروجی Server-side JSON با `{}` بررسی شد؛ این مورد Browser runtime محسوب نمی‌شود.
- Application logic integrity: فایل‌های اجرایی و امنیتی نسبت به بسته ورودی این Release تغییر نکرده‌اند.

## پوشش Policy/Authorization
- Full-State Save فقط برای Manager واقعی با `state.view_full` و `state.save_full`
- کنترل Scope سازمانی، Revision/409 و Permission Fingerprint
- Field-Level Access مشتری و Interaction در سطوح `view`، `call` و `edit`
- جلوگیری از دورزدن سقف `customer_access` با Permission عمومی
- حفظ مالک Interaction موجود و تعیین مالک Interaction جدید از Session
- محدودیت Team Member و مسدودسازی حساب عملیاتی بدون Team Member معتبر
- محدودیت Backup/Restore و عدم بازگشت Full-State خام بعد از Restore
- اتصال Cache Scope و Permission Fingerprint به Team Member

## TEST-EVIDENCE
- فایل‌های PNG داخل `TEST-EVIDENCE/` فقط **Automated Test Evidence Summary** هستند.
- این تصاویر Screenshot واقعی Browser، Production، Staging یا Session زنده نیستند.
- نام فایل‌های PNG فقط سناریوی Policy را مشخص می‌کند و به معنی اجرای Browser نیست.
- شواهد متنی شامل خروجی Policy، PHP lint، JavaScript syntax، Inline syntax و Secret scan است.

## تست‌های اجرا‌نشده
- Browser runtime test: **NOT RUN** — Chromium واقعی در محیط ساخت قابل اجرا نبود.
- Migration on real MariaDB/MySQL: **NOT RUN** — دیتابیس Staging واقعی در دسترس نبود.
- End-to-End روی Host واقعی، Sessionهای هم‌زمان و قطع شبکه: **NOT RUN**.
- برای هیچ‌یک از موارد اجرا‌نشده وضعیت PASS اعلام نشده است.

## بررسی Archive و SHA-256
- ساختار Archive پس از ساخت ZIP و بازکردن مجدد آن بررسی شده است.
- فقط یک پوشه سطح اول مجاز است و `index.php` باید مستقیماً داخل همان پوشه باشد.
- پوشه دارای `(1)` و پوشه هم‌نام تو‌در‌تو نباید وجود داشته باشد.
- SHA-256 فقط از فایل ZIP نهایی و پس از پایان ساخت محاسبه می‌شود و در پاسخ تحویل ثبت می‌گردد؛ درج Hash داخل خود ZIP به‌دلیل تغییر دادن همان Hash انجام نمی‌شود.
