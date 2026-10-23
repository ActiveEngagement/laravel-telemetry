<?php

use Actengage\Telemetry\Telemetry;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\TextUsage;
use Laravel\Ai\Responses\TextResponse;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\SDK\Trace\EventInterface;
use OpenTelemetry\SDK\Trace\ImmutableSpan;
use Tests\Fixtures\SupportAgent;

/**
 * @return list<ImmutableSpan>
 */
function agentSpans(): array
{
    return array_values(array_filter(
        exportedSpans(),
        fn (ImmutableSpan $span): bool => str_starts_with($span->getName(), 'invoke_agent'),
    ));
}

it('traces an agent prompt', function (): void {
    SupportAgent::fake([
        new TextResponse('Hello', new TextUsage(1200, 300, 800, 100, 50), new Meta('anthropic', 'claude-sonnet-4-5')),
    ]);

    new SupportAgent()->prompt('Hi', provider: 'anthropic', model: 'claude-sonnet-4-5');

    $spans = agentSpans();

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

    expect(agentSpans()[0]->getAttributes()->toArray())
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

    expect(agentSpans())->toHaveCount(1);
});

it('records a failed agent prompt', function (): void {
    SupportAgent::fake(fn () => throw new RuntimeException('The provider is overloaded.'));

    expect(fn (): AgentResponse => new SupportAgent()->prompt('Hi'))->toThrow(RuntimeException::class);

    $span = agentSpans()[0];
    $events = array_map(fn (EventInterface $event): string => $event->getName(), $span->getEvents());

    expect($span->getStatus()->getCode())->toBe(StatusCode::STATUS_ERROR)
        ->and($span->getStatus()->getDescription())->toBe('The provider is overloaded.')
        ->and($events)->toContain('exception');
});

it('makes the agent span the parent of spans started during the prompt', function (): void {
    SupportAgent::fake(function (): string {
        app(TracerInterface::class)->spanBuilder('provider call')->startSpan()->end();

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

    expect(agentSpans())->toBeEmpty();
});

it('can be turned off with TELEMETRY_AI_WATCHER', function (): void {
    refreshWithEnv('TELEMETRY_AI_WATCHER', 'false');
    SupportAgent::fake(['Hello']);

    new SupportAgent()->prompt('Hi');

    expect(agentSpans())->toBeEmpty();
});
