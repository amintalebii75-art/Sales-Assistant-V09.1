# راهنمای نصب روی localhost — V06.0.3 Localhost Security Fix

## پیش‌نیاز

- XAMPP یا Laragon با PHP 8.1 یا جدیدتر
- Apache
- MySQL یا MariaDB
- افزونه‌های PHP: `pdo_mysql`, `mbstring`, `json`, `session`

## ۱. کپی پروژه

پوشه زیر را داخل `htdocs` قرار دهید:

```text
Sales-Assistant-V06-0-3-Localhost-Security-Fix
```

نمونه مسیر XAMPP:

```text
C:\xampp\htdocs\Sales-Assistant-V06-0-3-Localhost-Security-Fix
```

## ۲. ساخت دیتابیس

در phpMyAdmin یک دیتابیس با مشخصات زیر بسازید:

```text
نام: granule_sales_local
Collation: utf8mb4_unicode_ci
```

برای نصب جدید، فایل زیر را Import کنید:

```text
schema.sql
```

`schema.sql` جداول اصلی V05 و Planning V06 را برای نصب جدید ایجاد می‌کند. Migrationهای ارتقا فقط برای دیتابیس نسخه‌های قبلی هستند و نباید بعد از `schema.sql` بدون نیاز اجرا شوند.

## ۳. ساخت config.php

فایل زیر را کپی کنید:

```text
config.localhost.sample.php
```

نام نسخه کپی‌شده را به این تغییر دهید:

```text
config.php
```

مقادیر پیش‌فرض XAMPP معمولاً چنین هستند:

```php
'db_host' => 'localhost',
'db_name' => 'granule_sales_local',
'db_user' => 'root',
'db_pass' => '',
```

مقدار `setup_token` را حتماً با یک رشته تصادفی و طولانی جایگزین کنید. نمونه تولید در PowerShell:

```powershell
-join ((48..57)+(65..90)+(97..122) | Get-Random -Count 40 | ForEach-Object {[char]$_})
```

## ۴. ساخت مدیر اولیه

آدرس زیر را باز کنید:

```text
http://localhost/Sales-Assistant-V06-0-3-Localhost-Security-Fix/create_admin.php
```

Setup Token، نام مدیر، نام کاربری انگلیسی و رمز حداقل ۱۰ کاراکتری را وارد کنید.

پس از ساخت مدیر، وارد شوید:

```text
http://localhost/Sales-Assistant-V06-0-3-Localhost-Security-Fix/login.php
```

صفحه `create_admin.php` پس از وجود اولین کاربر، درخواست جدید را با 403 رد می‌کند. برای کاهش سطح حمله می‌توانید پس از نصب، فایل را از پوشه وب خارج کنید.

## ۵. افزودن عضو تیم و حساب عملیاتی

1. با نقش Manager وارد شوید.
2. به «کاربران و دسترسی‌ها» بروید.
3. ابتدا «افزودن عضو تیم» را بزنید.
4. سپس حساب Marketer یا Center Call را بسازید و به عضو تیم معتبر متصل کنید.

ایجاد عضو تیم در این نسخه فقط از API رسمی انجام می‌شود و نیازمند موارد زیر است:

- Session معتبر
- نقش واقعی Manager
- Permission `permissions.manage`
- CSRF معتبر
- Revision معتبر
- ثبت Backup و Audit

## ۶. بررسی اولیه

این مسیرها را بررسی کنید:

```text
/login.php              باید باز شود
/index.php              بدون Login باید به login.php منتقل شود
/planning.php           بدون Login باید به login.php منتقل شود
/planning_api.php       بدون Login باید HTTP 401 بدهد
/users_api.php          بدون Login باید HTTP 401 بدهد
```

## نکات امنیتی

- `config.php` را برای دیگران ارسال نکنید.
- فایل واقعی `ai_config.php` یا API Key را داخل ZIP قرار ندهید.
- فایل‌های حذف‌شده مانند `diag.php`, `fix.php`, `force_db.php` و `add.php` را به پروژه برنگردانید؛ آن‌ها Login/RBAC/CSRF یا Revision را دور می‌زدند.
- پوشه‌های `tests` و `TEST-EVIDENCE` از طریق `.htaccess` برای مرورگر مسدود شده‌اند.
- برای Production حتماً HTTPS و رمز دیتابیس اختصاصی استفاده کنید.

## وضعیت تست این Release

- PHP Syntax: PASS — 27/27
- JavaScript Syntax: PASS — 14/14
- V05.2 Policy Tests: PASS — 27/27
- V06 Policy Tests: PASS — 47/47
- V06.0.3 Security Regression: PASS — 20/20
- HTTP بدون Session: PASS
- اجرای Migration و تست چهار نقش روی MariaDB/MySQL واقعی در محیط ساخت این بسته: `BLOCKED`، چون سرویس دیتابیس و `pdo_mysql` در محیط ساخت در دسترس نبود. Browser responsive نیز به‌علت Policy محیط `BLOCKED` ماند.
