<?php

declare(strict_types=1);

use ActiveEngagement\Telemetry\Watchers;

return [
    'enabled' => env('TELEMETRY_ENABLED', true),

    'deployment' => env('LARAVEL_CLOUD_DEPLOY_UUID', env('FORGE_DEPLOY_COMMIT', env('ATHENA_DEPLOY_COMMIT'))),

    'watchers' => [
        Watchers\AiWatcher::class => env('TELEMETRY_AI_WATCHER', true),

        Watchers\HeartbeatWatcher::class => [
            'scheduler' => env('TELEMETRY_SCHEDULER_HEARTBEAT', true),

            // allowed options: 'none', 'queue', 'horizon'
            'queue' => env('TELEMETRY_QUEUE_MONITOR', 'none'),
        ],

        Watchers\LogWatcher::class => [
            'level' => env('TELEMETRY_LOG_LEVEL', env('LOG_LEVEL', 'debug')),
        ],
    ],
];
