<?php

namespace ActiveEngagement\Telemetry\Listeners;

use Laravel\Ai\Events\EmbeddingsFailed;
use Laravel\Ai\Events\EmbeddingsGenerated;
use Laravel\Ai\Events\GeneratingEmbeddings;

class EmbeddingsEventSubscriber extends ProviderEventSubscriber
{
    /**
     * @var array<class-string, string>
     */
    protected array $listen = [
        GeneratingEmbeddings::class => 'start',
        EmbeddingsGenerated::class => 'end',
        EmbeddingsFailed::class => 'fail',
    ];

    public function start(GeneratingEmbeddings $event): void
    {
        $this->startSpan($event, 'embeddings', $event->invocationId, $event->provider, $event->model);
    }

    public function end(EmbeddingsGenerated $event): void
    {
        $this->endSpan($event->invocationId, $this->responseAttributes($event->response->usage, $event->response->meta));
    }

    public function fail(EmbeddingsFailed $event): void
    {
        $this->spans->fail($event->invocationId, $event->exception);
    }
}
