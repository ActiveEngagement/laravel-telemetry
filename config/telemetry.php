<?php

declare(strict_types=1);

use ActiveEngagement\Telemetry\Watchers\AiWatcher;
use ActiveEngagement\Telemetry\Watchers\HeartbeatWatcher;
use ActiveEngagement\Telemetry\Watchers\LogWatcher;

return [
    'enabled' => env('TELEMETRY_ENABLED', true),

    'deployment' => env('LARAVEL_CLOUD_DEPLOY_UUID', env('FORGE_DEPLOY_COMMIT', env('ATHENA_DEPLOY_COMMIT'))),

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
