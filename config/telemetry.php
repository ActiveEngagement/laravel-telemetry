<?php

use Actengage\Telemetry\Logging\OpenTelemetryHandler;

return [
    'log_level' => env('TELEMETRY_LOG_LEVEL', env('LOG_LEVEL', 'debug')),

    'log_handler' => OpenTelemetryHandler::class,
];
