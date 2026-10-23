<?php

declare(strict_types=1);

use ActiveEngagement\Telemetry\Telemetry;
use Illuminate\Auth\GenericUser;
use Illuminate\Support\Collection;
use Laravel\Ai\Exceptions\ProviderOverloadedException;
use Laravel\Ai\Providers\Provider;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\TextUsage;
use Laravel\Ai\Responses\Data\ToolCall;
use Laravel\Ai\Responses\TextResponse;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\SDK\Trace\EventInterface;
use OpenTelemetry\SDK\Trace\ImmutableSpan;
use Tests\Fixtures\ConversationAgent;
use Tests\Fixtures\SupportAgent;

it('traces an agent prompt', function (): void {
    SupportAgent::fake([
        new TextResponse('Hello', new TextUsage(1200, 300, 800, 100, 50), new Meta('anthropic', 'claude-sonnet-4-5')),
    ]);

    new SupportAgent()->prompt('Hi', provider: 'anthropic', model: 'claude-sonnet-4-5');

    $spans = aiSpans('invoke_agent');

    expect($spans)->toHaveCount(1)
        ->and($spans[0]->getName())->toBe('invoke_agent SupportAgent')
        ->and($spans[0]->getKind())->toBe(SpanKind::KIND_INTERNAL)
        ->and($spans[0]->getAttributes()->toArray())->toMatchArray([
            'gen_ai.operation.name' => 'invoke_agent',
            'gen_ai.agent.name' => 'SupportAgent',
            'gen_ai.provider.name' => 'anthropic',
            'gen_ai.request.model' => 'claude-sonnet-4-5',
            'gen_ai.response.model' => 'claude-sonnet-4-5',
            'gen_ai.usage.input_tokens' => 1200,
            'gen_ai.usage.output_tokens' => 300,
            'gen_ai.usage.cache_read.input_tokens' => 800,
            'gen_ai.usage.cache_creation.input_tokens' => 100,
            'gen_ai.usage.reasoning.output_tokens' => 50,
        ]);
});

it('leaves out token counts that the provider does not report', function (): void {
    SupportAgent::fake([
        new TextResponse('Hello', new TextUsage(10, 5), new Meta('openai', 'gpt-5')),
    ]);

    new SupportAgent()->prompt('Hi');

    expect(aiSpans('invoke_agent')[0]->getAttributes()->toArray())
        ->toHaveKeys(['gen_ai.usage.input_tokens', 'gen_ai.usage.output_tokens'])
        ->not->toHaveKeys([
            'gen_ai.usage.cache_read.input_tokens',
            'gen_ai.usage.cache_creation.input_tokens',
            'gen_ai.usage.reasoning.output_tokens',
        ]);
});

it('traces a streamed agent prompt once', function (): void {
    SupportAgent::fake(['Hello']);

    iterator_to_array(new SupportAgent()->stream('Hi'), false);

    expect(aiSpans('invoke_agent'))->toHaveCount(1);
});

it('records a failed agent prompt', function (): void {
    SupportAgent::fake(fn () => throw new RuntimeException('The provider is overloaded.'));

    expect(fn (): AgentResponse => new SupportAgent()->prompt('Hi'))->toThrow(RuntimeException::class);

    $span = aiSpans('invoke_agent')[0];
    $events = array_map(fn (EventInterface $event): string => $event->getName(), $span->getEvents());

    expect($span->getStatus()->getCode())->toBe(StatusCode::STATUS_ERROR)
        ->and($span->getStatus()->getDescription())->toBe('The provider is overloaded.')
        ->and($events)->toContain('exception');
});

it('makes the agent span the parent of spans started during the prompt', function (): void {
    SupportAgent::fake(function (): string {
        resolve(TracerInterface::class)->spanBuilder('provider call')->startSpan()->end();

        return 'Hello';
    });

    new SupportAgent()->prompt('Hi');

    $spans = collect(exportedSpans())->keyBy(fn (ImmutableSpan $span): string => $span->getName());

    expect($spans['provider call']->getParentSpanId())->toBe($spans['invoke_agent SupportAgent']->getSpanId());
});

