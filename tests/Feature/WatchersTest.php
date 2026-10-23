<?php

declare(strict_types=1);

use ActiveEngagement\Telemetry\Watchers\LogWatcher;
use Illuminate\Support\Facades\Exceptions;
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

it('reports a class that is not a watcher and registers the other watchers', function (): void {
    Exceptions::fake();
    config()->set('telemetry.watchers', [stdClass::class => true, LogWatcher::class => ['level' => 'debug']]);

    rewatch();
    Log::error('recorded');

    Exceptions::assertReported(InvalidArgumentException::class);
    expect(exportedLogs())->toHaveCount(1);
});

it('keeps the app booting when a watcher option is invalid', function (): void {
    refreshWithEnv('TELEMETRY_QUEUE_MONITOR', 'cloud');

    Log::error('recorded');

    expect(exportedLogs())->toHaveCount(1)
        ->and(exportedLogs()[0]->getBody())->toBe('recorded');
});
