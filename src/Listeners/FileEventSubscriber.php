<?php

namespace Actengage\Telemetry\Listeners;

use Illuminate\Contracts\Events\Dispatcher;
use Laravel\Ai\Events\FileDeleted;
use Laravel\Ai\Events\FileStored;
use Laravel\Ai\Events\StoringFile;
use Laravel\Ai\Events\StoringFileFailed;

class FileEventSubscriber extends ProviderEventSubscriber
{
    public function storing(StoringFile $event): void
    {
        $this->startSpan($event, 'file.store', $event->invocationId, $event->provider);
    }

    public function stored(FileStored $event): void
    {
        $this->endSpan($event->invocationId, ['laravel.ai.file.id' => $event->response->id]);
    }

    public function storingFailed(StoringFileFailed $event): void
    {
        $this->spans->fail($event->invocationId, $event->exception);
    }

    public function deleted(FileDeleted $event): void
    {
        $this->recordSpan($event, 'file.delete', $event->invocationId, $event->provider, [
            'laravel.ai.file.id' => $event->fileId,
        ]);
    }

    public function subscribe(Dispatcher $events): array
    {
        return [
            StoringFile::class => 'storing',
            FileStored::class => 'stored',
            StoringFileFailed::class => 'storingFailed',
            FileDeleted::class => 'deleted',
        ];
    }
}
