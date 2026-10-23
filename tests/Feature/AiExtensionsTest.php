<?php

use Actengage\Telemetry\Events\AiSpanStarted;
use Actengage\Telemetry\Telemetry;
use Illuminate\Support\Facades\Event;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Events\GeneratingEmbeddings;
use Laravel\Ai\Events\InvokingTool;
use Laravel\Ai\Events\PromptingAgent;
use Laravel\Ai\Responses\Data\ToolCall;
use Tests\Fixtures\BillingAgent;
use Tests\Fixtures\SupportAgent;

it('adds the attributes an agent declares', function (): void {
    BillingAgent::fake(['Hello']);

    new BillingAgent()->prompt('Hi');

    $span = aiSpans('invoke_agent')[0];

    expect($span->getName())->toBe('invoke_agent billing')
        ->and($span->getAttributes()->toArray())->toMatchArray([
            'gen_ai.agent.name' => 'billing',
            'app.team' => 'finance',
        ]);
});

it('adds the attributes a tool declares', function (): void {
    SupportAgent::fake([new ToolCall('call_1', 'LookupOrder', ['id' => 7]), 'It has shipped.']);

    new SupportAgent()->prompt('Where is order 7?');

    expect(aiSpans('execute_tool')[0]->getAttributes()->get('app.domain'))->toBe('orders');
});

it('dispatches AiSpanStarted with the span and the laravel/ai event', function (): void {
    $started = [];
    Event::listen(function (AiSpanStarted $event) use (&$started): void {
        $started[] = $event->source::class;
    });
    SupportAgent::fake([new ToolCall('call_1', 'LookupOrder', ['id' => 7]), 'It has shipped.']);
    Embeddings::fake();

    new SupportAgent()->prompt('Where is order 7?');
    Embeddings::for(['Hello'])->generate();

    expect($started)->toBe([PromptingAgent::class, InvokingTool::class, GeneratingEmbeddings::class]);
});

it('lets an AiSpanStarted listener add attributes', function (): void {
    Event::listen(function (AiSpanStarted $event): void {
        if ($event->source instanceof InvokingTool) {
            $event->span->setAttribute('app.order.id', $event->source->arguments['id']);
        }
    });
    SupportAgent::fake([new ToolCall('call_1', 'LookupOrder', ['id' => 7]), 'It has shipped.']);

    new SupportAgent()->prompt('Where is order 7?');

    expect(aiSpans('execute_tool')[0]->getAttributes()->get('app.order.id'))->toBe(7);
});

it('does not dispatch AiSpanStarted while recording is stopped', function (): void {
    Event::fake([AiSpanStarted::class]);
    Telemetry::stopRecording();
    SupportAgent::fake(['Hello']);

    new SupportAgent()->prompt('Hi');

    Event::assertNotDispatched(AiSpanStarted::class);
});
