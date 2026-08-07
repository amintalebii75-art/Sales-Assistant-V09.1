<?php
declare(strict_types=1);
require_once __DIR__ . '/ai_provider.php';

/**
 * ارائه‌دهنده‌ی اول (فاز ۲): OpenAI Responses API با خروجی ساختاریافته (json_schema).
 * store=false ارسال می‌شود تا متن کامل درخواست/پاسخ روی سرور OpenAI نگه داشته نشود
 * (سیاست نگهداری خودشان همچنان طبق حساب API اعمال می‌شود).
 */
final class HippoAiOpenAiProvider implements HippoAiProviderInterface {
    private string $apiKey;

    public function __construct(string $apiKey) {
        $this->apiKey = $apiKey;
    }

    public function complete(array $request): array {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('افزونه cURL روی هاست فعال نیست.');
        }

        $payload = [
            'model' => $request['model'],
            'instructions' => $request['instructions'],
            'input' => $request['input'],
            'store' => false,
            'text' => ['format' => [
                'type' => 'json_schema',
                'name' => $request['schema_name'],
                'strict' => true,
                'schema' => $request['schema'],
            ]],
        ];

        $ch = curl_init('https://api.openai.com/v1/responses');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => max(5, (int)($request['timeout'] ?? 60)),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiKey,
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
            $msg = (string)($decoded['error']['message'] ?? ('HTTP ' . $status));
            throw new RuntimeException('خطای سرویس AI: ' . $msg);
        }

        $text = $this->extractText(is_array($decoded) ? $decoded : []);
        if ($text === '') {
            throw new RuntimeException('پاسخ خالی از سرویس AI دریافت شد.');
        }

        return ['text' => $text, 'model' => (string)($decoded['model'] ?? $request['model'])];
    }

    private function extractText(array $response): string {
        if (isset($response['output_text']) && is_string($response['output_text'])) {
            return $response['output_text'];
        }
        $out = '';
        foreach (($response['output'] ?? []) as $item) {
            if (($item['type'] ?? '') !== 'message') continue;
            foreach (($item['content'] ?? []) as $c) {
                if (($c['type'] ?? '') === 'output_text') $out .= (string)($c['text'] ?? '');
            }
        }
        return trim($out);
    }
}
