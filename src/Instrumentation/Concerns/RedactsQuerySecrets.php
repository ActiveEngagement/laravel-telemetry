<?php

declare(strict_types=1);

namespace ActiveEngagement\Telemetry\Instrumentation\Concerns;

use Illuminate\Config\Repository;
use Illuminate\Support\Arr;

trait RedactsQuerySecrets
{
    public function __construct(protected Repository $config) {}

    /**
     * @param  array<mixed>  $options
     * @return array<mixed>
     */
    protected function withQuerySecrets(array $options): array
    {
        return [
            ...$options,
            'sensitive_query_parameters' => [
                ...$this->config->array('telemetry.sensitive_query_parameters'),
                ...Arr::array($options, 'sensitive_query_parameters', []),
            ],
        ];
    }
}
