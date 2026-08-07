# TEST REPORT V06.0.4 — Jalali Date Selectors

## Scope
تبدیل ورودی‌ها و نمایش تاریخ‌های قابل ویرایش CRM/Planning به تقویم شمسی، با حفظ قرارداد ISO برای تاریخ‌های روزانه در Backend.

## Results
- PHP Syntax: PASS — 28/28
- JavaScript Syntax: PASS — 16/16
- V05.2 Security Regression: PASS — 27/27
- V06/V06.0.1 Planning Policy: PASS — 47/47
- V06.0.3 Localhost Security Regression: PASS — 20/20
- V06.0.4 Jalali Static Regression: PASS — 8/8
- Jalali Conversion Runtime (Node): PASS — 7 conversion assertions
- Secret Scan: PASS

## Covered behavior
- انتخاب روز/ماه/سال شمسی برای شروع و پایان هفته.
- انتخاب روز/ماه/سال شمسی برای سررسید وظیفه.
- انتخاب ماه/سال شمسی برای ایجاد و کپی برنامه ماهانه.
- نمایش شمسی تاریخ‌ها و زمان‌ها.
- ذخیره تاریخ روزانه به ISO `YYYY-MM-DD`.
- پذیرش month key جدید شمسی و سازگاری با کلیدهای قدیمی میلادی.
- مرتب‌سازی لیست برنامه‌ها با ID برای جلوگیری از اختلال میان کلیدهای قدیمی و جدید.

## NOT RUN / Pending localhost verification
- تست UI واقعی Patch روی Chrome در localhost.
- ایجاد برنامه با کلید شمسی روی MariaDB واقعی.
- ویرایش تاریخ هفته و سررسید وظیفه و مشاهده مقدار ذخیره‌شده در دیتابیس.
- تست Responsive انتخابگرها در موبایل و تبلت.

هیچ Migration جدیدی لازم نیست.
