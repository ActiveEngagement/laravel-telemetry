<?php

namespace Actengage\Telemetry\Contracts;

interface HasTelemetryAttributes
{
    /**
     * @return array<non-empty-string, bool|int|float|string|array<mixed>|null>
     */
    public function telemetryAttributes(): array;
}
