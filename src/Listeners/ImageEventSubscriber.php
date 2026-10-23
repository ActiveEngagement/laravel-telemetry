<?php

namespace Actengage\Telemetry\Listeners;

use Laravel\Ai\Events\GeneratingImage;
use Laravel\Ai\Events\ImageFailed;
use Laravel\Ai\Events\ImageGenerated;

class ImageEventSubscriber extends ProviderEventSubscriber
{
    /**
     * @var array<class-string, string>
     */
    protected array $listen = [
        GeneratingImage::class => 'start',
        ImageGenerated::class => 'end',
        ImageFailed::class => 'fail',
    ];

    public function start(GeneratingImage $event): void
    {
        $this->startSpan($event, 'generate_image', $event->invocationId, $event->provider, $event->model);
    }

    public function end(ImageGenerated $event): void
    {
        $this->endSpan($event->invocationId, $this->responseAttributes($event->response->usage, $event->response->meta));
    }

    public function fail(ImageFailed $event): void
    {
        $this->spans->fail($event->invocationId, $event->exception);
    }
}
