<?php

namespace ActiveEngagement\Telemetry\Listeners;

use Laravel\Ai\Events\AddingFileToStore;
use Laravel\Ai\Events\AddingFileToStoreFailed;
use Laravel\Ai\Events\CreatingStore;
use Laravel\Ai\Events\CreatingStoreFailed;
use Laravel\Ai\Events\FileAddedToStore;
use Laravel\Ai\Events\FileRemovedFromStore;
use Laravel\Ai\Events\RemovingFileFromStore;
use Laravel\Ai\Events\RemovingFileFromStoreFailed;
use Laravel\Ai\Events\StoreCreated;
use Laravel\Ai\Events\StoreDeleted;

class StoreEventSubscriber extends ProviderEventSubscriber
{
    /**
     * @var array<class-string, string>
     */
    protected array $listen = [
        CreatingStore::class => 'creating',
        StoreCreated::class => 'created',
        CreatingStoreFailed::class => 'creatingFailed',
        AddingFileToStore::class => 'addingFile',
        FileAddedToStore::class => 'fileAdded',
        AddingFileToStoreFailed::class => 'addingFileFailed',
        RemovingFileFromStore::class => 'removingFile',
        FileRemovedFromStore::class => 'fileRemoved',
        RemovingFileFromStoreFailed::class => 'removingFileFailed',
        StoreDeleted::class => 'deleted',
    ];

    public function creating(CreatingStore $event): void
    {
        $this->startSpan($event, 'store.create', $event->invocationId, $event->provider, attributes: [
            'laravel.ai.store.name' => $event->name,
        ]);
    }

    public function created(StoreCreated $event): void
    {
        $this->endSpan($event->invocationId, ['gen_ai.data_source.id' => $event->store->id]);
    }

    public function creatingFailed(CreatingStoreFailed $event): void
    {
        $this->spans->fail($event->invocationId, $event->exception);
    }

    public function addingFile(AddingFileToStore $event): void
    {
        $this->startSpan($event, 'store.add_file', $event->invocationId, $event->provider, attributes: [
            'gen_ai.data_source.id' => $event->storeId,
            'laravel.ai.file.id' => $event->fileId,
        ]);
    }

    public function fileAdded(FileAddedToStore $event): void
    {
        $this->endSpan($event->invocationId, ['laravel.ai.document.id' => $event->documentId]);
    }

    public function addingFileFailed(AddingFileToStoreFailed $event): void
    {
        $this->spans->fail($event->invocationId, $event->exception);
    }

    public function removingFile(RemovingFileFromStore $event): void
    {
        $this->startSpan($event, 'store.remove_file', $event->invocationId, $event->provider, attributes: [
            'gen_ai.data_source.id' => $event->storeId,
            'laravel.ai.document.id' => $event->documentId,
        ]);
    }

    public function fileRemoved(FileRemovedFromStore $event): void
    {
        $this->endSpan($event->invocationId);
    }

    public function removingFileFailed(RemovingFileFromStoreFailed $event): void
    {
        $this->spans->fail($event->invocationId, $event->exception);
    }

    public function deleted(StoreDeleted $event): void
    {
        $this->recordSpan($event, 'store.delete', $event->invocationId, $event->provider, [
            'gen_ai.data_source.id' => $event->storeId,
        ]);
    }
}
