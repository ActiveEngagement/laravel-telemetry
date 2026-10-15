<?php

namespace Actengage\Telemetry\Logging;

use Keepsuit\LaravelOpenTelemetry\Facades\Logger;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\LogRecord;

class OpenTelemetryHandler extends AbstractProcessingHandler
{
    protected function write(LogRecord $record): void
    {
        Logger::log($record->level->toPsrLogLevel(), $record->message, $record->context);
    }
}