it('records nothing while recording is stopped', function (): void {
    Telemetry::stopRecording();
    SupportAgent::fake(['Hello']);

    new SupportAgent()->prompt('Hi');

    expect(aiSpans('invoke_agent'))->toBeEmpty();
});

it('can be turned off with TELEMETRY_AI_WATCHER', function (): void {
    refreshWithEnv('TELEMETRY_AI_WATCHER', 'false');
    SupportAgent::fake(['Hello']);

    new SupportAgent()->prompt('Hi');

    expect(aiSpans('invoke_agent'))->toBeEmpty();
});

it('traces a tool call inside the agent span', function (): void {
    SupportAgent::fake([new ToolCall('call_1', 'LookupOrder', ['id' => 7]), 'It has shipped.']);

    new SupportAgent()->prompt('Where is order 7?');

    $tools = aiSpans('execute_tool');

    expect($tools)->toHaveCount(1)
        ->and($tools[0]->getName())->toBe('execute_tool LookupOrder')
        ->and($tools[0]->getKind())->toBe(SpanKind::KIND_INTERNAL)
        ->and($tools[0]->getAttributes()->toArray())->toMatchArray([
            'gen_ai.operation.name' => 'execute_tool',
            'gen_ai.tool.name' => 'LookupOrder',
        ])
        ->and($tools[0]->getParentSpanId())->toBe(aiSpans('invoke_agent')[0]->getSpanId());
});

it('records a failed tool call', function (): void {
    SupportAgent::fake([new ToolCall('call_1', 'LookupOrder', ['id' => 0]), 'Sorry.']);

    expect(fn (): AgentResponse => new SupportAgent()->prompt('Where is order 0?'))->toThrow(RuntimeException::class);

    $tool = aiSpans('execute_tool')[0];

    expect($tool->getStatus()->getCode())->toBe(StatusCode::STATUS_ERROR)
        ->and($tool->getStatus()->getDescription())->toBe('The order does not exist.');
});

it('records a provider failover on the agent span', function (): void {
    SupportAgent::fake(function (string $prompt, Collection $attachments, Provider $provider): string {
        if ($provider->name() === 'anthropic') {
            throw ProviderOverloadedException::forProvider('anthropic');
        }

        return 'Hello';
    });

    new SupportAgent()->prompt('Hi', provider: ['anthropic' => 'claude-sonnet-4-5', 'openai' => 'gpt-5']);

    $spans = aiSpans('invoke_agent');
    $failover = collect($spans[0]->getEvents())->first(fn (EventInterface $event): bool => $event->getName() === 'exception');

    expect($spans)->toHaveCount(1)
        ->and($spans[0]->getStatus()->getCode())->toBe(StatusCode::STATUS_UNSET)
        ->and($spans[0]->getAttributes()->get('gen_ai.provider.name'))->toBe('openai')
        ->and($failover?->getAttributes()->toArray())->toMatchArray([
            'gen_ai.provider.name' => 'anthropic',
            'gen_ai.request.model' => 'claude-sonnet-4-5',
        ]);
});

it('adds the logged-in user to the agent span', function (): void {
    $this->actingAs(new GenericUser(['id' => 42]));
    SupportAgent::fake(['Hello']);

    new SupportAgent()->prompt('Hi');

    expect(aiSpans('invoke_agent')[0]->getAttributes()->get('user.id'))->toBe(42);
});

it('prefers the conversation participant over the logged-in user', function (): void {
    $this->actingAs(new GenericUser(['id' => 42]));
    ConversationAgent::fake(['Hello']);

    new ConversationAgent(new GenericUser(['id' => 7]))->prompt('Hi');

    expect(aiSpans('invoke_agent')[0]->getAttributes()->get('user.id'))->toBe(7);
});
