<?php

namespace Actengage\Telemetry\Logging;

use Actengage\Telemetry\Contracts\ContextResolver;
use Actengage\Telemetry\Telemetry;
use Monolog\Handler\AbstractHandler;
use Monolog\Level;
use Monolog\LogRecord;
use OpenTelemetry\API\Logs\LoggerInterface;
use OpenTelemetry\API\Logs\Severity;
use Throwable;

class OpenTelemetryHandler extends AbstractHandler
{
    public function __construct(
        protected LoggerInterface $logger,
        protected ContextResolver $contextResolver,
        int|string|Level $level = Level::Debug,
    ) {
        parent::__construct($level);
    }

    public function handle(LogRecord $record): bool
    {
        if ($this->isHandling($record) && Telemetry::isRecording()) {
            Telemetry::withoutRecording(fn () => rescue(fn () => $this->emit($record), report: false));
        }

        return false;
    }

    protected function emit(LogRecord $record): void
    {
        $level = $record->level->toPsrLogLevel();

        $builder = $this->logger->logRecordBuilder()
            ->setTimestamp((int) $record->datetime->format('Uu') * 1000)
            ->setSeverityNumber(Severity::fromPsr3($level))
            ->setSeverityText($level)
            ->setBody($record->message)
            ->setAttributes($this->attributes($record));

        $exception = $this->exception($record);

        if ($exception instanceof Throwable) {
            $builder->setException($exception);
        }

        $builder->emit();
    }

    /**
     * @return array<mixed>
     */
    protected function attributes(LogRecord $record): array
    {
        $context = $record->context;

        if ($this->exception($record) instanceof Throwable) {
            unset($context['exception']);
        }

        return $this->contextResolver->resolve([...$context, ...$record->extra]);
    }

    protected function exception(LogRecord $record): ?Throwable
    {
        $exception = $record->context['exception'] ?? null;

        return $exception instanceof Throwable ? $exception : null;
    }
}
