<?php

namespace ActiveEngagement\Telemetry\Support;

use ActiveEngagement\Telemetry\Events\HeartbeatRecorded;
use ActiveEngagement\Telemetry\Telemetry;
use Illuminate\Contracts\Events\Dispatcher;
use OpenTelemetry\API\Common\Time\Clock;
use OpenTelemetry\API\Logs\LoggerInterface;
use OpenTelemetry\API\Logs\Severity;

class Heartbeat
{
    public function __construct(
        protected LoggerInterface $logger,
        protected Dispatcher $events,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function record(string $name, array $attributes = []): void
    {
        if (! Telemetry::isRecording()) {
            return;
        }

        $this->logger->logRecordBuilder()
            ->setTimestamp(Clock::getDefault()->now())
            ->setSeverityNumber(Severity::INFO)
            ->setSeverityText('info')
            ->setBody($name)
            ->setAttributes($attributes)
            ->emit();

        $this->events->dispatch(new HeartbeatRecorded($name, $attributes));
    }
}
