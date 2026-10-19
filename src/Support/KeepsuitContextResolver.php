<?php

namespace Actengage\Telemetry\Support;

use Actengage\Telemetry\Contracts\ContextResolver;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Facades\Auth;
use Keepsuit\LaravelOpenTelemetry\Facades\OpenTelemetry;

class KeepsuitContextResolver implements ContextResolver
{
    public function __construct(protected Repository $config) {}

    public function resolve(array $context): array
    {
        return [...$this->withoutTraceId($context), ...$this->userAttributes()];
    }

    /**
     * @param  array<mixed>  $context
     * @return array<mixed>
     */
    protected function withoutTraceId(array $context): array
    {
        $traceIdField = $this->config->get('opentelemetry.logs.trace_id_field');

        if ($this->config->get('opentelemetry.logs.inject_trace_id') && is_string($traceIdField)) {
            unset($context[$traceIdField]);
        }

        return $context;
    }

    /**
     * @return array<mixed>
     */
    protected function userAttributes(): array
    {
        if ($this->config->get('opentelemetry.user_context') !== true || ! Auth::hasResolvedGuards() || ! Auth::hasUser()) {
            return [];
        }

        $user = Auth::user();

        return $user === null ? [] : OpenTelemetry::collectUserContext($user);
    }
}
