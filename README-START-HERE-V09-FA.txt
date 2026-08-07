شروع از اینجا — V09.0.0 Host Pilot / PWA Complete

این نسخه مرجع واحد شروع پایلوت واقعی است.

۱) برای localhost:
- پوشه را داخل htdocs قرار بده.
- config.php نسخه فعال V08 را کپی کن.
- فونت‌های محلی خودت را داخل assets/fonts کپی کن.
- login.php را باز کن.

۲) برای هاست اشتراکی:
- ابتدا Backup کامل فایل و DB بگیر.
- پوشه را کنار نسخه قبلی و در مسیر مستقل Extract کن.
- config.production.sample.php را به config.php کپی و تنظیم کن.
- چون V09 Migration جدید ندارد، روی DB سازگار V08 هیچ SQL اجرا نکن.
- deployment_check.php را با حساب مدیر باز کن.

۳) برای Android:
- دامنه باید HTTPS باشد.
- با Chrome وارد سایت شو.
- دکمه «نصب روی گوشی» را بزن یا از منوی Chrome گزینه Install app / Add to Home screen را انتخاب کن.

۴) برای پایلوت:
- pilot_test.php: ماتریس تست چهار نقش
- pilot_issues.php: ثبت ایراد و خروجی JSON/CSV

نکته امنیتی:
Service Worker هیچ PHP، API یا اطلاعات حساب را Cache نمی‌کند. config.php و فایل‌های فونت واقعی داخل ZIP مرجع نیستند.
