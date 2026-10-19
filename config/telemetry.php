<?php

use Actengage\Telemetry\Watchers;

return [
    'enabled' => env('TELEMETRY_ENABLED', true),

    'watchers' => [
        Watchers\LogWatcher::class => [
            'level' => env('TELEMETRY_LOG_LEVEL', env('LOG_LEVEL', 'debug')),
        ],
    ],
];
