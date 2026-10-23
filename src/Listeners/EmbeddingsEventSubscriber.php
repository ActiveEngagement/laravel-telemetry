<?php

namespace Actengage\Telemetry\Listeners;

use Illuminate\Contracts\Events\Dispatcher;
use Laravel\Ai\Events\EmbeddingsFailed;
use Laravel\Ai\Events\EmbeddingsGenerated;
use Laravel\Ai\Events\GeneratingEmbeddings;

class EmbeddingsEventSubscriber extends ProviderEventSubscriber
{
    public function start(GeneratingEmbeddings $event): void
    {
        $this->startSpan('embeddings', $event->invocationId, $event->provider, $event->model);
    }

    public function end(EmbeddingsGenerated $event): void
    {
        $this->endSpan($event->invocationId, $this->responseAttributes($event->response->usage, $event->response->meta));
    }

    public function fail(EmbeddingsFailed $event): void
    {
        $this->spans->fail($event->invocationId, $event->exception);
    }

    public function subscribe(Dispatcher $events): array
    {
        return [
            GeneratingEmbeddings::class => 'start',
            EmbeddingsGenerated::class => 'end',
            EmbeddingsFailed::class => 'fail',
        ];
    }
}
