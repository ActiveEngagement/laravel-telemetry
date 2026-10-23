<?php

declare(strict_types=1);

namespace ActiveEngagement\Telemetry\Support;

use ActiveEngagement\Telemetry\Contracts\ContextResolver;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Log\Context\Repository as ContextRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Context;
use Keepsuit\LaravelOpenTelemetry\Facades\OpenTelemetry;

class KeepsuitContextResolver implements ContextResolver
{
    public const string USER_CONTEXT_KEY = 'telemetry_user';

    public function __construct(protected Repository $config) {}

    public function resolve(array $context): array
    {
        return [...$this->withoutTraceId($context), ...$this->userAttributes()];
    }

    public function userAttributes(?Authenticatable $user = null): array
    {
        if ($this->config->get('opentelemetry.user_context') !== true) {
            return [];
        }

        $user ??= $this->authenticatedUser();

        return $user instanceof Authenticatable
            ? OpenTelemetry::collectUserContext($user)
            : $this->carriedUserAttributes();
    }

    public function dehydrating(ContextRepository $context): void
    {
        if ($context->hasHidden(static::USER_CONTEXT_KEY)) {
            return;
        }

        $attributes = $this->userAttributes();

        if ($attributes !== []) {
            $context->addHidden(static::USER_CONTEXT_KEY, $attributes);
        }
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

    protected function authenticatedUser(): ?Authenticatable
    {
        return Auth::hasResolvedGuards() && Auth::hasUser() ? Auth::user() : null;
    }

    /**
     * @return array<non-empty-string, bool|int|float|string|array<mixed>|null>
     */
    protected function carriedUserAttributes(): array
    {
        $carried = Context::getHidden(static::USER_CONTEXT_KEY);
        $attributes = [];

        foreach (is_array($carried) ? $carried : [] as $key => $value) {
            if (is_string($key) && $key !== '' && (is_scalar($value) || is_array($value))) {
                $attributes[$key] = $value;
            }
        }

        return $attributes;
    }
}
