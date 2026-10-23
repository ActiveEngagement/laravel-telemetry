<?php

namespace Actengage\Telemetry\Watchers;

use Actengage\Telemetry\Listeners;
use Actengage\Telemetry\Telemetry;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;

class AiWatcher extends Watcher
{
    /**
     * @var list<class-string>
     */
    protected array $subscribe = [
        Listeners\AgentEventSubscriber::class,
        Listeners\AudioEventSubscriber::class,
        Listeners\ClassificationEventSubscriber::class,
        Listeners\EmbeddingsEventSubscriber::class,
        Listeners\FileEventSubscriber::class,
        Listeners\ImageEventSubscriber::class,
        Listeners\RerankingEventSubscriber::class,
        Listeners\StoreEventSubscriber::class,
        Listeners\ToolEventSubscriber::class,
        Listeners\TranscriptionEventSubscriber::class,
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
