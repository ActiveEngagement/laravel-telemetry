<?php

namespace Actengage\Telemetry;

use Actengage\Telemetry\Contracts\ContextResolver;
use Actengage\Telemetry\Support\KeepsuitContextResolver;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\ServiceProvider;

class TelemetryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/telemetry.php', 'telemetry');

        $this->app->singleton(ContextResolver::class, KeepsuitContextResolver::class);
    }

    public function boot(Repository $config): void
    {
        $this->registerPublishing();

        if (! $config->get('telemetry.enabled')) {
            return;
        }

        Telemetry::startRecording();

        $this->app->booted(Telemetry::registerWatchers(...));
    }

    protected function registerPublishing(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/telemetry.php' => config_path('telemetry.php'),
            ], ['telemetry', 'telemetry-config']);
        }
    }
}
