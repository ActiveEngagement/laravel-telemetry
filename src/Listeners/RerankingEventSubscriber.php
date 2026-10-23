<?php

namespace Actengage\Telemetry\Listeners;

use Illuminate\Contracts\Events\Dispatcher;
use Laravel\Ai\Events\Reranked;
use Laravel\Ai\Events\Reranking;
use Laravel\Ai\Events\RerankingFailed;

class RerankingEventSubscriber extends ProviderEventSubscriber
{
    public function start(Reranking $event): void
    {
        $this->startSpan('rerank', $event->invocationId, $event->provider, $event->model);
    }

    public function end(Reranked $event): void
    {
        $this->endSpan($event->invocationId, $this->responseAttributes($event->response->usage, $event->response->meta));
    }

    public function fail(RerankingFailed $event): void
    {
        $this->spans->fail($event->invocationId, $event->exception);
    }

    public function subscribe(Dispatcher $events): array
    {
        return [
            Reranking::class => 'start',
            Reranked::class => 'end',
            RerankingFailed::class => 'fail',
        ];
    }
}
