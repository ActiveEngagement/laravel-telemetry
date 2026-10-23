<?php

namespace Actengage\Telemetry\Listeners;

use Laravel\Ai\Events\ClassificationFailed;
use Laravel\Ai\Events\Classified;
use Laravel\Ai\Events\Classifying;

class ClassificationEventSubscriber extends ProviderEventSubscriber
{
    /**
     * @var array<class-string, string>
     */
    protected array $listen = [
        Classifying::class => 'start',
        Classified::class => 'end',
        ClassificationFailed::class => 'fail',
    ];

    public function start(Classifying $event): void
    {
        $this->startSpan($event, 'classify', $event->invocationId, $event->provider, $event->model);
    }

    public function end(Classified $event): void
    {
        $this->endSpan($event->invocationId, $this->responseAttributes($event->response->usage, $event->response->meta));
    }

    public function fail(ClassificationFailed $event): void
    {
        $this->spans->fail($event->invocationId, $event->exception);
    }
}
