<?php

namespace Actengage\Telemetry\Listeners;

use Illuminate\Contracts\Events\Dispatcher;
use Laravel\Ai\Events\GeneratingImage;
use Laravel\Ai\Events\ImageFailed;
use Laravel\Ai\Events\ImageGenerated;

class ImageEventSubscriber extends ProviderEventSubscriber
{
    public function start(GeneratingImage $event): void
    {
        $this->startSpan('generate_image', $event->invocationId, $event->provider, $event->model);
    }

    public function end(ImageGenerated $event): void
    {
        $this->endSpan($event->invocationId, $event->response->usage, $event->response->meta);
    }

    public function fail(ImageFailed $event): void
    {
        $this->spans->fail($event->invocationId, $event->exception);
    }

    public function subscribe(Dispatcher $events): array
    {
        return [
            GeneratingImage::class => 'start',
            ImageGenerated::class => 'end',
            ImageFailed::class => 'fail',
        ];
    }
}
