<?php

namespace Actengage\Telemetry\Watchers\Ai;

use Actengage\Telemetry\Support\ActiveSpans;
use Actengage\Telemetry\Telemetry;
use Illuminate\Contracts\Events\Dispatcher;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Events\AgentFailed;
use Laravel\Ai\Events\AgentPrompted;
use Laravel\Ai\Events\AgentStreamed;
use Laravel\Ai\Events\PromptingAgent;
use Laravel\Ai\Events\StreamingAgent;
use Laravel\Ai\Responses\AgentResponse;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\TracerInterface;

class AgentEventSubscriber
{
    public function __construct(
        protected TracerInterface $tracer,
        protected ActiveSpans $spans,
    ) {}

    public function start(PromptingAgent $event): void
    {
        if (! Telemetry::isRecording() || $this->spans->has($event->invocationId)) {
            return;
        }

        $agent = $this->agentName($event->prompt->agent);

        $span = $this->tracer->spanBuilder('invoke_agent '.$agent)
            ->setSpanKind(SpanKind::KIND_INTERNAL)
            ->setAttribute('gen_ai.operation.name', 'invoke_agent')
            ->setAttribute('gen_ai.agent.name', $agent)
            ->setAttribute('gen_ai.provider.name', $event->prompt->provider->name())
            ->setAttribute('gen_ai.request.model', $event->prompt->model)
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
        ];
    }

    protected function agentName(Agent $agent): string
    {
        return class_basename($agent);
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
            'gen_ai.usage.input_tokens' => $response->usage->inputTokens,
            'gen_ai.usage.output_tokens' => $response->usage->outputTokens,
            'gen_ai.usage.cache_read.input_tokens' => $response->usage->cacheReadInputTokens,
            'gen_ai.usage.cache_creation.input_tokens' => $response->usage->cacheWriteInputTokens,
            'gen_ai.usage.reasoning.output_tokens' => $response->usage->reasoningTokens,
        ], fn (int|string|null $value): bool => $value !== null);
    }
}
