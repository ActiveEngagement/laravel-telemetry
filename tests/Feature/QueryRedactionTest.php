<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Keepsuit\LaravelOpenTelemetry\Instrumentation\HttpServerInstrumentation;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\SDK\Trace\ImmutableSpan;

function recordedQuery(int $kind): mixed
{
    return collect(exportedSpans())
        ->first(fn (ImmutableSpan $span): bool => $span->getKind() === $kind)
        ?->getAttributes()->get('url.query');
}

it('redacts login secrets in the query string of a request', function (): void {
    Route::get('/authenticate', fn (): string => 'ok');

    $this->get('/authenticate?code=01ABC&state=xyz&page=2')->assertOk();

    expect(recordedQuery(SpanKind::KIND_SERVER))->toBe('code=REDACTED&page=2&state=REDACTED');
});

it('redacts signing secrets in the query string of an outgoing request', function (): void {
    Http::fake();
    $span = resolve(TracerInterface::class)->spanBuilder('download')->startSpan();
    $scope = $span->activate();

    try {
        Http::get('https://files.example.com/report.pdf?X-Amz-Credential=AKIA&X-Amz-Signature=abc&response-content-disposition=inline');
    } finally {
        $scope->detach();
        $span->end();
    }

    expect(recordedQuery(SpanKind::KIND_CLIENT))->toBe('X-Amz-Credential=REDACTED&X-Amz-Signature=REDACTED&response-content-disposition=inline');
});

it('redacts only the query parameters that the app lists in its telemetry config', function (): void {
    $this->refreshWithConfig(['telemetry.sensitive_query_parameters' => ['email']]);
    Route::get('/placement', fn (): string => 'ok');

    $this->get('/placement?email=ada%40example.com&code=01ABC')->assertOk();

    expect(recordedQuery(SpanKind::KIND_SERVER))->toBe('code=01ABC&email=REDACTED');
});

it('also redacts the query parameters in the keepsuit config', function (): void {
    $this->refreshWithConfig([
        'opentelemetry.instrumentation.'.HttpServerInstrumentation::class => ['enabled' => true, 'sensitive_query_parameters' => ['email']],
    ]);
    Route::get('/placement', fn (): string => 'ok');

    $this->get('/placement?email=ada%40example.com&code=01ABC')->assertOk();

    expect(recordedQuery(SpanKind::KIND_SERVER))->toBe('code=REDACTED&email=REDACTED');
});
