<?php

namespace Actengage\Telemetry\Events;

use OpenTelemetry\API\Trace\SpanInterface;

class AiSpanStarted
{
    public function __construct(
        public SpanInterface $span,
        public object $source,
    ) {}
}
