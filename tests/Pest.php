<?php

use Actengage\Telemetry\Logging\SendToOpenTelemetry;
use Actengage\Telemetry\Telemetry;
use Keepsuit\LaravelOpenTelemetry\Facades\OpenTelemetry;
use OpenTelemetry\SDK\Common\Export\InMemoryStorageManager;
use OpenTelemetry\SDK\Logs\LogRecordExporterInterface;
use OpenTelemetry\SDK\Logs\ReadableLogRecord;
use OpenTelemetry\SDK\Trace\ImmutableSpan;
use OpenTelemetry\SDK\Trace\SpanExporterInterface;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->beforeEach(function (): void {
        InMemoryStorageManager::logs()->exchangeArray([]);
        InMemoryStorageManager::spans()->exchangeArray([]);
    })
    ->in('Feature');

/**
 * @return list<ReadableLogRecord>
 */
function exportedLogs(): array
{
    OpenTelemetry::flush();

    return array_values(app(LogRecordExporterInterface::class)->getStorage()->getArrayCopy());
}

/**
 * @return list<ImmutableSpan>
 */
function exportedSpans(): array
{
    OpenTelemetry::flush();

    return array_values(app(SpanExporterInterface::class)->getStorage()->getArrayCopy());
}

/**
 * @return list<ImmutableSpan>
 */
function aiSpans(string $operation): array
{
    return array_values(array_filter(
        exportedSpans(),
        fn (ImmutableSpan $span): bool => $span->getAttributes()->get('gen_ai.operation.name') === $operation,
    ));
}

function rewatch(): void
{
    $channel = config('logging.default');
    $taps = (array) config("logging.channels.{$channel}.tap", []);

    config()->set("logging.channels.{$channel}.tap", array_values(array_filter(
        $taps,
        fn (mixed $tap): bool => ! is_string($tap) || ! str_starts_with($tap, SendToOpenTelemetry::class),
    )));
    app('log')->forgetChannel($channel);

    Telemetry::registerWatchers(app());
}

function refreshWithEnv(string $name, string $value): void
{
    putenv("{$name}={$value}");

    try {
        test()->refreshApplication();
    } finally {
        putenv($name);
    }
}
