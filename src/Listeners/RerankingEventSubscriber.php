<?php

namespace Actengage\Telemetry\Listeners;

use Laravel\Ai\Events\Reranked;
use Laravel\Ai\Events\Reranking;
use Laravel\Ai\Events\RerankingFailed;

class RerankingEventSubscriber extends ProviderEventSubscriber
{
    /**
     * @var array<class-string, string>
     */
    protected array $listen = [
        Reranking::class => 'start',
        Reranked::class => 'end',
        RerankingFailed::class => 'fail',
    ];

    public function start(Reranking $event): void
    {
        $this->startSpan($event, 'rerank', $event->invocationId, $event->provider, $event->model);
    }

    public function end(Reranked $event): void
    {
        $this->endSpan($event->invocationId, $this->responseAttributes($event->response->usage, $event->response->meta));
    }

    public function fail(RerankingFailed $event): void
    {
        $this->spans->fail($event->invocationId, $event->exception);
    }
}
