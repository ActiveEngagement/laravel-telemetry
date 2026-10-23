<?php

namespace Tests\Fixtures;

use ActiveEngagement\Telemetry\Support\ActiveSpans;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use OpenTelemetry\API\Trace\TracerInterface;

class LeavesSpanOpen implements ShouldQueue
{
    use Queueable;

    public function handle(ActiveSpans $spans, TracerInterface $tracer): void
    {
        $spans->open('stray', $tracer->spanBuilder('stray')->startSpan());
    }
}
