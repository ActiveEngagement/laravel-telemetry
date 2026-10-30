<?php

declare(strict_types=1);

use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\Looping;
use Illuminate\Support\Facades\Event;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\SDK\Logs\LogRecordExporterInterface;
use OpenTelemetry\SDK\Logs\ReadableLogRecord;
use OpenTelemetry\SDK\Trace\ImmutableSpan;
use OpenTelemetry\SDK\Trace\SpanExporterInterface;
use Tests\Fixtures\ImportOrders;
use Tests\Fixtures\ShipOrder;

beforeEach(function (): void {
    $this->loadLaravelMigrations();
    config()->set(['queue.default' => 'database', 'queue.failed.driver' => 'null']);
});

/**
 * @return list<string>
 */
function sentWithoutFlush(): array
{
    $jobs = collect(resolve(SpanExporterInterface::class)->getStorage())
        ->filter(fn (ImmutableSpan $span): bool => $span->getKind() === SpanKind::KIND_CONSUMER)
        ->map(fn (ImmutableSpan $span): string => "{$span->getName()}: {$span->getStatus()->getCode()}");

    $logs = collect(resolve(LogRecordExporterInterface::class)->getStorage())
        ->map(fn (ReadableLogRecord $log): string => (string) $log->getBody());

    return [...$jobs, ...$logs];
}

/**
 * @param  array<string, mixed>  $options
 * @return list<list<string>>
 */
function sentAtEachWorkerLoop(array $options = []): array
{
    $sent = [];

    Event::listen(Looping::class, function () use (&$sent): void {
        $sent[] = sentWithoutFlush();
    });

    test()->artisan('queue:work', ['--sleep' => 0, '--stop-when-empty' => true, ...$options]);

    return $sent;
}

it('exports a job before the worker waits for the next one', function (): void {
    dispatch(new ShipOrder(1));

    expect(sentAtEachWorkerLoop()[1])->toBe(['process default: Unset', 'shipping order 1']);
});

it('exports a failed job before the worker waits for the next one', function (): void {
    dispatch(new ImportOrders);

    expect(sentAtEachWorkerLoop()[1])->toBe(['process default: Error', 'importing orders', 'The import failed.']);
});

it('waits five seconds between exports while jobs keep coming', function (): void {
    dispatch(new ShipOrder(1));
    dispatch(new ShipOrder(2));

    expect(sentAtEachWorkerLoop()[2])->toBe(['process default: Unset', 'shipping order 1']);
});

it('exports the next job once five seconds have passed', function (): void {
    dispatch(new ShipOrder(1));
    dispatch(new ShipOrder(2));
    Event::listen(JobProcessed::class, fn () => $this->travel(5)->seconds());

    expect(sentAtEachWorkerLoop()[2])->toBe([
        'process default: Unset',
        'process default: Unset',
        'shipping order 1',
        'shipping order 2',
    ]);
});

it('exports the last job when the worker stops', function (): void {
    dispatch(new ShipOrder(1));

    $this->artisan('queue:work', ['--max-jobs' => 1]);

    expect(sentWithoutFlush())->toBe(['process default: Unset', 'shipping order 1']);
});
