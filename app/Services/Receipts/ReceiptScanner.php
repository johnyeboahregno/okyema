<?php

declare(strict_types=1);

namespace App\Services\Receipts;

use App\Services\MoneyMath;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Best-effort receipt extraction from an image, via the configured
 * OpenAI-compatible vision endpoint. Never throws to the caller: when the
 * server is not configured or the read fails, it returns null and the user
 * enters the fields by hand.
 */
final class ReceiptScanner
{
    public function enabled(): bool
    {
        return $this->missingConfiguration() === [];
    }

    /**
     * @return list<string>
     */
    public function missingConfiguration(): array
    {
        $missing = [];

        if (! config('okyema.receipts.enabled')) {
            $missing[] = 'RECEIPT_SCAN_ENABLED is off';
        }
        if (! $this->visionEnabled()) {
            $missing[] = 'AI vision is not enabled (AI_VISION_ENABLED / AI_ENABLED)';
        }
        if (! filled($this->visionApiKey())) {
            $missing[] = 'AI vision API key is empty (AI_VISION_API_KEY / AI_API_KEY)';
        }
        if (! filled($this->visionBaseUrl())) {
            $missing[] = 'AI vision base URL is empty (AI_VISION_BASE_URL / AI_BASE_URL)';
        }

        return $missing;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function scan(string $path, ?string $mime): ?array
    {
        if (! $this->enabled()) {
            return null;
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            return null;
        }

        $model = $this->visionModel();

        try {
            $response = Http::timeout((int) config('okyema.ai.timeout_seconds', 30))
                ->withToken($this->visionApiKey())
                ->post(rtrim($this->visionBaseUrl(), '/').'/chat/completions', [
                    'model' => $model,
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => [
                                ['type' => 'text', 'text' => 'Read this receipt and reply with the JSON object only: {"merchant":?, "date":?, "total":?, "currency":?, "tax":?, "payment_method":?, "items":[{"name":?,"amount":?}], "confidence":0-1}'],
                                ['type' => 'image_url', 'image_url' => ['url' => 'data:'.$mime.';base64,'.base64_encode($contents)]],
                            ],
                        ],
                    ],
                    'temperature' => 0,
                    'max_tokens' => 800,
                    'response_format' => ['type' => 'json_object'],
                ]);

            if ($response->failed()) {
                Log::warning('ai.receipt_scan.failed', ['status' => $response->status()]);

                return null;
            }

            $content = $response->json('choices.0.message.content');
            $decoded = is_string($content) ? json_decode($content, true) : null;

            if (! is_array($decoded)) {
                return null;
            }

            return $this->normalise($decoded);
        } catch (\Throwable $e) {
            Log::warning('ai.receipt_scan.failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    private function visionEnabled(): bool
    {
        $vision = config('okyema.ai.vision_enabled');
        if ($vision === null || $vision === '') {
            $vision = config('okyema.ai.enabled');
        }

        return filter_var($vision, FILTER_VALIDATE_BOOLEAN);
    }

    private function visionApiKey(): string
    {
        return (string) (config('okyema.ai.vision_api_key') ?: config('okyema.ai.api_key'));
    }

    private function visionBaseUrl(): string
    {
        return (string) (config('okyema.ai.vision_base_url') ?: config('okyema.ai.base_url'));
    }

    private function visionModel(): string
    {
        return (string) (config('okyema.ai.vision_model') ?: config('okyema.ai.model'));
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>|null
     */
    private function normalise(array $raw): ?array
    {
        $total = $this->money($raw['total'] ?? null);

        return [
            'merchant' => $this->string($raw['merchant'] ?? null),
            'expense_date' => $this->date($raw['date'] ?? null),
            'total_minor' => $total,
            'currency' => $this->string($raw['currency'] ?? null),
            'tax_minor' => $this->money($raw['tax'] ?? null),
            'payment_method' => $this->string($raw['payment_method'] ?? null),
            'line_items' => $this->lineItems($raw['items'] ?? null),
            'confidence' => is_numeric($raw['confidence'] ?? null) ? (float) $raw['confidence'] : 0.0,
        ];
    }

    private function money(mixed $value): ?int
    {
        if (is_int($value) || is_float($value)) {
            return (int) round((float) $value * 100);
        }
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return MoneyMath::majorToMinor($value);
        } catch (RuntimeException) {
            return null;
        }
    }

    private function string(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? mb_substr(trim($value), 0, 120) : null;
    }

    private function date(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return list<array{name: string, amount: int}>
     */
    private function lineItems(mixed $raw): array
    {
        $items = [];

        foreach (is_array($raw) ? $raw : [] as $item) {
            if (! is_array($item)) {
                continue;
            }

            $name = trim((string) ($item['name'] ?? ''));
            $amount = $this->money($item['amount'] ?? $item['price'] ?? null);

            if ($name === '' || $amount === null) {
                continue;
            }

            $items[] = ['name' => mb_substr($name, 0, 80), 'amount' => abs($amount)];
        }

        return array_slice($items, 0, 25);
    }
}
