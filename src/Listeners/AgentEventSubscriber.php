<?php

namespace ActiveEngagement\Telemetry\Listeners;

use ActiveEngagement\Telemetry\Listeners\Concerns\DescribesTokenUsage;
use ActiveEngagement\Telemetry\Telemetry;
use Illuminate\Contracts\Auth\Authenticatable;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Events\AgentFailed;
use Laravel\Ai\Events\AgentFailedOver;
use Laravel\Ai\Events\AgentPrompted;
use Laravel\Ai\Events\AgentStreamed;
use Laravel\Ai\Events\PromptingAgent;
use Laravel\Ai\Events\StreamingAgent;
use Laravel\Ai\Responses\AgentResponse;
use OpenTelemetry\API\Trace\SpanKind;

class AgentEventSubscriber extends AiEventSubscriber
{
    use DescribesTokenUsage;

    /**
     * @var array<class-string, string>
     */
    protected array $listen = [
        PromptingAgent::class => 'start',
        StreamingAgent::class => 'start',
        AgentPrompted::class => 'end',
        AgentStreamed::class => 'end',
        AgentFailed::class => 'fail',
        AgentFailedOver::class => 'failover',
    ];

    public function start(PromptingAgent $event): void
    {
        if (! Telemetry::isRecording() || $this->spans->has($event->invocationId)) {
            return;
        }

        $agent = $event->prompt->agent;
        $attributes = $this->classAttributes($agent);
        $name = $this->agentName($agent, $attributes);

        $span = $this->tracer->spanBuilder('invoke_agent '.$name)
            ->setSpanKind(SpanKind::KIND_INTERNAL)
            ->setAttributes($this->withoutNulls([
                'gen_ai.operation.name' => 'invoke_agent',
                'gen_ai.agent.name' => $name,
                'gen_ai.provider.name' => $event->prompt->provider->name(),
                'gen_ai.request.model' => $event->prompt->model,
                ...$this->contextResolver->userAttributes($this->participant($agent)),
                ...$attributes,
            ]))
            ->startSpan();

        $this->openSpan($event->invocationId, $span, $event);
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
     * @param  array<non-empty-string, bool|int|float|string|array<mixed>|null>  $attributes
     */
    protected function agentName(Agent $agent, array $attributes): string
    {
        $name = $attributes['gen_ai.agent.name'] ?? null;

        return is_string($name) && $name !== '' ? $name : class_basename($agent);
    }

    protected function participant(Agent $agent): ?Authenticatable
    {
        $participant = method_exists($agent, 'conversationParticipant') ? $agent->conversationParticipant() : null;

        return $participant instanceof Authenticatable ? $participant : null;
    }

    /**
     * @return array<non-empty-string, bool|int|float|string|array<mixed>>
     */
    protected function responseAttributes(AgentResponse $response): array
    {
        return $this->withoutNulls([
            'gen_ai.provider.name' => $response->meta->provider,
            'gen_ai.response.model' => $response->meta->model,
            'gen_ai.conversation.id' => $response->conversationId,
            ...$this->usageAttributes($response->usage),
        ]);
    }
}
