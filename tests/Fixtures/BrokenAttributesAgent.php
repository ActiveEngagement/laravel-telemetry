<?php

namespace Tests\Fixtures;

use ActiveEngagement\Telemetry\Contracts\HasTelemetryAttributes;
use RuntimeException;

class BrokenAttributesAgent extends SupportAgent implements HasTelemetryAttributes
{
    public function telemetryAttributes(): array
    {
        throw new RuntimeException('The attributes are broken.');
    }
}
