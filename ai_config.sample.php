<?php
// این فایل را کپی کن و نام نسخه‌ی کپی را ai_config.php بگذار، بعد endpoint و کلید را پر کن.
// ⚠️ اینها را هرگز داخل index.php یا هیچ فایل JavaScript نگذار — فقط همین‌جا، سمت سرور.
// ai_config.php توسط .htaccess از دسترسی مستقیم وب مسدود می‌شود؛ اگر نبود، خودت مسدودش کن.

return [
    'provider' => 'arvancloud',                 // چون خرید مستقیم از OpenAI از ایران ممکن نیست
    'endpoint' => 'REPLACE_ME',                 // آدرس AI gateway از پنل آروان‌کلاد (بدون /chat/completions در انتها)
    'api_key' => 'REPLACE_ME',                  // کلید دسترسی (Access Key) از همان صفحه‌ی Endpoint در آروان‌کلاد
    'model' => 'GPT-4.1-Mini',                  // اگر آروان مدل را با اسم دیگری می‌خواهد، همان‌جا در نمونه‌کد خودشان چک کن
    'timeout_seconds' => 45,
    'daily_limit_per_user' => 40,                // سقف تعداد درخواست موفق در روز برای هر حساب (جدا از Rate Limit خودِ آروان)
];

// اگر یک روز خواستی مستقیم از OpenAI استفاده کنی (provider جایگزین، از قبل پیاده‌سازی شده):
// return [
//     'provider' => 'openai',
//     'api_key' => 'sk-REPLACE_ME',
//     'model' => 'gpt-4.1-mini',
//     'timeout_seconds' => 45,
//     'daily_limit_per_user' => 40,
// ];
