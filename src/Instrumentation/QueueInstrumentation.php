<?php

declare(strict_types=1);

namespace ActiveEngagement\Telemetry\Instrumentation;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Queue\Events\Looping;
use Illuminate\Queue\Events\WorkerStopping;
use Keepsuit\LaravelOpenTelemetry\Facades\Tracer;
use Keepsuit\LaravelOpenTelemetry\Instrumentation\QueueInstrumentation as KeepsuitQueueInstrumentation;
use OpenTelemetry\API\Common\Time\Clock;
use OpenTelemetry\API\Common\Time\ClockInterface;
use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\Context\ScopeInterface;
use OpenTelemetry\SDK\Logs\LoggerProviderInterface;
use OpenTelemetry\SDK\Trace\TracerProviderInterface;
use Override;
use Throwable;

class QueueInstrumentation extends KeepsuitQueueInstrumentation
{
    /**
     * @var array{0: SpanInterface, 1: ?ScopeInterface}|null
     */
    protected ?array $failedJob = null;

    protected bool $hasUnexportedJobs = false;

    protected ?int $lastExportedAt = null;

    protected int $minimumSecondsBetweenExports = 5;

    public function __construct(
        protected Dispatcher $events,
        protected Application $app,
    ) {}

    /**
     * @param  array<mixed>  $options
     */
    #[Override]
    public function register(array $options): void
    {
        parent::register($options);

        $this->events->listen(Looping::class, $this->exportFinishedJobs(...));
        $this->events->listen(WorkerStopping::class, $this->exportBeforeStopping(...));

        $this->app->terminating($this->endFailedJob(...));
    }

    public function exportFinishedJobs(): void
    {
        $this->endFailedJob();

        if ($this->hasUnexportedJobs && ! $this->exportedRecently()) {
            $this->export();
        }
    }

    public function exportBeforeStopping(): void
    {
        $this->endFailedJob();
        $this->export();
    }

    public function endFailedJob(): void
    {
        if ($this->failedJob === null) {
            return;
        }

        [$span, $scope] = $this->failedJob;

        $this->failedJob = null;

        $scope?->detach();
        $span->end();
    }

    #[Override]
    protected function finishActiveJobSpan(?Throwable $exception = null): void
    {
        $this->hasUnexportedJobs = true;

        if (! $exception instanceof Throwable) {
            parent::finishActiveJobSpan();

            return;
        }

        $this->endFailedJob();

        $this->failedJob = [Tracer::activeSpan()->setStatus(StatusCode::STATUS_ERROR), Tracer::activeScope()];
    }

    protected function exportedRecently(): bool
    {
        return $this->lastExportedAt !== null
            && Clock::getDefault()->now() - $this->lastExportedAt < $this->minimumSecondsBetweenExports * ClockInterface::NANOS_PER_SECOND;
    }

    protected function export(): void
    {
        $this->hasUnexportedJobs = false;
        $this->lastExportedAt = Clock::getDefault()->now();

        rescue(function (): void {
            $tracerProvider = Globals::tracerProvider();
            $loggerProvider = Globals::loggerProvider();

            if ($tracerProvider instanceof TracerProviderInterface) {
                $tracerProvider->forceFlush();
            }

            if ($loggerProvider instanceof LoggerProviderInterface) {
                $loggerProvider->forceFlush();
            }
        }, report: false);
    }
}
