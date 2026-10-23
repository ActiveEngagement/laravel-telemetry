<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use ActiveEngagement\Telemetry\Contracts\HasTelemetryAttributes;

class BillingAgent extends SupportAgent implements HasTelemetryAttributes
{
    public function telemetryAttributes(): array
    {
        return [
            'gen_ai.agent.name' => 'billing',
            'app.team' => 'finance',
        ];
    }
}
