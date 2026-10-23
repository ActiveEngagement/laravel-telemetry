<?php

declare(strict_types=1);

namespace ActiveEngagement\Telemetry\Instrumentation;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Queue\Events\Looping;
use Illuminate\Queue\Events\WorkerStopping;
use Keepsuit\LaravelOpenTelemetry\Facades\Tracer;
use Keepsuit\LaravelOpenTelemetry\Instrumentation\QueueInstrumentation as KeepsuitQueueInstrumentation;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\Context\ScopeInterface;
use Override;
use Throwable;

class QueueInstrumentation extends KeepsuitQueueInstrumentation
{
    /**
     * @var array{0: SpanInterface, 1: ?ScopeInterface}|null
     */
    protected ?array $failedJob = null;

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

        $this->events->listen([Looping::class, WorkerStopping::class], $this->endFailedJob(...));

        $this->app->terminating($this->endFailedJob(...));
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
        if (! $exception instanceof Throwable) {
            parent::finishActiveJobSpan();

            return;
        }

        $this->endFailedJob();

        $this->failedJob = [Tracer::activeSpan()->setStatus(StatusCode::STATUS_ERROR), Tracer::activeScope()];
    }
}
