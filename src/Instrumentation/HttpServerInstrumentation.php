<?php

declare(strict_types=1);

namespace ActiveEngagement\Telemetry\Instrumentation;

use ActiveEngagement\Telemetry\Instrumentation\Concerns\RedactsQuerySecrets;
use Keepsuit\LaravelOpenTelemetry\Instrumentation\HttpServerInstrumentation as KeepsuitHttpServerInstrumentation;
use Override;

class HttpServerInstrumentation extends KeepsuitHttpServerInstrumentation
{
    use RedactsQuerySecrets;

    /**
     * @param  array<mixed>  $options
     */
    #[Override]
    public function register(array $options): void
    {
        parent::register($this->withQuerySecrets($options));
    }
}
