# V05.2 Policy Test Results

- PASS: 27
- FAIL: 0
- PHP: 8.4.16
- Database: not used in this suite

| ID | Result | Test | Detail |
|---|---|---|---|---|
| FS-01 | PASS | مدیر با View و Save کامل می‌تواند Full-State ذخیره کند | manager + state.view_full + state.save_full |
| FS-02 | PASS | مدیر با View و بدون Save نمی‌تواند Full-State ذخیره کند | view-only manager rejected for full save |
| FS-03 | PASS | مدیر با Save و بدون View نمی‌تواند Full-State ذخیره کند | save-only manager rejected for full save |
| FS-04 | PASS | Override غیرمدیر مرز سازمانی را دور نمی‌زند | real manager role remains mandatory |
| FS-05 | PASS | مالک Interaction در Full-State فقط سمت سرور تعیین می‌شود | existing owner preserved; new owner set from authenticated session |
| CF-01 | PASS | Field-Level مشتری در سطح Call فقط فیلدهای تماس را می‌فرستد | id, name, company, contact, phone, phone2, city, province, industry, product, stage, nextFollowUp, status, assignee, _accessLevel, _ownerSelf |
| CF-02 | PASS | Field-Level مشتری در سطح View از Call محدودتر است | id, name, company, city, province, industry, product, stage, nextFollowUp, status, assignee, _accessLevel, _ownerSelf |
| CF-03 | PASS | customer_access=call سقف customers.edit_all است | call ceiling preserved despite customers.edit_all |
| IF-01 | PASS | تعامل Call فاقد سفارش، Fulfillment و مالی است | id, customerId, date, channel, resultIds, note, nextFollowUp, duration, status, memberId, _accessLevel, _ownerSelf |
| IF-02 | PASS | تعامل View فقط خلاصه لازم را دارد | id, customerId, date, channel, resultIds, status, memberId, _accessLevel, _ownerSelf |
| IF-03 | PASS | Payload سفارش جعلی در سطح Call رد می‌شود | fulfillment and orderValue rejected |
| IF-04 | PASS | Result خرید جعلی در سطح Call رد می‌شود | purchase result rejected |
| SM-01 | PASS | Scoped Merge مشتری پنهان، Formula و Settings را حفظ می‌کند | omitted organization data preserved |
| SM-02 | PASS | Call fields change while Stage/Source/Assignee remain unchanged | allowed patch applied; sensitive fields stripped |
| SM-03 | PASS | کاربر View نمی‌تواند Interaction ثبت کند | view blocked |
| SM-04 | PASS | Interaction متعلق به کاربر دیگر قابل ویرایش نیست | ownership enforced |
| SM-05 | PASS | حذف Interaction برای غیرمدیر با omission انجام نمی‌شود | server retained all existing interactions |
| SM-06 | PASS | Revoke دسترسی بلافاصله ثبت تعامل را متوقف می‌کند | no access row, no operation |
| TM-01 | PASS | حساب عملیاتی بدون Team Member مسدود است | workspace and APIs blocked |
| TM-02 | PASS | Team Member در Permission Fingerprint اثر می‌گذارد | cache/session scope changes on relink |
| TM-03 | PASS | Team Member نامعتبر حساب عملیاتی را مسدود می‌کند | non-empty stale id is not enough |
| SP-01 | PASS | Permission حساس بدون نقش واقعی Manager رد می‌شود | override cannot create organization authority |
| ST-01 | PASS | Backup/Restore دارای گارد Manager و Full-State است | static endpoint guard verification |
| ST-02 | PASS | Restore response خام Full-State برنمی‌گرداند | response is metadata + revision + context token only |
| ST-03 | PASS | Team Member linking در users_api نیازمند Manager است | create and sensitive update guarded |
| ST-04 | PASS | State context token و Fingerprint در Save بررسی می‌شوند | load/save invariant present |
| ST-05 | PASS | No-op Save مسیر افزایش Revision ندارد | no-op exits before revision increment and backup |
