<?php

namespace Tests;

use Actengage\Telemetry\TelemetryServiceProvider;
use Keepsuit\LaravelOpenTelemetry\LaravelOpenTelemetryServiceProvider;
use Laravel\Ai\AiServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            TelemetryServiceProvider::class,
            LaravelOpenTelemetryServiceProvider::class,
            AiServiceProvider::class,
        ];
    }
}
