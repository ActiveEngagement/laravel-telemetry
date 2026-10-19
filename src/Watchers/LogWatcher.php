<?php

namespace Actengage\Telemetry\Watchers;

use Actengage\Telemetry\Contracts\ContextResolver;
use Actengage\Telemetry\Telemetry;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Log\Events\MessageLogged;
use InvalidArgumentException;
use OpenTelemetry\API\Common\Time\Clock;
use OpenTelemetry\API\Logs\LoggerInterface;
use OpenTelemetry\API\Logs\Severity;
use Throwable;
use ValueError;

class LogWatcher extends Watcher
{
    protected Severity $minimumSeverity;

    /**
     * @param  array<mixed>  $options
     */
    public function __construct(
        protected LoggerInterface $logger,
        protected ContextResolver $contextResolver,
        array $options = [],
    ) {
        parent::__construct($options);

        $this->minimumSeverity = $this->severity($options['level'] ?? 'debug');
    }

    public function register(Application $app): void
    {
        $app->make(Dispatcher::class)->listen(MessageLogged::class, $this->recordLog(...));
    }

    public function recordLog(MessageLogged $event): void
    {
        if (! Telemetry::isRecording()) {
            return;
        }

        Telemetry::withoutRecording(function () use ($event): void {
            try {
                if ($this->shouldRecord($event)) {
                    $this->emit($event);
                }
            } catch (Throwable $throwable) {
                $this->failed($throwable);
            }
        });
    }

    protected function shouldRecord(MessageLogged $event): bool
    {
        return Severity::fromPsr3($event->level)->value >= $this->minimumSeverity->value;
    }

    protected function emit(MessageLogged $event): void
    {
        $builder = $this->logger->logRecordBuilder()
            ->setTimestamp($this->timestamp())
            ->setSeverityNumber(Severity::fromPsr3($event->level))
            ->setSeverityText($event->level)
            ->setBody($event->message)
            ->setAttributes($this->attributes($event));

        $exception = $this->exception($event);

        if ($exception instanceof Throwable) {
            $builder->setException($exception);
        }

        $builder->emit();
    }

    protected function timestamp(): int
    {
        return Clock::getDefault()->now();
    }

    /**
     * @return array<mixed>
     */
    protected function attributes(MessageLogged $event): array
    {
        $context = $event->context;

        if ($this->exception($event) instanceof Throwable) {
            unset($context['exception']);
        }

        return $this->contextResolver->resolve($context);
    }

    protected function exception(MessageLogged $event): ?Throwable
    {
        $exception = $event->context['exception'] ?? null;

        return $exception instanceof Throwable ? $exception : null;
    }

    protected function failed(Throwable $throwable): void {}

    protected function severity(mixed $level): Severity
    {
        try {
            return Severity::fromPsr3(is_string($level) ? $level : '');
        } catch (ValueError) {
            throw new InvalidArgumentException('The log watcher level must be a PSR-3 log level.');
        }
    }
}
