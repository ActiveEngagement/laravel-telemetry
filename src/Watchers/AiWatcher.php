<?php

namespace Actengage\Telemetry\Watchers;

use Actengage\Telemetry\Listeners\AgentEventSubscriber;
use Actengage\Telemetry\Listeners\ToolEventSubscriber;
use Actengage\Telemetry\Telemetry;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;

class AiWatcher extends Watcher
{
    public function register(Application $app): void
    {
        if (! Telemetry::isAiInstalled()) {
            return;
        }

        $events = $app->make(Dispatcher::class);

        $events->subscribe(AgentEventSubscriber::class);
        $events->subscribe(ToolEventSubscriber::class);
    }
}
