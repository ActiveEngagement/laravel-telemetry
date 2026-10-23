<?php

declare(strict_types=1);

namespace ActiveEngagement\Telemetry\Contracts;

interface HasTelemetryAttributes
{
    /**
     * @return array<non-empty-string, bool|int|float|string|array<mixed>|null>
     */
    public function telemetryAttributes(): array;
}
