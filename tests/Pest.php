<?php

use Actengage\Telemetry\Telemetry;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Log\Events\MessageLogged;
use Keepsuit\LaravelOpenTelemetry\Facades\OpenTelemetry;
use OpenTelemetry\SDK\Common\Export\InMemoryStorageManager;
use OpenTelemetry\SDK\Logs\LogRecordExporterInterface;
use OpenTelemetry\SDK\Logs\ReadableLogRecord;
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

function rewatch(): void
{
    app(Dispatcher::class)->forget(MessageLogged::class);

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
