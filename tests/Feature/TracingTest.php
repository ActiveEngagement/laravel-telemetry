<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\SDK\Logs\ReadableLogRecord;
use OpenTelemetry\SDK\Trace\EventInterface;
use OpenTelemetry\SDK\Trace\ImmutableSpan;
use Symfony\Component\HttpFoundation\StreamedResponse;
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

function spanNamed(string $name): ?ImmutableSpan
{
    return collect(exportedSpans())->first(fn (ImmutableSpan $span): bool => $span->getName() === $name);
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

it('traces a streamed response body in a child span of the request span', function (): void {
    Route::get('/chats/{conversation}', fn () => response()->stream(function (): void {
        Log::info('streaming the reply');
        resolve(TracerInterface::class)->spanBuilder('invoke_agent savant')->startSpan()->end();

        echo 'Hello';
    }));

    expect($this->get('/chats/1')->streamedContent())->toBe('Hello');

    $request = spanOfKind(SpanKind::KIND_SERVER);
    $stream = spanNamed('GET /chats/{conversation} stream');

    expect($stream?->getTraceId())->toBe($request?->getTraceId())
        ->and($stream?->getParentSpanId())->toBe($request?->getSpanId())
        ->and($stream?->getStartEpochNanos())->toBeGreaterThanOrEqual($request?->getEndEpochNanos())
        ->and($stream?->getAttributes()->get('http.route'))->toBe('/chats/{conversation}')
        ->and(spanNamed('invoke_agent savant')?->getParentSpanId())->toBe($stream?->getSpanId())
        ->and(logWithBody('streaming the reply')?->getSpanContext()->getSpanId())->toBe($stream?->getSpanId());
});

it('records an exception in a streamed response body on the stream span', function (): void {
    Route::get('/chats/{conversation}', fn () => response()->stream(fn () => throw new RuntimeException('The stream failed.')));

    $response = $this->get('/chats/1');

    expect(fn () => $response->baseResponse->sendContent())->toThrow(RuntimeException::class, 'The stream failed.');

    $stream = spanNamed('GET /chats/{conversation} stream');

    expect($stream?->getStatus()->getCode())->toBe(StatusCode::STATUS_ERROR)
        ->and(recordedExceptions($stream))->toBe(['The stream failed.'])
        ->and(spanOfKind(SpanKind::KIND_SERVER)?->getStatus()->getCode())->toBe(StatusCode::STATUS_OK);
});

it('leaves a generator stream for Octane to send', function (): void {
    Route::get('/chats/{conversation}', fn (): StreamedResponse => (new StreamedResponse)->setCallback(fn () => yield 'Hello'));

    $body = $this->get('/chats/1')->baseResponse->getCallback();

    expect(iterator_to_array($body()))->toBe(['Hello']);
});

it('records an exception in a queued job on the job span', function (): void {
    $this->loadLaravelMigrations();
    config()->set(['queue.default' => 'database', 'queue.failed.driver' => 'null']);

    dispatch(new ImportOrders);
    $this->artisan('queue:work', ['--once' => true, '--stop-when-empty' => true]);
    $this->app->terminate();

    $span = spanOfKind(SpanKind::KIND_CONSUMER);

    expect($span?->getStatus()->getCode())->toBe(StatusCode::STATUS_ERROR)
        ->and(recordedExceptions($span))->toBe(['The import failed.'])
        ->and(logWithBody('importing orders')?->getSpanContext()->getTraceId())->toBe($span?->getTraceId())
        ->and(logWithBody('The import failed.')?->getSpanContext()->getTraceId())->toBe($span?->getTraceId());
});

it('ends a failed job span when the worker stops', function (): void {
    $this->loadLaravelMigrations();
    config()->set(['queue.default' => 'database', 'queue.failed.driver' => 'null']);

    dispatch(new ImportOrders);
    $this->artisan('queue:work', ['--max-jobs' => 1, '--stop-when-empty' => true]);

    expect(spanOfKind(SpanKind::KIND_CONSUMER)?->getStatus()->getCode())->toBe(StatusCode::STATUS_ERROR)
        ->and(logWithBody('The import failed.')?->getSpanContext()->getTraceId())->toBe(spanOfKind(SpanKind::KIND_CONSUMER)?->getTraceId());
});
