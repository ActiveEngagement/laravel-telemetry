<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Keepsuit\LaravelOpenTelemetry\Instrumentation\HttpServerInstrumentation;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\SDK\Trace\ImmutableSpan;

function serverSpanCount(): int
{
    return collect(exportedSpans())->filter(fn (ImmutableSpan $span): bool => $span->getKind() === SpanKind::KIND_SERVER)->count();
}

it('does not trace the health route', function (): void {
    Route::get('/up', fn (): array => DB::select('select 1'));

    $this->get('/up')->assertOk();

    expect(exportedSpans())->toBeEmpty();
});

it('traces the health route when the app removes it from its telemetry config', function (): void {
    $this->refreshWithConfig(['telemetry.excluded_paths' => []]);
    Route::get('/up', fn (): string => 'ok');

    $this->get('/up')->assertOk();

    expect(serverSpanCount())->toBe(1);
});

it('excludes only the paths that the app lists in its telemetry config', function (): void {
    $this->refreshWithConfig(['telemetry.excluded_paths' => ['/health']]);
    Route::get('/health', fn (): string => 'ok');
    Route::get('/up', fn (): string => 'ok');

    $this->get('/health')->assertOk();
    $this->get('/up')->assertOk();

    expect(serverSpanCount())->toBe(1);
});

it('also excludes the paths in the keepsuit config', function (): void {
    $this->refreshWithConfig([
        'opentelemetry.instrumentation.'.HttpServerInstrumentation::class => ['enabled' => true, 'excluded_paths' => ['webhooks/*']],
    ]);
    Route::get('/up', fn (): string => 'ok');
    Route::post('/webhooks/stripe', fn (): string => 'ok');

    $this->get('/up')->assertOk();
    $this->post('/webhooks/stripe')->assertOk();

    expect(exportedSpans())->toBeEmpty();
});
