<?php

declare(strict_types=1);

namespace ActiveEngagement\Telemetry\Support;

use Carbon\CarbonImmutable;
use OpenTelemetry\API\Common\Time\ClockInterface;
use Override;

class WallClock implements ClockInterface
{
    #[Override]
    public function now(): int
    {
        return (int) CarbonImmutable::now()->getPreciseTimestamp(6) * 1000;
    }
}
