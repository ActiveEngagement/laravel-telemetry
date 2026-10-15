<?php

namespace Actengage\Telemetry;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\ServiceProvider;

class TelemetryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/telemetry.php', 'telemetry');

        $this->registerLogChannel();
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->registerPublishing();
        }
    }

    protected function registerLogChannel(): void
    {
        $config = $this->app->make(Repository::class);

        if ($config->has('logging.channels.otlp')) {
            return;
        }

        $config->set('logging.channels.otlp', [
            'driver' => 'monolog',
            'handler' => $config->get('telemetry.log_handler'),
            'level' => $config->get('telemetry.log_level'),
        ]);
    }

    protected function registerPublishing(): void
    {
        $this->publishes([
            __DIR__.'/../config/telemetry.php' => config_path('telemetry.php'),
        ], ['telemetry', 'telemetry-config']);
    }
}
