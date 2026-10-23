<?php

namespace ActiveEngagement\Telemetry\Events;

class HeartbeatRecorded
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public string $name,
        public array $attributes = [],
    ) {}
}
