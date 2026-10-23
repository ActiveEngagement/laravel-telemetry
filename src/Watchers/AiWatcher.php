<?php

declare(strict_types=1);

namespace ActiveEngagement\Telemetry\Watchers;

use ActiveEngagement\Telemetry\Listeners\AgentEventSubscriber;
use ActiveEngagement\Telemetry\Listeners\AudioEventSubscriber;
use ActiveEngagement\Telemetry\Listeners\ClassificationEventSubscriber;
use ActiveEngagement\Telemetry\Listeners\EmbeddingsEventSubscriber;
use ActiveEngagement\Telemetry\Listeners\FileEventSubscriber;
use ActiveEngagement\Telemetry\Listeners\ImageEventSubscriber;
use ActiveEngagement\Telemetry\Listeners\RerankingEventSubscriber;
use ActiveEngagement\Telemetry\Listeners\StoreEventSubscriber;
use ActiveEngagement\Telemetry\Listeners\ToolEventSubscriber;
use ActiveEngagement\Telemetry\Listeners\TranscriptionEventSubscriber;
use ActiveEngagement\Telemetry\Telemetry;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;

class AiWatcher extends Watcher
{
    /**
     * @var list<class-string>
     */
    protected array $subscribe = [
        AgentEventSubscriber::class,
        AudioEventSubscriber::class,
        ClassificationEventSubscriber::class,
        EmbeddingsEventSubscriber::class,
        FileEventSubscriber::class,
        ImageEventSubscriber::class,
        RerankingEventSubscriber::class,
        StoreEventSubscriber::class,
        ToolEventSubscriber::class,
        TranscriptionEventSubscriber::class,
    ];

    public function register(Application $app): void
    {
        if (! Telemetry::isAiInstalled()) {
            return;
        }

        $events = $app->make(Dispatcher::class);

        foreach ($this->subscribe as $subscriber) {
            $events->subscribe($subscriber);
        }
    }
}
