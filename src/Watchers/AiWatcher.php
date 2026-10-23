<?php

namespace Actengage\Telemetry\Watchers;

use Actengage\Telemetry\Telemetry;
use Actengage\Telemetry\Watchers\Ai\AgentEventSubscriber;
use Actengage\Telemetry\Watchers\Ai\ToolEventSubscriber;
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
