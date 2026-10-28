<?php

declare(strict_types=1);

namespace ActiveEngagement\Telemetry\Contracts;

use ActiveEngagement\Telemetry\Browser\BrowserConfig;
use InvalidArgumentException;

interface BrowserContext
{
    /**
     * @throws InvalidArgumentException
     */
    public function config(): ?BrowserConfig;

    /**
     * @return array{id: int|string, name: string|null, email: string|null}|null
     */
    public function user(): ?array;

    public function traceparent(): ?string;
}
