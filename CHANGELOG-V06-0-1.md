# CHANGELOG — V06.0.1 Planning Security Fix

## مبنا و دامنه
این Release مستقل از `Sales-Assistant-V06-Four-Week-Team-Planning` ساخته شده است. هیچ قابلیت جدیدی اضافه نشده و اصلاحات فقط به امنیت چرخه Assignment، حفظ تاریخچه، صحت Progress Summary، سیاست Copy Plan و مستندات Release محدود هستند.

## نقص Reactivate غیرمجاز
در Endpoint `planning_api.php?action=update_my_assignment` وضعیت فعلی Assignment لغوشده به‌عنوان وضعیت نهایی کنترل نمی‌شد. کاربر عملیاتی می‌توانست با درخواست مستقیم و انتخاب وضعیت دیگری، Assignment لغوشده را دوباره فعال کند. همچنین تابع تخصیص `planning_assign_users()` هنگام تخصیص مجدد، Reactivate را ضمنی انجام می‌داد.

اصلاحات:
- Assignment با وضعیت `cancelled` برای Marketer و Center Call فقط خواندنی است.
- `update_my_assignment` پیش از هر Update، وضعیت فعلی را سمت سرور کنترل و درخواست را با HTTP 409 رد می‌کند.
- `update_assignment` معمولی مدیر نیز مجاز به Reactivate ضمنی نیست.
- Reactivate ضمنی از `planning_assign_users()` حذف شد.
- Action مستقل `reactivate_assignment` اضافه شد که فقط برای Manager واقعی دارای `plans.assign` مجاز است.
- `expected_revision` و وضعیت فعلی `cancelled` الزامی است؛ Revision قدیمی با HTTP 409 رد می‌شود.
- عملیات با رویداد `plan.assignment_reactivate` Audit می‌شود.

## حفظ تاریخچه Assignment
جدول مستقل `monthly_assignment_history` اضافه شد. پیش از Reactivate صریح، Snapshot کامل وضعیت قبلی شامل Status، Progress، Note، Blocked Reason، Started/Completed At، عامل تغییر، زمان و دلیل تغییر در Transaction ذخیره می‌شود. فقط پس از ثبت موفق Snapshot، Assignment اصلی به وضعیت `pending` بازنشانی می‌شود. بنابراین اطلاعات قبلی بدون مسیر بازیابی پاک نمی‌شوند.

## Progress کل Weighted
محاسبه پیشرفت کل تیم دیگر میانگین ساده درصد اعضا نیست. `planning_team_summary()` تمام Assignmentهای غیرلغوشده را دریافت و فرمول زیر را اعمال می‌کند:

`SUM(progress_percent) / COUNT(non-cancelled assignments)`

پیشرفت شخصی هر عضو همچنان میانگین Assignmentهای همان عضو است. مثال یک Assignment صددرصدی و نه Assignment صفر، نتیجه کل ۱۰ درصد می‌دهد.

## سیاست Copy Plan
`copy_plan` فقط Taskهای دارای وضعیت `active` را کپی می‌کند. Taskهای `cancelled` و `archived` کپی نمی‌شوند، هیچ Assignment قبلی منتقل نمی‌شود و `due_date` و تاریخ شروع/پایان Weekهای ماه جدید `NULL` باقی می‌مانند.

## رابط کاربری
- در صفحه «وظایف من»، Assignment لغوشده دکمه «ثبت پیشرفت» ندارد و با برچسب «لغوشده · فقط خواندنی» نمایش داده می‌شود.
- فراخوانی دستی `HippoMonthlyTasks.update()` برای Assignment لغوشده پیش از ارسال Request متوقف می‌شود.
- رابط مدیر برای Assignment لغوشده فقط عملیات صریح «فعال‌سازی مجدد» با ثبت دلیل ارائه می‌کند.

## فایل‌های ایجادشده
- `planning_policy.php`
- `CHANGELOG-V06-0-1.md`
- `TEST-REPORT-V06-0-1.md`
- `INSTALL-V06-0-1.md`

## فایل‌های تغییرکرده
- `planning_api.php`
- `assets/js/planning.js`
- `assets/js/planning-tasks-integration.js`
- `v06_four_week_planning_migration.sql`
- `schema.sql`
- `ROLLBACK-V06.sql`
- `tests/v06_policy_tests.php`

## موارد بدون تغییر
RBAC پایه، Customer Ownership، Customer Access، Full-State/Scoped Save، Login/Session، Backup/Restore، Excel Import، Pipeline، AI Providerها و Promptهای AI تغییر عملکردی نکرده‌اند.
