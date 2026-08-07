<?php
declare(strict_types=1);
require_once __DIR__ . '/ai_provider.php';

/**
 * ارائه‌دهنده‌ی دوم: دروازه‌ی AIaaS آروان‌کلاد (چون خرید API مستقیم OpenAI از ایران
 * ممکن نیست). طبق مستندات رسمی آروان (docs.arvancloud.ir/fa/aiaas/api-usage):
 *   POST {endpoint}/chat/completions
 *   Authorization: apikey <کلید>      -- نه Bearer، دقیقاً کلمه‌ی apikey
 *   بدنه: فرمت سازگار با OpenAI Chat Completions (messages/model/max_tokens)
 * آروان خروجی JSON Schema ساختاریافته را رسماً تضمین نکرده؛ برای همین schema
 * را به‌صورت متن داخل system prompt توضیح می‌دهیم و پاسخ را با decode معمولی
 * (که در ai_service.php کد ```json را هم پاک می‌کند) پارس می‌کنیم.
 */
final class HippoAiArvanCloudProvider implements HippoAiProviderInterface {
    private string $endpoint;
    private string $apiKey;

    public function __construct(string $endpoint, string $apiKey) {
        $this->endpoint = rtrim($endpoint, '/');
        $this->apiKey = $apiKey;
    }

    public function complete(array $request): array {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('افزونه cURL روی هاست فعال نیست.');
        }
        if ($this->endpoint === '') {
            throw new RuntimeException('آدرس Endpoint آروان‌کلاد در ai_config.php تنظیم نشده است.');
        }

        $schemaText = json_encode($request['schema'], JSON_UNESCAPED_UNICODE);
        $systemPrompt = $request['instructions']
            . "\n\nخروجی را فقط و فقط به‌صورت یک JSON معتبر و دقیقاً با همین کلیدها بده، بدون هیچ توضیح یا متن اضافه و بدون ```: "
            . $schemaText;

        $payload = [
            'model' => $request['model'],
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $request['input']],
            ],
            'max_tokens' => 1200,
            'temperature' => 0.3,
        ];

        $ch = curl_init($this->endpoint . '/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => max(5, (int)($request['timeout'] ?? 60)),
            CURLOPT_HTTPHEADER => [
                'Authorization: apikey ' . $this->apiKey,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
        $raw = curl_exec($ch);
        $err = curl_error($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false || $err !== '') {
            throw new RuntimeException('ارتباط با سرویس AI ناموفق بود: ' . $err);
        }

        $decoded = json_decode($raw, true);
        if ($status < 200 || $status >= 300) {
            $msg = (string)($decoded['error']['message'] ?? ($decoded['message'] ?? ('HTTP ' . $status)));
            if ($status === 401) $msg = 'کلید API آروان‌کلاد نامعتبر است. ' . $msg;
            if ($status === 429) $msg = 'سقف نرخ درخواست آروان‌کلاد پر شده؛ کمی صبر کن. ' . $msg;
            throw new RuntimeException('خطای سرویس AI: ' . $msg);
        }

        $text = (string)($decoded['choices'][0]['message']['content'] ?? '');
        if ($text === '') {
            throw new RuntimeException('پاسخ خالی از سرویس AI دریافت شد.');
        }

        return ['text' => $text, 'model' => (string)($decoded['model'] ?? $request['model'])];
    }
}
