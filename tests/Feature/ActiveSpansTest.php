<?php

use ActiveEngagement\Telemetry\Support\ActiveSpans;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\SDK\Trace\ImmutableSpan;

it('closes a span and restores the previous active span', function (): void {
    $spans = new ActiveSpans;
    $span = app(TracerInterface::class)->spanBuilder('agent')->startSpan();

    $spans->open('agent', $span);

    expect(Span::getCurrent())->toBe($span)
        ->and($spans->close('agent'))->toBe($span)
        ->and(Span::getCurrent())->not->toBe($span)
        ->and($spans->has('agent'))->toBeFalse();
});

it('abandons the spans opened after the span it closes', function (): void {
    $spans = new ActiveSpans;
    $tracer = app(TracerInterface::class);

    $spans->open('agent', $tracer->spanBuilder('agent')->startSpan());
    $spans->open('tool', $tracer->spanBuilder('tool')->startSpan());

    $spans->close('agent')?->end();

    $tool = collect(exportedSpans())->first(fn (ImmutableSpan $span): bool => $span->getName() === 'tool');

    expect($tool?->getStatus()->getCode())->toBe(StatusCode::STATUS_ERROR)
        ->and($spans->has('tool'))->toBeFalse();
});

it('returns nothing for a span it never opened', function (): void {
    expect(new ActiveSpans()->close('unknown'))->toBeNull();
});

it('abandons every open span', function (): void {
    $spans = new ActiveSpans;
    $tracer = app(TracerInterface::class);

    $spans->open('agent', $tracer->spanBuilder('agent')->startSpan());
    $spans->open('tool', $tracer->spanBuilder('tool')->startSpan());

    $spans->abandonAll();

    expect(collect(exportedSpans())->map(fn (ImmutableSpan $span): string => $span->getStatus()->getCode())->all())
        ->toBe([StatusCode::STATUS_ERROR, StatusCode::STATUS_ERROR])
        ->and($spans->has('agent'))->toBeFalse()
        ->and(Span::getCurrent()->getContext()->isValid())->toBeFalse();
});
