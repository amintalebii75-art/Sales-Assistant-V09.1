<?php
declare(strict_types=1);

/**
 * قرارداد یکسان برای هر ارائه‌دهنده‌ی هوش مصنوعی. ai_service.php فقط با این
 * اینترفیس کار می‌کند، نه با API خاص هیچ سرویسی — عوض‌کردن ارائه‌دهنده یعنی
 * فقط یک کلاس تازه پیاده‌سازش کن و در hippo_ai_provider() انتخابش کن؛
 * به ai.php یا index.php دست نمی‌خورد.
 */
interface HippoAiProviderInterface {
    /**
     * @param array{model:string,instructions:string,input:string,schema:array,schema_name:string,timeout:int} $request
     * @return array{text:string,model:string} متن خام پاسخ (قرار است JSON باشد) + نام مدلی که واقعاً جواب داد
     * @throws RuntimeException در صورت خطای شبکه/سرویس/پیکربندی
     */
    public function complete(array $request): array;
}
