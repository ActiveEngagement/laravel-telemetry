<?php

declare(strict_types=1);

namespace ActiveEngagement\Telemetry;

use ActiveEngagement\Telemetry\Browser\BrowserTags;
use ActiveEngagement\Telemetry\Console\HeartbeatCommand;
use ActiveEngagement\Telemetry\Console\HorizonHeartbeatCommand;
use ActiveEngagement\Telemetry\Contracts\BrowserContext;
use ActiveEngagement\Telemetry\Contracts\ContextResolver;
use ActiveEngagement\Telemetry\Instrumentation\ConsoleInstrumentation;
use ActiveEngagement\Telemetry\Instrumentation\HttpClientInstrumentation;
use ActiveEngagement\Telemetry\Instrumentation\HttpServerInstrumentation;
use ActiveEngagement\Telemetry\Instrumentation\QueueInstrumentation;
use ActiveEngagement\Telemetry\Instrumentation\TraceRequestMiddleware;
use ActiveEngagement\Telemetry\Support\ActiveSpans;
use ActiveEngagement\Telemetry\Support\KeepsuitBrowserContext;
use ActiveEngagement\Telemetry\Support\KeepsuitContextResolver;
use ActiveEngagement\Telemetry\Support\WallClock;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Log\Context\Repository as ContextRepository;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\Compilers\BladeCompiler;
use Inertia\Inertia;
use Keepsuit\LaravelOpenTelemetry\Instrumentation\ConsoleInstrumentation as KeepsuitConsoleInstrumentation;
use Keepsuit\LaravelOpenTelemetry\Instrumentation\HttpClientInstrumentation as KeepsuitHttpClientInstrumentation;
use Keepsuit\LaravelOpenTelemetry\Instrumentation\HttpServerInstrumentation as KeepsuitHttpServerInstrumentation;
use Keepsuit\LaravelOpenTelemetry\Instrumentation\QueueInstrumentation as KeepsuitQueueInstrumentation;
use Keepsuit\LaravelOpenTelemetry\Instrumentation\Support\Http\Server\TraceRequestMiddleware as KeepsuitTraceRequestMiddleware;
use OpenTelemetry\API\Common\Time\Clock;
use Override;

class TelemetryServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/telemetry.php', 'telemetry');

        $this->app->singleton(ContextResolver::class, KeepsuitContextResolver::class);
        $this->app->singleton(BrowserContext::class, KeepsuitBrowserContext::class);
        $this->app->singleton(ActiveSpans::class);
        $this->app->bind(KeepsuitConsoleInstrumentation::class, ConsoleInstrumentation::class);
        $this->app->bind(KeepsuitQueueInstrumentation::class, QueueInstrumentation::class);
        $this->app->bind(KeepsuitHttpServerInstrumentation::class, HttpServerInstrumentation::class);
        $this->app->bind(KeepsuitHttpClientInstrumentation::class, HttpClientInstrumentation::class);
        $this->app->bind(KeepsuitTraceRequestMiddleware::class, TraceRequestMiddleware::class);

        $this->app->booting(fn () => $this->registerResourceAttributes($this->app->make(Repository::class)));
    }

    public function boot(Repository $config, Dispatcher $events): void
    {
        $this->registerClock();
        $this->registerCommands();
        $this->registerPublishing();
        $this->registerBladeDirective();

        if (! $config->get('telemetry.enabled')) {
            return;
        }

        Telemetry::startRecording();

        $this->registerUserContext();
        $this->registerSpanCleanup($events);
        $this->registerInertiaUser($config);

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
        Context::dehydrating(fn (ContextRepository $context) => rescue(
            fn () => $this->app->make(ContextResolver::class)->dehydrating($context), report: false
        ));
    }

    protected function registerBladeDirective(): void
    {
        $this->callAfterResolving(BladeCompiler::class, function (BladeCompiler $blade): void {
            $blade->directive('telemetry', BrowserTags::compile(...));
        });
    }

    protected function registerInertiaUser(Repository $config): void
    {
        if (! Telemetry::isInertiaInstalled() || ! $config->get('telemetry.browser.enabled')) {
            return;
        }

        Inertia::share('telemetry', fn (): array => [
            'user' => rescue(fn (): ?array => $this->app->make(BrowserContext::class)->user()),
        ]);
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
