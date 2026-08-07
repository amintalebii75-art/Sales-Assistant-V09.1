# TEST REPORT — V06.0.1 Planning Security Fix

## نتیجه کلان
**وضعیت نصب مستقیم روی Production: NOT APPROVED**

علت: Runtime واقعی Endpointهای Assignment و Migration روی MySQL/MariaDB در محیط ساخت قابل اجرا نبود. نتیجه Static و PHP Policy Runtime جایگزین تست دیتابیس واقعی نیست.

## تست‌های اجراشده

| حوزه | وضعیت | نتیجه واقعی |
|---|---|---|
| V06/V06.0.1 Policy Regression | PASS | 47 PASS / 0 FAIL |
| V05.2 Security Regression | PASS | 27 PASS / 0 FAIL |
| PHP Syntax | PASS | 25 PASS / 0 FAIL |
| JavaScript Syntax | PASS | 14 PASS / 0 FAIL |
| Inline JavaScript Syntax | PASS | 7 PASS / 0 FAIL |
| Asset path check | PASS | 70 PASS / 0 FAIL |
| PHP HTTP smoke test | PASS | Login 200، Planning بدون Session برابر 302، API بدون Session برابر 401 |
| Secret/Runtime Config Scan | PASS | Config واقعی، Password، Token، Cookie و API Key یافت نشد |
| ZIP structure/integrity | PASS | پس از ساخت و استخراج مجدد بررسی شد |

## تست اختصاصی cancelled Assignment

| تست | وضعیت | توضیح |
|---|---|---|
| Transition Policy: cancelled → pending/completed | PASS | تست Runtime تابع Policy در PHP؛ هر دو رد شدند |
| Guard سمت سرور در `update_my_assignment` | PASS | Regression Static؛ وضعیت فعلی cancelled پیش از Update کنترل می‌شود |
| مالکیت Session و تغییر `assignment_id`/`user_id` | PASS | Query به `a.id` و `a.user_id` جاری محدود است |
| Reactivate فقط Manager واقعی + `plans.assign` | PASS | Regression Static روی Action مستقل |
| Revision اجباری و مسیر HTTP 409 | PASS | Regression Static روی Optimistic Lock |
| Runtime واقعی API با MySQL/MariaDB | NOT RUN | MySQL/MariaDB و `pdo_mysql`/`mysqli` در محیط موجود نبود |

## تاریخچه Reactivate
- وجود جدول و تمام ستون‌های Snapshot: **PASS — Static**
- ثبت Snapshot پیش از Reset در Transaction: **PASS — Static**
- Audit رویداد `plan.assignment_reactivate`: **PASS — Static**
- بازیابی واقعی Snapshot از دیتابیس: **NOT RUN**

## Progress Weighted
- PHP Policy Runtime با داده نمونه ۱×۱۰۰ و ۹×۰: **PASS — نتیجه ۱۰٪**
- استفاده `planning_team_summary` از Assignmentهای خام غیرلغوشده: **PASS — Static**
- Query و نتیجه واقعی روی دیتابیس: **NOT RUN**

## Copy Plan
- فیلتر فقط `status='active'`: **PASS — Static**
- عدم کپی Taskهای cancelled و archived: **PASS — Static**
- عدم کپی Assignmentها: **PASS — Static**
- `due_date` و بازه Week جدید برابر NULL: **PASS — Static**
- Runtime واقعی Copy روی MySQL/MariaDB: **NOT RUN**

## Browser
تست کامل Browser احراز‌شده و Screenshot واقعی برای این اصلاح اجرا نشده است: **NOT RUN**. هیچ Screenshot ساختگی تولید نشده است.

## FAIL
هیچ FAIL در تست‌های اجراشده ثبت نشد. موارد `NOT RUN` باید پیش از Production روی Staging واقعی اجرا شوند.

## SHA-256 Release
SHA-256 نهایی فقط پس از بسته‌شدن کامل ZIP و بدون هیچ تغییر بعدی محاسبه می‌شود و در پاسخ تحویل ثبت خواهد شد. قرار دادن Hash خود ZIP داخل فایل موجود در همان ZIP پس از محاسبه، Archive را تغییر می‌دهد و Hash را باطل می‌کند؛ بنابراین در مستند داخل Archive مقدار خودارجاع و نادرست ثبت نشده است.
