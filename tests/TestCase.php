<?php

namespace Tests;

use Actengage\Telemetry\TelemetryServiceProvider;
use Keepsuit\LaravelOpenTelemetry\LaravelOpenTelemetryServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            LaravelOpenTelemetryServiceProvider::class,
            TelemetryServiceProvider::class,
        ];
    }
}
