<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\AI\AIProviderInterface;
use App\Services\AI\OpenAiCompatibleProvider;
use App\Support\CaBundle;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->registerSharedAiPackage();

        $this->app->bind(AIProviderInterface::class, function () {
            return new OpenAiCompatibleProvider(
                baseUrl: (string) config('okyema.ai.base_url', ''),
                apiKey: config('okyema.ai.api_key') ?: null,
                model: (string) config('okyema.ai.model', ''),
                timeoutSeconds: (int) config('okyema.ai.timeout_seconds', 30),
                maxTokens: (int) config('okyema.ai.max_tokens', 2000),
                temperature: (float) config('okyema.ai.temperature', 0.2),
            );
        });
    }

    public function boot(): void
    {
        // Point every outbound HTTPS request at a real CA bundle. PHP on
        // Windows ships without one, which breaks OAuth and AI calls.
        $bundle = CaBundle::path();

        if ($bundle !== null) {
            Http::globalOptions(['verify' => $bundle]);
        } elseif (app()->environment('local')) {
            Log::warning('http.ca_bundle.missing', [
                'hint' => 'No CA bundle found — set CA_BUNDLE_PATH to a cacert.pem for outbound HTTPS.',
            ]);
        }
    }

    /**
     * Register the shared `courtly/ai-client` package classes. Fallback for
     * machines without Composer; on the VPS the package is installed via
     * Composer (see composer.json repositories).
     */
    private function registerSharedAiPackage(): void
    {
        $src = realpath(env('SHARED_AI_CLIENT_PATH', base_path('../ai-client/src')));
        if ($src === false) {
            return;
        }

        spl_autoload_register(function (string $class) use ($src): void {
            if (! str_starts_with($class, 'Courtly\\AiClient\\')) {
                return;
            }

            $file = $src.DIRECTORY_SEPARATOR
                .str_replace('\\', DIRECTORY_SEPARATOR, substr($class, strlen('Courtly\\AiClient\\')))
                .'.php';

            if (is_file($file)) {
                require_once $file;
            }
        });
    }
}
