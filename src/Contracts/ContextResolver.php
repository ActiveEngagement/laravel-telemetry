<?php

namespace Actengage\Telemetry\Contracts;

interface ContextResolver
{
    /**
     * @param  array<mixed>  $context
     * @return array<mixed>
     */
    public function resolve(array $context): array;
}
