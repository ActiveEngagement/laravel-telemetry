<?php

namespace Actengage\Telemetry\Watchers;

use Actengage\Telemetry\Support\ActiveSpans;
use Actengage\Telemetry\Telemetry;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Events\AgentFailed;
use Laravel\Ai\Events\AgentPrompted;
use Laravel\Ai\Events\AgentStreamed;
use Laravel\Ai\Events\PromptingAgent;
use Laravel\Ai\Events\StreamingAgent;
use Laravel\Ai\Responses\AgentResponse;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\API\Trace\TracerInterface;

class AiWatcher extends Watcher
{
    /**
     * @param  array<mixed>  $options
     */
    public function __construct(
        protected TracerInterface $tracer,
        protected ActiveSpans $spans,
        array $options = [],
    ) {
        parent::__construct($options);
    }

    public function register(Application $app): void
    {
        if (! Telemetry::isAiInstalled()) {
            return;
        }

        $events = $app->make(Dispatcher::class);

        $events->listen([PromptingAgent::class, StreamingAgent::class], $this->startAgentSpan(...));
        $events->listen([AgentPrompted::class, AgentStreamed::class], $this->endAgentSpan(...));
        $events->listen(AgentFailed::class, $this->failAgentSpan(...));
    }

    public function startAgentSpan(PromptingAgent $event): void
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

    public function endAgentSpan(AgentPrompted $event): void
    {
        $this->spans->close($event->invocationId)
            ?->setAttributes($this->responseAttributes($event->response))
            ->end();
    }

    public function failAgentSpan(AgentFailed $event): void
    {
        $this->spans->close($event->invocationId)
            ?->recordException($event->exception)
            ->setStatus(StatusCode::STATUS_ERROR, $event->exception->getMessage())
            ->end();
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
