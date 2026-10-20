<?php

namespace Actengage\Telemetry\Support;

use Carbon\CarbonImmutable;
use OpenTelemetry\API\Common\Time\ClockInterface;

class WallClock implements ClockInterface
{
    public function now(): int
    {
        return (int) CarbonImmutable::now()->getPreciseTimestamp(6) * 1000;
    }
}
