<?php

namespace Actengage\Telemetry\Listeners;

use Actengage\Telemetry\Listeners\Concerns\DescribesTokenUsage;
use Actengage\Telemetry\Support\ActiveSpans;
use Actengage\Telemetry\Telemetry;
use Illuminate\Contracts\Events\Dispatcher;
use Laravel\Ai\Providers\Provider;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\TracerInterface;

abstract class ProviderEventSubscriber
{
    use DescribesTokenUsage;

    public function __construct(
        protected TracerInterface $tracer,
        protected ActiveSpans $spans,
    ) {}

    /**
     * @return array<class-string, string>
     */
    abstract public function subscribe(Dispatcher $events): array;

    /**
     * @param  non-empty-string  $operation
     * @param  array<non-empty-string, int|string|null>  $attributes
     */
    protected function startSpan(
        string $operation,
        string $invocationId,
        Provider $provider,
        ?string $model = null,
        array $attributes = [],
    ): void {
        if (! Telemetry::isRecording()) {
            return;
        }

        $span = $this->tracer->spanBuilder($model === null ? $operation : $operation.' '.$model)
            ->setSpanKind(SpanKind::KIND_CLIENT)
            ->setAttributes($this->withoutNulls([
                'gen_ai.operation.name' => $operation,
                'gen_ai.provider.name' => $provider->name(),
                'gen_ai.request.model' => $model,
                ...$attributes,
            ]))
            ->startSpan();

        $this->spans->open($invocationId, $span);
    }

    /**
     * @param  array<non-empty-string, int|string|null>  $attributes
     */
    protected function endSpan(string $invocationId, array $attributes = []): void
    {
        $this->spans->close($invocationId)
            ?->setAttributes($this->withoutNulls($attributes))
            ->end();
    }

    /**
     * @param  non-empty-string  $operation
     * @param  array<non-empty-string, int|string|null>  $attributes
     */
    protected function recordSpan(string $operation, string $invocationId, Provider $provider, array $attributes): void
    {
        $this->startSpan($operation, $invocationId, $provider, attributes: $attributes);
        $this->endSpan($invocationId);
    }

    /**
     * @return array<non-empty-string, int|string|null>
     */
    protected function responseAttributes(Usage $usage, Meta $meta): array
    {
        return ['gen_ai.response.model' => $meta->model, ...$this->usageAttributes($usage)];
    }

    /**
     * @param  array<non-empty-string, int|string|null>  $attributes
     * @return array<non-empty-string, int|string>
     */
    protected function withoutNulls(array $attributes): array
    {
        return array_filter($attributes, fn (int|string|null $value): bool => $value !== null);
    }
}
