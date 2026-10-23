<?php

namespace Actengage\Telemetry\Listeners;

use Actengage\Telemetry\Contracts\ContextResolver;
use Actengage\Telemetry\Listeners\Concerns\DescribesTokenUsage;
use Actengage\Telemetry\Support\ActiveSpans;
use Actengage\Telemetry\Telemetry;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Events\Dispatcher;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Events\AgentFailed;
use Laravel\Ai\Events\AgentFailedOver;
use Laravel\Ai\Events\AgentPrompted;
use Laravel\Ai\Events\AgentStreamed;
use Laravel\Ai\Events\PromptingAgent;
use Laravel\Ai\Events\StreamingAgent;
use Laravel\Ai\Responses\AgentResponse;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\TracerInterface;

class AgentEventSubscriber
{
    use DescribesTokenUsage;

    public function __construct(
        protected TracerInterface $tracer,
        protected ActiveSpans $spans,
        protected ContextResolver $contextResolver,
    ) {}

    public function start(PromptingAgent $event): void
    {
        if (! Telemetry::isRecording() || $this->spans->has($event->invocationId)) {
            return;
        }

        $agent = $this->agentName($event->prompt->agent);

        $span = $this->tracer->spanBuilder('invoke_agent '.$agent)
            ->setSpanKind(SpanKind::KIND_INTERNAL)
            ->setAttributes([
                'gen_ai.operation.name' => 'invoke_agent',
                'gen_ai.agent.name' => $agent,
                'gen_ai.provider.name' => $event->prompt->provider->name(),
                'gen_ai.request.model' => $event->prompt->model,
                ...$this->contextResolver->userAttributes($this->participant($event->prompt->agent)),
            ])
            ->startSpan();

        $this->spans->open($event->invocationId, $span);
    }

    public function end(AgentPrompted $event): void
    {
        $this->spans->close($event->invocationId)
            ?->setAttributes($this->responseAttributes($event->response))
            ->end();
    }

    public function fail(AgentFailed $event): void
    {
        $this->spans->fail($event->invocationId, $event->exception);
    }

    public function failover(AgentFailedOver $event): void
    {
        $this->spans->get($event->invocationId)?->recordException($event->exception, [
            'gen_ai.provider.name' => $event->provider->name(),
            'gen_ai.request.model' => $event->model,
        ]);
    }

    /**
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            PromptingAgent::class => 'start',
            StreamingAgent::class => 'start',
            AgentPrompted::class => 'end',
            AgentStreamed::class => 'end',
            AgentFailed::class => 'fail',
            AgentFailedOver::class => 'failover',
        ];
    }

    protected function agentName(Agent $agent): string
    {
        return class_basename($agent);
    }

    protected function participant(Agent $agent): ?Authenticatable
    {
        $participant = method_exists($agent, 'conversationParticipant') ? $agent->conversationParticipant() : null;

        return $participant instanceof Authenticatable ? $participant : null;
    }

    /**
     * @return array<non-empty-string, int|string>
     */
    protected function responseAttributes(AgentResponse $response): array
    {
        return array_filter([
            'gen_ai.provider.name' => $response->meta->provider,
            'gen_ai.response.model' => $response->meta->model,
            'gen_ai.conversation.id' => $response->conversationId,
            ...$this->usageAttributes($response->usage),
        ], fn (int|string|null $value): bool => $value !== null);
    }
}
