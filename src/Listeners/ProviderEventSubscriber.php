<?php

declare(strict_types=1);

namespace ActiveEngagement\Telemetry\Listeners;

use ActiveEngagement\Telemetry\Listeners\Concerns\DescribesTokenUsage;
use ActiveEngagement\Telemetry\Telemetry;
use Laravel\Ai\Providers\Provider;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;
use OpenTelemetry\API\Trace\SpanKind;

abstract class ProviderEventSubscriber extends AiEventSubscriber
{
    use DescribesTokenUsage;

    /**
     * @param  non-empty-string  $operation
     * @param  array<non-empty-string, bool|int|float|string|array<mixed>|null>  $attributes
     */
    protected function startSpan(
        object $source,
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
                ...$this->contextResolver->userAttributes(),
                ...$attributes,
            ]))
            ->startSpan();

        $this->openSpan($invocationId, $span, $source);
    }

    /**
     * @param  array<non-empty-string, bool|int|float|string|array<mixed>|null>  $attributes
     */
    protected function endSpan(string $invocationId, array $attributes = []): void
    {
        $this->spans->close($invocationId)
            ?->setAttributes($this->withoutNulls($attributes))
            ->end();
    }

    /**
     * @param  non-empty-string  $operation
     * @param  array<non-empty-string, bool|int|float|string|array<mixed>|null>  $attributes
     */
    protected function recordSpan(object $source, string $operation, string $invocationId, Provider $provider, array $attributes): void
    {
        $this->startSpan($source, $operation, $invocationId, $provider, attributes: $attributes);
        $this->endSpan($invocationId);
    }

    /**
     * @return array<non-empty-string, int|string|null>
     */
    protected function responseAttributes(Usage $usage, Meta $meta): array
    {
        return ['gen_ai.response.model' => $meta->model, ...$this->usageAttributes($usage)];
    }
}
