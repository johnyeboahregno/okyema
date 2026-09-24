<?php

declare(strict_types=1);

use App\Services\Receipts\ReceiptScanner;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'okyema.receipts.enabled' => true,
        'okyema.ai.enabled' => true,
        'okyema.ai.provider' => 'deepseek',
        'okyema.ai.api_key' => 'chat-key',
        'okyema.ai.model' => 'deepseek-chat',
        'okyema.ai.base_url' => 'https://chat.example.com',
        'okyema.ai.vision_enabled' => true,
        'okyema.ai.vision_model' => '',
        'okyema.ai.vision_api_key' => '',
        'okyema.ai.vision_base_url' => '',
    ]);
});

function receiptJson(): string
{
    return json_encode([
        'merchant' => 'Acme Coffee',
        'date' => '2026-09-24',
        'total' => '12.50',
        'currency' => 'GBP',
        'confidence' => 0.95,
    ]);
}

test('scan uses the vision endpoint and key when configured', function () {
    config([
        'okyema.ai.vision_model' => 'vision-model',
        'okyema.ai.vision_api_key' => 'vision-key',
        'okyema.ai.vision_base_url' => 'https://vision.example.com',
    ]);

    Http::fake([
        'https://vision.example.com/chat/completions' => Http::response([
            'choices' => [['message' => ['content' => receiptJson()]]],
        ]),
    ]);

    $path = tempnam(sys_get_temp_dir(), 'receipt');
    file_put_contents($path, 'fake-jpeg-bytes');

    $result = app(ReceiptScanner::class)->scan($path, 'image/jpeg');

    expect($result)->not->toBeNull()
        ->and($result['merchant'])->toBe('Acme Coffee');

    Http::assertSent(fn ($request) => $request->url() === 'https://vision.example.com/chat/completions'
        && $request->hasHeader('Authorization', 'Bearer vision-key')
        && $request['model'] === 'vision-model');

    @unlink($path);
});

test('scan falls back to the chat endpoint and key when vision is unset', function () {
    Http::fake([
        'https://chat.example.com/chat/completions' => Http::response([
            'choices' => [['message' => ['content' => receiptJson()]]],
        ]),
    ]);

    $path = tempnam(sys_get_temp_dir(), 'receipt');
    file_put_contents($path, 'fake-jpeg-bytes');

    $result = app(ReceiptScanner::class)->scan($path, 'image/jpeg');

    expect($result)->not->toBeNull();

    Http::assertSent(fn ($request) => $request->url() === 'https://chat.example.com/chat/completions'
        && $request->hasHeader('Authorization', 'Bearer chat-key')
        && $request['model'] === 'deepseek-chat');

    @unlink($path);
});

test('missingConfiguration is empty when vision config is present', function () {
    config([
        'okyema.ai.vision_api_key' => 'vision-key',
        'okyema.ai.vision_base_url' => 'https://vision.example.com',
    ]);

    expect(app(ReceiptScanner::class)->missingConfiguration())->toBe([]);
});

test('missingConfiguration flags a missing vision key and base url', function () {
    config([
        'okyema.ai.api_key' => '',
        'okyema.ai.base_url' => '',
    ]);

    expect(app(ReceiptScanner::class)->missingConfiguration())
        ->toContain('AI vision API key is empty (AI_VISION_API_KEY / AI_API_KEY)')
        ->toContain('AI vision base URL is empty (AI_VISION_BASE_URL / AI_BASE_URL)');
});
