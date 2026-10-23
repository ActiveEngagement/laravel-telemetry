<?php

namespace ActiveEngagement\Telemetry\Listeners;

use ActiveEngagement\Telemetry\Contracts\ContextResolver;
use ActiveEngagement\Telemetry\Contracts\HasTelemetryAttributes;
use ActiveEngagement\Telemetry\Events\AiSpanStarted;
use ActiveEngagement\Telemetry\Support\ActiveSpans;
use Closure;
use Illuminate\Contracts\Events\Dispatcher;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\TracerInterface;

abstract class AiEventSubscriber
{
    /**
     * @var array<class-string, string>
     */
    protected array $listen = [];

    public function __construct(
        protected TracerInterface $tracer,
        protected ActiveSpans $spans,
        protected ContextResolver $contextResolver,
        protected Dispatcher $events,
    ) {}

    /**
     * @return array<class-string, Closure(object): void>
     */
    public function subscribe(Dispatcher $events): array
    {
        return array_map(
            fn (string $method): Closure => function (object $event) use ($method): void {
                rescue(fn () => $this->{$method}($event), report: false);
            },
            $this->listen,
        );
    }

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
