<?php

declare(strict_types=1);

namespace ActiveEngagement\Telemetry\Listeners;

use Laravel\Ai\Events\AudioFailed;
use Laravel\Ai\Events\AudioGenerated;
use Laravel\Ai\Events\GeneratingAudio;

class AudioEventSubscriber extends ProviderEventSubscriber
{
    /**
     * @var array<class-string, string>
     */
    protected array $listen = [
        GeneratingAudio::class => 'start',
        AudioGenerated::class => 'end',
        AudioFailed::class => 'fail',
    ];

    public function start(GeneratingAudio $event): void
    {
        $this->startSpan($event, 'generate_audio', $event->invocationId, $event->provider, $event->model);
    }

    public function end(AudioGenerated $event): void
    {
        $this->endSpan($event->invocationId, $this->responseAttributes($event->response->usage, $event->response->meta));
    }

    public function fail(AudioFailed $event): void
    {
        $this->spans->fail($event->invocationId, $event->exception);
    }
}
