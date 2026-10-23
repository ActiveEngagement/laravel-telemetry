<?php

use Actengage\Telemetry\Instrumentation\ConsoleInstrumentation;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Keepsuit\LaravelOpenTelemetry\Instrumentation\ConsoleInstrumentation as KeepsuitConsoleInstrumentation;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\SDK\Trace\ImmutableSpan;

beforeEach(function (): void {
    $kernel = $this->app->make(Kernel::class);

    $kernel->rerouteSymfonyCommandEvents();
    $kernel->setArtisan(null);
});

function commandSpan(string $command): ?ImmutableSpan
{
    return collect(exportedSpans())->first(fn (ImmutableSpan $span): bool => $span->getName() === $command);
}

it('replaces the keepsuit console instrumentation', function (): void {
    expect(resolve(KeepsuitConsoleInstrumentation::class))->toBeInstanceOf(ConsoleInstrumentation::class);
});

it('traces an artisan command', function (): void {
    Artisan::command('reports:send', fn (): int => 0);

    $this->artisan('reports:send')->assertSuccessful();

    expect(commandSpan('reports:send')?->getAttributes()->get('console.command'))->toBe('reports:send')
        ->and(commandSpan('reports:send')?->getStatus()->getCode())->toBe(StatusCode::STATUS_OK);
});

it('links the logs a command writes to its span', function (): void {
    Artisan::command('reports:send', fn () => Log::info('sending reports'));

    $this->artisan('reports:send');

    $span = commandSpan('reports:send');

    expect(exportedLogs()[0]->getSpanContext()->getTraceId())->toBe($span?->getTraceId())
        ->and(exportedLogs()[0]->getSpanContext()->getSpanId())->toBe($span?->getSpanId());
});

it('marks a failed command as an error', function (): void {
    Artisan::command('reports:send', fn (): int => 1);

    $this->artisan('reports:send')->assertFailed();

    expect(commandSpan('reports:send')?->getStatus()->getCode())->toBe(StatusCode::STATUS_ERROR);
});

it('does not trace long-running and heartbeat commands', function (string $command): void {
    Artisan::command($command, fn (): int => 0);

    $this->artisan($command);

    expect(commandSpan($command))->toBeNull();
})->with(['horizon:work', 'octane:start', 'pulse:work', 'telemetry:heartbeat']);

it('does not trace the commands in the excluded option', function (): void {
    $instrumentation = new class extends ConsoleInstrumentation
    {
        public function records(string $command): bool
        {
            return $this->shouldRecord($command);
        }
    };

    $instrumentation->register(['excluded' => ['app:listen-*']]);

    expect($instrumentation->records('app:listen-websocket'))->toBeFalse()
        ->and($instrumentation->records('queue:work'))->toBeFalse()
        ->and($instrumentation->records('reports:send'))->toBeTrue();
});
