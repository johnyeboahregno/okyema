<?php

declare(strict_types=1);

return [

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],

    'microsoft' => [
        'client_id' => env('MICROSOFT_CLIENT_ID'),
        'client_secret' => env('MICROSOFT_CLIENT_SECRET'),
        'redirect' => env('MICROSOFT_REDIRECT_URI'),
    ],

    // Calendar connectors are separate OAuth clients from the sign-in flow
    // (ADR-002 / docs/connectors). They never reuse the sign-in credentials.
    'connectors' => [
        'google' => [
            'client_id' => env('GOOGLE_CALENDAR_CLIENT_ID'),
            'client_secret' => env('GOOGLE_CALENDAR_CLIENT_SECRET'),
            'redirect' => env('GOOGLE_CALENDAR_REDIRECT_URI'),
        ],
        'microsoft' => [
            'client_id' => env('MICROSOFT_CALENDAR_CLIENT_ID'),
            'client_secret' => env('MICROSOFT_CALENDAR_CLIENT_SECRET'),
            'redirect' => env('MICROSOFT_CALENDAR_REDIRECT_URI'),
        ],
        'notion' => [
            'client_id' => env('NOTION_CLIENT_ID'),
            'client_secret' => env('NOTION_CLIENT_SECRET'),
            'redirect' => env('NOTION_REDIRECT_URI'),
        ],
    ],

];
