<?php

declare(strict_types=1);

namespace ActiveEngagement\Telemetry\Instrumentation;

use ActiveEngagement\Telemetry\Instrumentation\Concerns\RedactsQuerySecrets;
use Illuminate\Support\Arr;
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
        parent::register($this->withExcludedPaths($this->withQuerySecrets($options)));
    }

    /**
     * @param  array<mixed>  $options
     * @return array<mixed>
     */
    protected function withExcludedPaths(array $options): array
    {
        return [
            ...$options,
            'excluded_paths' => [
                ...$this->config->array('telemetry.excluded_paths'),
                ...Arr::array($options, 'excluded_paths', []),
            ],
        ];
    }
}
