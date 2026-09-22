<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Okyema Application Version
    |--------------------------------------------------------------------------
    | Single source of truth for the release version, in MAJOR.MINOR.PATCH
    | form. Bump it on every user-visible release: it is the cache-busting
    | suffix for the CSS/favicon and is shown in the user menu.
    */
    'app' => [
        'version' => '0.1.0',
        'name' => env('APP_NAME', 'Okyema'),
        'tagline' => 'Your intelligent chief of staff',
        'powered_by' => 'Powered by Regno AI',

        // Where deployment-success emails are sent.
        'deploy_email' => env('DEPLOY_EMAIL', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Workspace contexts
    |--------------------------------------------------------------------------
    | Every stored object carries a workspace identifier unless it is
    | explicitly global. These defaults are created for the first user. The
    | UI always shows the active context; "All contexts" is owner-only.
    */
    'workspaces' => [
        'defaults' => [
            ['key' => 'REGNO', 'name' => 'Regno'],
            ['key' => 'LAUNCHPAD', 'name' => 'Launchpad'],
            ['key' => 'PERSONAL', 'name' => 'Personal'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Money handling
    |--------------------------------------------------------------------------
    | Every amount is stored as an integer number of minor units (pence for
    | GBP). Never use floating-point arithmetic for money.
    */
    'currency' => [
        'code' => env('OKYEMA_CURRENCY', 'GBP'),
        'symbol' => env('OKYEMA_CURRENCY_SYMBOL', '£'),
        'minor_unit' => 100,
    ],

    /*
    |--------------------------------------------------------------------------
    | Outbound HTTP
    |--------------------------------------------------------------------------
    | PHP on Windows ships without a CA certificate bundle, which makes every
    | Guzzle/Socialite call fail with "cURL error 60". Leave this empty to
    | auto-detect a bundle already on the machine and set CA_BUNDLE_PATH when
    | you want to be explicit.
    */
    'http' => [
        'ca_bundle' => env('CA_BUNDLE_PATH'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Regno AI
    |--------------------------------------------------------------------------
    | OpenAI-compatible chat-completions endpoint. Disabled by default — the
    | briefing and advisors fall back to deterministic answers.
    */
    'ai' => [
        'enabled' => env('AI_ENABLED', false),
        'provider' => env('AI_PROVIDER', 'openai'),
        'api_key' => env('AI_API_KEY', ''),
        'model' => env('AI_MODEL', ''),
        'vision_model' => env('AI_VISION_MODEL', ''),
        'base_url' => env('AI_BASE_URL', ''),
        'timeout_seconds' => env('AI_TIMEOUT_SECONDS', 30),
        'max_tokens' => env('AI_MAX_TOKENS', 2000),
        'temperature' => env('AI_TEMPERATURE', 0.2),
    ],

    /*
    |--------------------------------------------------------------------------
    | Receipt capture (Milestone 3)
    |--------------------------------------------------------------------------
    */
    'receipts' => [
        'enabled' => env('RECEIPT_SCAN_ENABLED', true),

        // Laravel's max: validation for the uploaded photo, in kilobytes.
        'max_kb' => env('RECEIPT_SCAN_MAX_KB', 8192),
    ],
];
