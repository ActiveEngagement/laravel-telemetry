<?php

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\SDK\Logs\ReadableLogRecord;
use OpenTelemetry\SDK\Trace\EventInterface;
use OpenTelemetry\SDK\Trace\ImmutableSpan;
use Tests\Fixtures\ImportOrders;

function spanOfKind(int $kind): ?ImmutableSpan
{
    return collect(exportedSpans())->first(fn (ImmutableSpan $span): bool => $span->getKind() === $kind);
}

/**
 * @return list<string>
 */
function recordedExceptions(?ImmutableSpan $span): array
{
    return collect($span?->getEvents() ?? [])
        ->filter(fn (EventInterface $event): bool => $event->getName() === 'exception')
        ->map(fn (EventInterface $event): string => (string) $event->getAttributes()->get('exception.message'))
        ->values()
        ->all();
}

function logWithBody(string $body): ?ReadableLogRecord
{
    return collect(exportedLogs())->first(fn (ReadableLogRecord $log): bool => $log->getBody() === $body);
}

it('records an exception in an HTTP request on the server span', function (): void {
    Route::get('/checkout', fn () => throw new RuntimeException('The payment failed.'));

    $this->get('/checkout')->assertServerError();

    $span = spanOfKind(SpanKind::KIND_SERVER);

    expect($span?->getStatus()->getCode())->toBe(StatusCode::STATUS_ERROR)
        ->and(recordedExceptions($span))->toContain('The payment failed.')
        ->and(logWithBody('The payment failed.')?->getSpanContext()->getTraceId())->toBe($span?->getTraceId());
});

it('links a log written during a request to the server span', function (): void {
    Route::get('/checkout', function (): string {
        Log::info('charging the card');

        return 'ok';
    });

    $this->get('/checkout')->assertOk();

    $span = spanOfKind(SpanKind::KIND_SERVER);
    $log = logWithBody('charging the card');

    expect($log?->getSpanContext()->getTraceId())->toBe($span?->getTraceId())
        ->and($log?->getSpanContext()->getSpanId())->toBe($span?->getSpanId());
});

it('records an exception in a queued job on the job span', function (): void {
    $this->loadLaravelMigrations();
    config()->set(['queue.default' => 'database', 'queue.failed.driver' => 'null']);

    dispatch(new ImportOrders);
    $this->artisan('queue:work', ['--once' => true, '--stop-when-empty' => true]);

    $span = spanOfKind(SpanKind::KIND_CONSUMER);

    expect($span?->getStatus()->getCode())->toBe(StatusCode::STATUS_ERROR)
        ->and(recordedExceptions($span))->toContain('The import failed.')
        ->and(logWithBody('importing orders')?->getSpanContext()->getTraceId())->toBe($span?->getTraceId());
});
