# ماتریس انطباق پرامپت مادر — V09.0.0

تعریف وضعیت‌ها:
- **پیاده‌سازی‌شده:** کد/Policy/UI موجود و Static Test مرتبط PASS است.
- **آماده Runtime:** ساخت انجام شده اما رفتار نهایی باید روی DB و حساب واقعی مشاهده شود.
- **خارج از Scope فعلی:** در پرامپت مادر عمداً تا دستور صریح ممنوع یا آینده تعریف شده است.

| حوزه پرامپت مادر | وضعیت V09 | شاهد اصلی |
|---|---|---|
| PHP/PDO/MySQL، Session و Prepared Statement | پیاده‌سازی‌شده | `db.php`, `auth.php`, APIها |
| چهار نقش Manager/Marketer/Center Call/Manager Viewer | پیاده‌سازی‌شده؛ آماده Runtime | `permissions.php`, `manager.php`, Planning API |
| Permission Override true/false و Fingerprint | پیاده‌سازی‌شده | Policy Tests |
| Full-State View/Save با Manager واقعی | پیاده‌سازی‌شده | `api.php`, `permissions.php` |
| Scoped Save و حفظ داده پنهان | پیاده‌سازی‌شده | V05.2 Policy Suite |
| Revision/HTTP 409 در CRM | پیاده‌سازی‌شده؛ آماده Runtime دو Session | `api.php` |
| Customer Field-Level View/Call/Edit | پیاده‌سازی‌شده | Allowlistهای `permissions.php` |
| Customer Access به‌عنوان سقف قطعی | پیاده‌سازی‌شده در V09 | View دیگر برای Center Call به Call ارتقا نمی‌یابد |
| Interaction Field-Level و مالکیت از Session | پیاده‌سازی‌شده | Sanitizer/Scoped Merge |
| انتخاب چند نتیجه مذاکره | پیاده‌سازی‌شده و قبلاً در localhost مشاهده شده | فرم مذاکره + V06.0.9 |
| جلوگیری از Purchase/Order توسط Center Call | پیاده‌سازی‌شده | نتیجه‌های Call و Field Reject |
| ارجاع نتیجه Center Call به بازاریاب | پیاده‌سازی‌شده؛ آماده Runtime هاست | Handoff Task |
| Team Member معتبر و RBAC Review | پیاده‌سازی‌شده | `auth.php`, `users_api.php` |
| Login Rotation، Lock، Logout و Cache Isolation | پیاده‌سازی‌شده؛ آماده Runtime | `auth.php`, `cache-scope.js` |
| CSRF تمام Writeها | پیاده‌سازی‌شده | APIها و Login |
| Audit بدون Secret | پیاده‌سازی‌شده | `hippo_audit` |
| Backup/Restore Manager-only و بدون State خام | پیاده‌سازی‌شده؛ آماده Runtime | `api.php` |
| Excel/CSV Import امن | پیاده‌سازی‌شده؛ آماده Runtime | `excel-import.js`, Scoped Save |
| برنامه ماهانه چهار هفته‌ای | پیاده‌سازی‌شده؛ بخشی قبلاً تست شده | جداول/Planning API/UI |
| Revoke/Reactivate/History | پیاده‌سازی‌شده؛ آماده Runtime کامل | Planning API/Policy Tests |
| Weighted Progress | پیاده‌سازی‌شده | V06 Policy Tests |
| Copy Month بدون Assignment قدیمی | پیاده‌سازی‌شده | V06 Policy Tests |
| Manager Viewer Summary-only | پیاده‌سازی‌شده؛ آماده Runtime | `manager.php`, Planning Summary |
| RTL/Responsive/Touch Targets/States | پیاده‌سازی‌شده؛ آماده تست واقعی موبایل | V07/V08 UI |
| Shared-host Deployment Check | اضافه و پیاده‌سازی‌شده در V09 | `deployment_check.php` |
| نصب Android بدون APK | اضافه و پیاده‌سازی‌شده در V09 | PWA/Manifest/Service Worker |
| ثبت ایراد پایلوت | اضافه و پیاده‌سازی‌شده در V09 | `pilot_issues.php` |
| AI جدید، WhatsApp، Email، Multi-tenant | خارج از Scope فعلی | طبق بخش ۳۲ و ۳۳ پرامپت مادر |

## نتیجه
بر اساس Scope فعلی، مورد ساخت‌نشده‌ای که مانع شروع پایلوت باشد باقی نمانده است. موارد باقی‌مانده «تست اجرایی» هستند، نه «طراحی قابلیت جدید».
