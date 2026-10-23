<?php

use ActiveEngagement\Telemetry\Instrumentation\ConsoleInstrumentation;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Keepsuit\LaravelOpenTelemetry\Instrumentation\ConsoleInstrumentation as KeepsuitConsoleInstrumentation;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\SDK\Logs\ReadableLogRecord;
use OpenTelemetry\SDK\Trace\EventInterface;
use OpenTelemetry\SDK\Trace\ImmutableSpan;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

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
    $this->app->terminate();

    expect(commandSpan('reports:send')?->getStatus()->getCode())->toBe(StatusCode::STATUS_ERROR);
});

it('does not trace long-running and heartbeat commands', function (string $command): void {
    Artisan::command($command, fn (): int => 0);

    $this->artisan($command);

    expect(commandSpan($command))->toBeNull();
})->with(['horizon:work', 'octane:start', 'pulse:work', 'telemetry:heartbeat']);

it('links an exception in a command to its span', function (): void {
    Artisan::command('reports:send', fn () => throw new RuntimeException('The mail server is down.'));

    try {
        $this->app->make(Kernel::class)->handle(new ArrayInput(['command' => 'reports:send']), new BufferedOutput);
    } catch (RuntimeException $runtimeException) {
        $this->app->make(ExceptionHandler::class)->report($runtimeException);
    }

    $this->app->terminate();

    $span = commandSpan('reports:send');
    $log = collect(exportedLogs())->first(fn (ReadableLogRecord $log): bool => $log->getBody() === 'The mail server is down.');
    $exceptions = collect($span?->getEvents() ?? [])->filter(fn (EventInterface $event): bool => $event->getName() === 'exception');

    expect($span?->getStatus()->getCode())->toBe(StatusCode::STATUS_ERROR)
        ->and($exceptions)->toHaveCount(1)
        ->and($log?->getSpanContext()->getTraceId())->toBe($span?->getTraceId())
        ->and($log?->getSpanContext()->getSpanId())->toBe($span?->getSpanId());
});
