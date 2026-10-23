<?php

namespace Actengage\Telemetry\Listeners;

use Illuminate\Contracts\Events\Dispatcher;
use Laravel\Ai\Events\AudioFailed;
use Laravel\Ai\Events\AudioGenerated;
use Laravel\Ai\Events\GeneratingAudio;

class AudioEventSubscriber extends ProviderEventSubscriber
{
    public function start(GeneratingAudio $event): void
    {
        $this->startSpan('generate_audio', $event->invocationId, $event->provider, $event->model);
    }

    public function end(AudioGenerated $event): void
    {
        $this->endSpan($event->invocationId, $this->responseAttributes($event->response->usage, $event->response->meta));
    }

    public function fail(AudioFailed $event): void
    {
        $this->spans->fail($event->invocationId, $event->exception);
    }

    public function subscribe(Dispatcher $events): array
    {
        return [
            GeneratingAudio::class => 'start',
            AudioGenerated::class => 'end',
            AudioFailed::class => 'fail',
        ];
    }
}
