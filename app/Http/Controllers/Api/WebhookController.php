<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Transcription\RecordingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    public function __construct(
        private readonly RecordingService $recordings,
    ) {}

    public function assemblyai(Request $request): JsonResponse
    {
        $secret = (string) config('okyema.transcription.webhook_secret', '');

        if ($secret !== '' && ! hash_equals($secret, (string) $request->header('X-Okyema-Webhook-Secret', ''))) {
            return response()->json(['ok' => false], 401);
        }

        if (($request->input('transcript_id') ?? '') === '') {
            return response()->json(['ok' => false, 'reason' => 'missing transcript_id'], 422);
        }

        $this->recordings->complete($request->all());

        return response()->json(['ok' => true]);
    }
}
