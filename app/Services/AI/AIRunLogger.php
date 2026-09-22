<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Models\AIRun;
use Illuminate\Support\Facades\Log;

/**
 * Persists AI invocations to the `ai_runs` table for auditing and cost
 * tracking. Logging is best-effort: a logging failure must never break the
 * feature that triggered the AI call.
 */
class AIRunLogger
{
    public function log(
        string $runType,
        array $inputSummary,
        array $output,
        ?int $userId = null,
        string $status = 'SUCCESS',
        ?int $latencyMs = null,
        ?string $errorMessage = null,
    ): void {
        try {
            AIRun::query()->create([
                'user_id' => $userId,
                'run_type' => $runType,
                'provider' => config('okyema.ai.provider'),
                'model' => config('okyema.ai.model'),
                'input_summary' => $this->summarizeInput($inputSummary),
                'output' => $output,
                'latency_ms' => $latencyMs,
                'status' => $status,
                'error_message' => $errorMessage,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('ai.run.log_failed', [
                'run_type' => $runType,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function summarizeInput(array $input): array
    {
        $json = (string) json_encode($input, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return [
            'truncated' => mb_strlen($json) > 2000,
            'size' => mb_strlen($json),
            'preview' => mb_substr($json, 0, 2000),
        ];
    }
}
