<?php

declare(strict_types=1);

use ActiveEngagement\Telemetry\Watchers\AiWatcher;
use ActiveEngagement\Telemetry\Watchers\HeartbeatWatcher;
use ActiveEngagement\Telemetry\Watchers\LogWatcher;

return [
    'enabled' => env('TELEMETRY_ENABLED', true),

    'deployment' => env('LARAVEL_CLOUD_DEPLOY_UUID', env('FORGE_DEPLOY_COMMIT', env('ATHENA_DEPLOY_COMMIT'))),

    'sensitive_query_parameters' => [
        'code',
        'state',
        'access_token',
        'refresh_token',
        'id_token',
        'token',
        'client_secret',
        'password',
        'api_key',
        'x-amz-signature',
        'x-amz-credential',
        'x-amz-security-token',
    ],

    'excluded_paths' => [
        '/up',
    ],

    'browser' => [
        'enabled' => env('TELEMETRY_BROWSER_ENABLED', true),

        'url' => env('TELEMETRY_BROWSER_URL', env('OTEL_EXPORTER_OTLP_ENDPOINT')),
        'api_key' => env('TELEMETRY_BROWSER_API_KEY'),

        'console_capture' => env('TELEMETRY_BROWSER_CONSOLE_CAPTURE', true),

        'replay' => [
            'enabled' => env('TELEMETRY_BROWSER_REPLAY', true),
            'mask_all_inputs' => true,
            'block_selector' => 'iframe, [data-replay-block]',
        ],
    ],

    'watchers' => [
        AiWatcher::class => env('TELEMETRY_AI_WATCHER', true),

        HeartbeatWatcher::class => [
            'scheduler' => env('TELEMETRY_SCHEDULER_HEARTBEAT', true),

            // allowed options: 'none', 'queue', 'horizon'
            'queue' => env('TELEMETRY_QUEUE_MONITOR', 'none'),
        ],

        LogWatcher::class => [
            'level' => env('TELEMETRY_LOG_LEVEL', env('LOG_LEVEL', 'debug')),
        ],
    ],
];
