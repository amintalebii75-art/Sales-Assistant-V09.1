# CHANGELOG — V06 Four-Week Team Planning

## مبنا و دامنه
این نسخه در شاخه مستقل `Sales-Assistant-V06-Four-Week-Team-Planning` و بر پایه `Sales-Assistant-V05-3-Staging-Validation-Final` ساخته شده است. منطق امنیتی، Session، Login، Customer Ownership، Customer Access، Scoped Save، Full-State Save، Revision اصلی CRM، Backup/Restore، Excel Import و AI نسخه مبنا بازنویسی نشده‌اند.

## معماری برنامه ماهانه
داده برنامه ماهانه خارج از State JSON و در چهار جدول مستقل ذخیره می‌شود:

- `monthly_plans`: مشخصات ماه، وضعیت و Revision برنامه.
- `monthly_plan_weeks`: چهار هفته، هدف و بازه زمانی هر هفته.
- `monthly_plan_tasks`: متن و تنظیمات اصلی وظیفه مدیر.
- `monthly_task_assignments`: وضعیت، پیشرفت و یادداشت مستقل هر کاربر.

استفاده از جدول مستقل مانع جایگزینی کل داده برنامه توسط مرورگر و مخلوط‌شدن Conflict برنامه با Revision اصلی CRM می‌شود. Task اصلی وضعیت فردی ندارد و تکمیل یک Assignment روی Assignment کاربران دیگر اثر نمی‌گذارد.

## Permissionهای جدید
Permissionهای زیر در فایل مرکزی `permissions.php` و Migration ثبت شده‌اند:

`plans.view_own`, `plans.view_team`, `plans.view_team_summary`, `plans.manage`, `plans.publish`, `plans.assign`, `plans.update_own`, `plans.close`, `plans.copy_month`.

Overrideهای موجود V05 روی Permissionهای جدید نیز اعمال می‌شوند. Override برابر `false` در Fingerprint اثر می‌گذارد و دسترسی درخواست بعدی را حذف می‌کند.

## وظیفه عمومی
عملیات «همه اعضای عملیاتی» ابتدا فهرست دریافت‌کنندگان واجد شرایط را برمی‌گرداند. فقط کاربران Active با نقش Marketer یا Center Call، Team Member معتبر، بدون نیاز به RBAC Review و دارای Permission مشاهده برنامه انتخاب می‌شوند. پس از تأیید مدیر، برای هر کاربر یک Assignment مستقل در Transaction ساخته می‌شود. کاربر جدید بعداً خودکار Assignment دریافت نمی‌کند.

## Conflict و جلوگیری از Lost Update
Plan، Week، Task و Assignment ستون `revision` مستقل دارند. تمام Updateهای حساس Revision مورد انتظار را دریافت و در شرط Update بررسی می‌کنند. اختلاف Revision با HTTP 409 و پیام عمومی پاسخ داده می‌شود؛ داده حساس یا SQL در پاسخ Conflict برنمی‌گردد.

## اتصال به «وظایف من»
فایل `assets/js/planning-tasks-integration.js` Assignmentهای برنامه ماهانه را با منبع `monthly_plan` و Badge «برنامه ماهانه» به نمای فعلی وظایف اضافه می‌کند. داده در JSON قدیمی کپی نمی‌شود. تغییر وضعیت از هر دو صفحه Endpoint واحد `update_my_assignment` و همان Assignment اصلی را به‌روزرسانی می‌کند.

## API و رابط
- API مستقل: `planning_api.php` با Session، وضعیت حساب، Permission، CSRF، Validation، Prepared Statement، Transaction، Audit و پاسخ JSON استاندارد.
- مدیر می‌تواند Assignment هر فرد را با Revision مستقل مدیریت یا لغو کند؛ یادداشت اصلی ثبت‌شده توسط کاربر در Update مدیریتی بازنویسی نمی‌شود.
- صفحه مستقل: `planning.php` با نمای Manager، Marketer، Center Call و Manager Viewer.
- رابط RTL و Responsive در `assets/css/planning.css` و `assets/js/planning.js`.
- Navigation برنامه براساس Permission در `index.php` و خلاصه ناظر در `manager.php` اضافه شد.

## Audit
رویدادهای ایجاد، ویرایش، انتشار، بستن، آرشیو، کپی، مدیریت هفته و Task، تخصیص، لغو و تغییر وضعیت Assignment ثبت می‌شوند. Password، CSRF، Session، Cookie، Query و Stack Trace در Metadata ثبت نمی‌شوند.

## فایل‌های ایجادشده
- `planning.php`
- `planning_api.php`
- `v06_four_week_planning_migration.sql`
- `ROLLBACK-V06.sql`
- `assets/css/planning.css`
- `assets/js/planning.js`
- `assets/js/planning-tasks-integration.js`
- `tests/v06_policy_tests.php`
- مستندات و شواهد V06

## فایل‌های تغییرکرده
- `permissions.php`
- `schema.sql`
- `index.php`
- `manager.php`

## موارد بدون تغییر عمدی
Promptها و Providerهای AI، اتصال ArvanCloud، Multi-tenant، ساختار کاربران و RBAC قبلی، Customer Access، Migration کامل State JSON، Migration کامل Customers، Pipeline، سفارش‌ها، Backup/Restore و Excel Import تغییر عملکردی نکرده‌اند.

## وضعیت نقص Baseline
هیچ نقص اعلام‌شده V05.3 به‌صورت مخفیانه وارد یا در V06 اصلاح نشده است. در تست‌های قابل اجرای این محیط نیز نقص قابل بازتولید جدیدی در Baseline پیدا نشد. اعتبارسنجی واقعی دیتابیس و چهار نقش همچنان باید روی Staging انجام شود.
