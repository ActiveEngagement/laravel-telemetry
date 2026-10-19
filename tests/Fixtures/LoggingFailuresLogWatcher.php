<?php

namespace Tests\Fixtures;

use Actengage\Telemetry\Watchers\LogWatcher;
use Illuminate\Support\Facades\Log;
use Throwable;

class LoggingFailuresLogWatcher extends LogWatcher
{
    protected function failed(Throwable $throwable): void
    {
        Log::error('Telemetry failed', ['exception' => $throwable]);
    }
}
