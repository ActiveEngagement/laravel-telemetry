<?php

declare(strict_types=1);

namespace ActiveEngagement\Telemetry\Events;

use OpenTelemetry\API\Trace\SpanInterface;

class AiSpanStarted
{
    public function __construct(
        public SpanInterface $span,
        public object $source,
    ) {}
}
