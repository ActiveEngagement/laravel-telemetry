<?php

declare(strict_types=1);

namespace Tests;

use ActiveEngagement\Telemetry\TelemetryServiceProvider;
use Illuminate\Contracts\Config\Repository;
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
            LaravelOpenTelemetryServiceProvider::class,
            TelemetryServiceProvider::class,
            AiServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app->make(Repository::class)->set([
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
