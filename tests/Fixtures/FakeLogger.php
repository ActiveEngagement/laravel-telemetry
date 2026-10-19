<?php

namespace Tests\Fixtures;

use Closure;
use OpenTelemetry\API\Logs\LoggerInterface;
use OpenTelemetry\API\Logs\LogRecord;
use OpenTelemetry\API\Logs\LogRecordBuilderInterface;
use OpenTelemetry\Context\ContextInterface;
use OpenTelemetry\SDK\Logs\LogRecordBuilder;

class FakeLogger implements LoggerInterface
{
    /**
     * @var list<LogRecord>
     */
    public array $records = [];

    public function __construct(protected ?Closure $onEmit = null) {}

    public function emit(LogRecord $logRecord): void
    {
        $this->records[] = $logRecord;

        if ($this->onEmit instanceof Closure) {
            ($this->onEmit)($logRecord);
        }
    }

    public function logRecordBuilder(): LogRecordBuilderInterface
    {
        return new LogRecordBuilder($this);
    }

    public function isEnabled(?ContextInterface $context = null, ?int $severityNumber = null, ?string $eventName = null): bool
    {
        return true;
    }
}
