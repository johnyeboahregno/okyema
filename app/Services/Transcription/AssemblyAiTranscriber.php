<?php

declare(strict_types=1);

namespace App\Services\Transcription;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * AssemblyAI pre-recorded transcription. Uploads the raw audio bytes, submits
 * a transcript job that calls back to Okyema's webhook, and exposes the
 * signature check the webhook uses to authenticate inbound deliveries.
 */
final class AssemblyAiTranscriber
{
    private const UPLOAD_URL = 'https://api.assemblyai.com/v2/upload';

    private const TRANSCRIPT_URL = 'https://api.assemblyai.com/v2/transcript';

    /**
     * @return array{job_id: string, status: string}
     */
    public function submit(string $contents, string $mime, string $webhookUrl, string $webhookSecret): array
    {
        $uploadUrl = $this->upload($contents, $mime);

        $response = Http::withToken($this->apiKey())
            ->post(self::TRANSCRIPT_URL, [
                'audio_url' => $uploadUrl,
                'webhook_url' => $webhookUrl,
                'webhook_auth_header_name' => 'X-Okyema-Webhook-Secret',
                'webhook_auth_header_value' => $webhookSecret,
            ]);

        if ($response->failed()) {
            throw new RuntimeException('AssemblyAI submission failed: '.mb_substr($response->body(), 0, 400));
        }

        return [
            'job_id' => (string) $response->json('id', ''),
            'status' => (string) ($response->json('status', 'queued') ?: 'queued'),
        ];
    }

    private function upload(string $contents, string $mime): string
    {
        $response = Http::withToken($this->apiKey())
            ->withBody($contents, $mime !== '' ? $mime : 'application/octet-stream')
            ->post(self::UPLOAD_URL);

        if ($response->failed()) {
            throw new RuntimeException('AssemblyAI upload failed: '.mb_substr($response->body(), 0, 400));
        }

        $uploadUrl = (string) $response->json('upload_url', '');
        if ($uploadUrl === '') {
            throw new RuntimeException('AssemblyAI did not return an upload URL.');
        }

        return $uploadUrl;
    }

    public function isConfigured(): bool
    {
        return $this->apiKey() !== '';
    }

    private function apiKey(): string
    {
        return (string) config('okyema.transcription.api_key', '');
    }
}
