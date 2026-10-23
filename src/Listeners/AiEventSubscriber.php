<?php

namespace Actengage\Telemetry\Listeners;

use Actengage\Telemetry\Contracts\ContextResolver;
use Actengage\Telemetry\Contracts\HasTelemetryAttributes;
use Actengage\Telemetry\Events\AiSpanStarted;
use Actengage\Telemetry\Support\ActiveSpans;
use Illuminate\Contracts\Events\Dispatcher;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\TracerInterface;

abstract class AiEventSubscriber
{
    public function __construct(
        protected TracerInterface $tracer,
        protected ActiveSpans $spans,
        protected ContextResolver $contextResolver,
        protected Dispatcher $events,
    ) {}

    /**
     * @return array<class-string, string>
     */
    abstract public function subscribe(Dispatcher $events): array;

    protected function openSpan(string $key, SpanInterface $span, object $source): void
    {
        $this->spans->open($key, $span);

        $this->events->dispatch(new AiSpanStarted($span, $source));
    }

    /**
     * @return array<non-empty-string, bool|int|float|string|array<mixed>|null>
     */
    protected function classAttributes(object $target): array
    {
        return $target instanceof HasTelemetryAttributes ? $target->telemetryAttributes() : [];
    }

    /**
     * @param  array<non-empty-string, bool|int|float|string|array<mixed>|null>  $attributes
     * @return array<non-empty-string, bool|int|float|string|array<mixed>>
     */
    protected function withoutNulls(array $attributes): array
    {
        return array_filter($attributes, fn (bool|int|float|string|array|null $value): bool => $value !== null);
    }
}
