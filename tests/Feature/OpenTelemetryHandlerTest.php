<?php

use Illuminate\Support\Facades\Log;
use Keepsuit\LaravelOpenTelemetry\Facades\Logger;

it('forwards log records to the OpenTelemetry logger', function (): void {
    Logger::shouldReceive('log')->once()->with('warning', 'disk almost full', ['disk' => 'sda']);

    Log::channel('otlp')->warning('disk almost full', ['disk' => 'sda']);
});
