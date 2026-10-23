<?php

namespace Actengage\Telemetry\Listeners;

use Laravel\Ai\Events\GeneratingTranscription;
use Laravel\Ai\Events\TranscriptionFailed;
use Laravel\Ai\Events\TranscriptionGenerated;

class TranscriptionEventSubscriber extends ProviderEventSubscriber
{
    /**
     * @var array<class-string, string>
     */
    protected array $listen = [
        GeneratingTranscription::class => 'start',
        TranscriptionGenerated::class => 'end',
        TranscriptionFailed::class => 'fail',
    ];

    public function start(GeneratingTranscription $event): void
    {
        $this->startSpan($event, 'transcription', $event->invocationId, $event->provider, $event->model);
    }

    public function end(TranscriptionGenerated $event): void
    {
        $this->endSpan($event->invocationId, $this->responseAttributes($event->response->usage, $event->response->meta));
    }

    public function fail(TranscriptionFailed $event): void
    {
        $this->spans->fail($event->invocationId, $event->exception);
    }
}
