<?php

namespace Tests\Fixtures;

use Actengage\Telemetry\Contracts\HasTelemetryAttributes;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use RuntimeException;

class LookupOrder implements HasTelemetryAttributes, Tool
{
    public function description(): string
    {
        return 'Look up an order by its ID.';
    }

    public function handle(Request $request): string
    {
        if ($request['id'] === 0) {
            throw new RuntimeException('The order does not exist.');
        }

        return 'The order has shipped.';
    }

    public function telemetryAttributes(): array
    {
        return ['app.domain' => 'orders'];
    }

    public function schema(JsonSchema $schema): array
    {
        return ['id' => $schema->integer()->required()];
    }
}
