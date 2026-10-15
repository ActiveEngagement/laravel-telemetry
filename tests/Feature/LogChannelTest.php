<?php

use Actengage\Telemetry\Logging\OpenTelemetryHandler;
use Actengage\Telemetry\TelemetryServiceProvider;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Monolog\Handler\TestHandler;

it('registers the otlp log channel with the OpenTelemetry handler', function (): void {
    $handlers = Log::channel('otlp')->getLogger()->getHandlers();

    expect($handlers)->toHaveCount(1)
        ->and($handlers[0])->toBeInstanceOf(OpenTelemetryHandler::class);
});

it('uses the configured log handler', function (): void {
    config()->set('telemetry.log_handler', TestHandler::class);
    config()->set('logging.channels', Arr::except(config('logging.channels'), 'otlp'));

    $this->app->register(TelemetryServiceProvider::class, force: true);

    expect(Log::channel('otlp')->getLogger()->getHandlers()[0])->toBeInstanceOf(TestHandler::class);
});

it('defaults the log level to debug', function (): void {
    expect(config('logging.channels.otlp.level'))->toBe('debug');
});

it('reads the log level from TELEMETRY_LOG_LEVEL', function (): void {
    putenv('TELEMETRY_LOG_LEVEL=warning');

    try {
        $this->refreshApplication();
    } finally {
        putenv('TELEMETRY_LOG_LEVEL');
    }

    expect(config('logging.channels.otlp.level'))->toBe('warning');
});

it('falls back to LOG_LEVEL', function (): void {
    putenv('LOG_LEVEL=error');

    try {
        $this->refreshApplication();
    } finally {
        putenv('LOG_LEVEL');
    }

    expect(config('logging.channels.otlp.level'))->toBe('error');
});

it('keeps an otlp channel that the app defines', function (): void {
    $channel = ['driver' => 'single', 'path' => storage_path('logs/otlp.log')];

    config()->set('logging.channels.otlp', $channel);

    $this->app->register(TelemetryServiceProvider::class, force: true);

    expect(config('logging.channels.otlp'))->toBe($channel);
});

it('publishes the config file', function (): void {
    $paths = ServiceProvider::pathsToPublish(TelemetryServiceProvider::class, 'telemetry-config');

    expect(array_map(realpath(...), array_keys($paths)))->toBe([realpath(__DIR__.'/../../config/telemetry.php')])
        ->and(array_values($paths))->toBe([config_path('telemetry.php')]);
});
