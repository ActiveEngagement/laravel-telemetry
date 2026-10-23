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

    protected function startSpan(string $operation, string $invocationId, Provider $provider, string $model): void
    {
        if (! Telemetry::isRecording()) {
            return;
        }

        $span = $this->tracer->spanBuilder($operation.' '.$model)
            ->setSpanKind(SpanKind::KIND_CLIENT)
            ->setAttribute('gen_ai.operation.name', $operation)
            ->setAttribute('gen_ai.provider.name', $provider->name())
            ->setAttribute('gen_ai.request.model', $model)
            ->startSpan();

        $this->spans->open($invocationId, $span);
    }

    protected function endSpan(string $invocationId, Usage $usage, Meta $meta): void
    {
        $this->spans->close($invocationId)
            ?->setAttributes(array_filter([
                'gen_ai.response.model' => $meta->model,
                ...$this->usageAttributes($usage),
            ], fn (int|string|null $value): bool => $value !== null))
            ->end();
    }
}
