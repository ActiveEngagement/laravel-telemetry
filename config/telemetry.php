<?php

use Actengage\Telemetry\Watchers;

return [
    'enabled' => env('TELEMETRY_ENABLED', true),

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
