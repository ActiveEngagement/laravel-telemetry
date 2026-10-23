<?php

declare(strict_types=1);

use ActiveEngagement\Telemetry\Watchers\LogWatcher;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Keepsuit\LaravelOpenTelemetry\Instrumentation\ConsoleInstrumentation;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\SDK\Trace\ImmutableSpan;

it('lets the app override the resource attributes', function (): void {
    $this->refreshWithConfig(['opentelemetry.resource_attributes' => ['deployment.environment.name' => 'staging']]);

    app(TracerInterface::class)->spanBuilder('request')->startSpan()->end();

    expect(exportedSpans()[0]->getResource()->getAttributes()->get('deployment.environment.name'))->toBe('staging');
});

it('lets the app exclude more commands', function (): void {
    $this->refreshWithConfig([
        'opentelemetry.instrumentation.'.ConsoleInstrumentation::class => ['enabled' => true, 'excluded' => ['reports:*']],
    ]);

    $kernel = $this->app->make(Kernel::class);
    $kernel->rerouteSymfonyCommandEvents();
    $kernel->setArtisan(null);

    Artisan::command('reports:send', fn (): int => 0);
    $this->artisan('reports:send');

    expect(collect(exportedSpans())->contains(fn (ImmutableSpan $span): bool => $span->getName() === 'reports:send'))->toBeFalse();
});

it('lets the app change the log level', function (): void {
    $this->refreshWithConfig(['telemetry.watchers' => [LogWatcher::class => ['level' => 'error']]]);

    Log::warning('not recorded');
    Log::error('recorded');

    expect(exportedLogs())->toHaveCount(1);
});
