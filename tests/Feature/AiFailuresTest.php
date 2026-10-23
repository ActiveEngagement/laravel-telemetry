<?php

declare(strict_types=1);

use ActiveEngagement\Telemetry\Events\AiSpanStarted;
use ActiveEngagement\Telemetry\Support\ActiveSpans;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\SDK\Trace\ImmutableSpan;
use Tests\Fixtures\BrokenAttributesAgent;
use Tests\Fixtures\LeavesSpanOpen;
use Tests\Fixtures\SupportAgent;

function strayStatus(): ?string
{
    return collect(exportedSpans())
        ->first(fn (ImmutableSpan $span): bool => $span->getName() === 'stray')
        ?->getStatus()->getCode();
}

it('never breaks a prompt when an AiSpanStarted listener throws', function (): void {
    Event::listen(fn (AiSpanStarted $event) => throw new RuntimeException('The listener is broken.'));
    SupportAgent::fake(['Hello']);

    expect(new SupportAgent()->prompt('Hi')->text)->toBe('Hello')
        ->and(aiSpans('invoke_agent'))->toHaveCount(1);
});

it('never breaks a prompt when the agent attributes throw', function (): void {
    BrokenAttributesAgent::fake(['Hello']);

    expect(new BrokenAttributesAgent()->prompt('Hi')->text)->toBe('Hello');
});

it('closes spans left open when a queued job ends', function (): void {
    Queue::connection('sync')->push(new LeavesSpanOpen);

    expect(strayStatus())->toBe(StatusCode::STATUS_ERROR)
        ->and(app(ActiveSpans::class)->has('stray'))->toBeFalse();
});

it('closes spans left open when the request ends', function (): void {
    app(ActiveSpans::class)->open('stray', app(TracerInterface::class)->spanBuilder('stray')->startSpan());

    app()->terminate();

    expect(strayStatus())->toBe(StatusCode::STATUS_ERROR)
        ->and(app(ActiveSpans::class)->has('stray'))->toBeFalse();
});
