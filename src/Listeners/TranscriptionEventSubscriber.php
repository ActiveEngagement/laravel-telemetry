<?php

namespace Actengage\Telemetry\Listeners;

use Illuminate\Contracts\Events\Dispatcher;
use Laravel\Ai\Events\GeneratingTranscription;
use Laravel\Ai\Events\TranscriptionFailed;
use Laravel\Ai\Events\TranscriptionGenerated;

class TranscriptionEventSubscriber extends ProviderEventSubscriber
{
    public function start(GeneratingTranscription $event): void
    {
        $this->startSpan('transcription', $event->invocationId, $event->provider, $event->model);
    }

    public function end(TranscriptionGenerated $event): void
    {
        $this->endSpan($event->invocationId, $event->response->usage, $event->response->meta);
    }

    public function fail(TranscriptionFailed $event): void
    {
        $this->spans->fail($event->invocationId, $event->exception);
    }

    public function subscribe(Dispatcher $events): array
    {
        return [
            GeneratingTranscription::class => 'start',
            TranscriptionGenerated::class => 'end',
            TranscriptionFailed::class => 'fail',
        ];
    }
}
