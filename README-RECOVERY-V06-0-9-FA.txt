بسته مرجع V06.0.9 — روش بازیابی

1) پوشه را داخل htdocs قرار بده.
2) config.php معتبر محیط localhost را از نسخه فعال خودت کپی کن.
3) دیتابیس موجود granule_sales_local را استفاده کن؛ بدون نیاز قطعی به Import جدید برای Patchهای V06.0.6 تا V06.0.9.
4) فایل‌های قانونی فونت IRANSansXFaNum را در assets/fonts قرار بده:
   IRANSansXFaNum-Regular.ttf
   IRANSansXFaNum-Medium.ttf
   IRANSansXFaNum-DemiBold.ttf
   IRANSansXFaNum-Bold.ttf
5) create_admin.php عمداً در بسته نیست.
6) آدرس نمونه:
   http://localhost:8080/Sales-Assistant-V06-0-9-Consolidated-Localhost-Reference/login.php
7) پس از نصب، Ctrl + F5 بزن.

این نسخه هنوز Production Ready اعلام نشده است. وضعیت تست‌ها در سند Word مرجع و PROJECT-STATUS-V06-0-9-FA.md ثبت شده است.


نسخه UI پایلوت V07.0.0 بر پایه همین مرجع ساخته شده و Migration دیتابیس ندارد.
