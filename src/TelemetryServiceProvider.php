<?php

namespace Actengage\Telemetry;

use Actengage\Telemetry\Console\HeartbeatCommand;
use Actengage\Telemetry\Console\HorizonHeartbeatCommand;
use Actengage\Telemetry\Contracts\ContextResolver;
use Actengage\Telemetry\Support\ActiveSpans;
use Actengage\Telemetry\Support\KeepsuitContextResolver;
use Actengage\Telemetry\Support\WallClock;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Log\Context\Repository as ContextRepository;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\ServiceProvider;
use OpenTelemetry\API\Common\Time\Clock;

class TelemetryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/telemetry.php', 'telemetry');

        $this->app->singleton(ContextResolver::class, KeepsuitContextResolver::class);
        $this->app->singleton(ActiveSpans::class);
    }

    public function boot(Repository $config, Dispatcher $events): void
    {
        $this->registerResourceAttributes($config);
        $this->registerClock();
        $this->registerCommands();
        $this->registerPublishing();

        if (! $config->get('telemetry.enabled')) {
            return;
        }

        Telemetry::startRecording();

        $this->registerUserContext();
        $this->registerSpanCleanup($events);

        $this->app->booted(Telemetry::registerWatchers(...));
    }

    protected function registerResourceAttributes(Repository $config): void
    {
        $deployment = $config->get('telemetry.deployment');
        $configured = $config->get('opentelemetry.resource_attributes');

        $config->set('opentelemetry.resource_attributes', [
            'deployment.environment.name' => $this->app->environment(),
            ...is_string($deployment) && $deployment !== '' ? ['service.version' => $deployment] : [],
            ...is_array($configured) ? $configured : [],
        ]);
    }

    protected function registerSpanCleanup(Dispatcher $events): void
    {
        $abandonOpenSpans = fn () => $this->app->make(ActiveSpans::class)->abandonAll();

        $events->listen([JobProcessed::class, JobExceptionOccurred::class, JobFailed::class], $abandonOpenSpans);

        $this->app->terminating($abandonOpenSpans);
    }

    protected function registerUserContext(): void
    {
        Context::dehydrating(
            fn (ContextRepository $context) => $this->app->make(ContextResolver::class)->dehydrating($context)
        );
    }

    protected function registerClock(): void
    {
        $this->app->booted(fn () => Clock::setDefault(new WallClock));
    }

    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                HeartbeatCommand::class,
                ...Telemetry::isHorizonInstalled() ? [HorizonHeartbeatCommand::class] : [],
            ]);
        }
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
