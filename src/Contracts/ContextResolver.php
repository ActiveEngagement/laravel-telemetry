<?php

namespace Actengage\Telemetry\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Log\Context\Repository as ContextRepository;

interface ContextResolver
{
    /**
     * @param  array<mixed>  $context
     * @return array<mixed>
     */
    public function resolve(array $context): array;

    /**
     * @return array<non-empty-string, bool|int|float|string|array<mixed>|null>
     */
    public function userAttributes(?Authenticatable $user = null): array;

    public function dehydrating(ContextRepository $context): void;
}
