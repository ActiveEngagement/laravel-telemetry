<?php

use ActiveEngagement\Telemetry\Watchers\LogWatcher;
use Illuminate\Support\Facades\Log;

it('registers no watchers when TELEMETRY_ENABLED is false', function (): void {
    refreshWithEnv('TELEMETRY_ENABLED', 'false');

    Log::error('not recorded');

    expect(exportedLogs())->toBeEmpty();
});

it('registers a watcher listed by class name only', function (): void {
    config()->set('telemetry.watchers', [LogWatcher::class]);

    rewatch();
    Log::debug('recorded');

    expect(exportedLogs())->toHaveCount(1);
});

it('passes the configured options to the watcher', function (): void {
    config()->set('telemetry.watchers', [LogWatcher::class => ['level' => 'error']]);

    rewatch();
    Log::warning('not recorded');
    Log::error('recorded');

    expect(exportedLogs())->toHaveCount(1);
});

it('skips a watcher that is set to false', function (): void {
    config()->set('telemetry.watchers', [LogWatcher::class => false]);

    rewatch();
    Log::error('not recorded');

    expect(exportedLogs())->toBeEmpty();
});

it('skips a watcher whose enabled option is false', function (): void {
    config()->set('telemetry.watchers', [LogWatcher::class => ['enabled' => false]]);

    rewatch();
    Log::error('not recorded');

    expect(exportedLogs())->toBeEmpty();
});

it('rejects a class that is not a watcher', function (): void {
    config()->set('telemetry.watchers', [stdClass::class => true]);

    rewatch();
})->throws(InvalidArgumentException::class);
