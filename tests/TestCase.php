<?php

namespace Tests;

use Actengage\Telemetry\TelemetryServiceProvider;
use Keepsuit\LaravelOpenTelemetry\LaravelOpenTelemetryServiceProvider;
use Laravel\Ai\AiServiceProvider;
use Monolog\Handler\NullHandler;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    /**
     * @var array<string, mixed>
     */
    protected array $config = [];

    protected function getPackageProviders($app): array
    {
        return [
            TelemetryServiceProvider::class,
            LaravelOpenTelemetryServiceProvider::class,
            AiServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app->make('config')->set([
            'logging.default' => 'test',
            'logging.channels.test' => ['driver' => 'monolog', 'handler' => NullHandler::class],
            ...$this->config,
        ]);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function refreshWithConfig(array $config): void
    {
        $this->config = $config;

        $this->refreshApplication();
    }
}
