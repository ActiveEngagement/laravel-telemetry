<?php

use Illuminate\Support\Collection;
use Laravel\Ai\Ai;
use Laravel\Ai\Events\AddingFileToStore;
use Laravel\Ai\Events\AddingFileToStoreFailed;
use Laravel\Ai\Events\CreatingStore;
use Laravel\Ai\Events\CreatingStoreFailed;
use Laravel\Ai\Events\RemovingFileFromStore;
use Laravel\Ai\Events\RemovingFileFromStoreFailed;
use Laravel\Ai\Events\StoringFile;
use Laravel\Ai\Events\StoringFileFailed;
use Laravel\Ai\Files;
use Laravel\Ai\Files\Base64Document;
use Laravel\Ai\Providers\Provider;
use Laravel\Ai\Stores;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;

it('traces creating a store', function (): void {
    Stores::fake();

    Stores::create('Knowledge base', provider: 'openai');

    $span = aiSpans('store.create')[0];

    expect($span->getName())->toBe('store.create')
        ->and($span->getKind())->toBe(SpanKind::KIND_CLIENT)
        ->and($span->getAttributes()->toArray())->toMatchArray([
            'gen_ai.provider.name' => 'openai',
            'laravel.ai.store.name' => 'Knowledge base',
            'gen_ai.data_source.id' => Stores::fakeId('Knowledge base'),
        ]);
});

it('traces adding a file to a store and removing it', function (): void {
    Stores::fake();
    $store = Stores::create('Knowledge base', provider: 'openai');

    $store->add('file_1');
    $store->remove('file_1');

    expect(aiSpans('store.add_file')[0]->getAttributes()->toArray())->toMatchArray([
        'gen_ai.data_source.id' => $store->id,
        'laravel.ai.file.id' => 'file_1',
        'laravel.ai.document.id' => 'file_1',
    ])->and(aiSpans('store.remove_file')[0]->getAttributes()->toArray())->toMatchArray([
        'gen_ai.data_source.id' => $store->id,
        'laravel.ai.document.id' => 'file_1',
    ]);
});

it('traces deleting a store', function (): void {
    Stores::fake();

    Stores::delete('vs_1', provider: 'openai');

    expect(aiSpans('store.delete')[0]->getAttributes()->get('gen_ai.data_source.id'))->toBe('vs_1');
});

it('traces storing and deleting a file', function (): void {
    Files::fake();

    $file = Files::put('Hello', 'text/plain', provider: 'openai');
    Files::delete($file->id, provider: 'openai');

    expect(aiSpans('file.store')[0]->getAttributes()->get('laravel.ai.file.id'))->toBe($file->id)
        ->and(aiSpans('file.delete')[0]->getAttributes()->get('laravel.ai.file.id'))->toBe($file->id);
});

dataset('failed storage operations', [
    'store.create' => ['store.create', fn (Provider $provider, Throwable $e): array => [
        new CreatingStore('inv_1', $provider, 'Knowledge base', null, new Collection, null),
        new CreatingStoreFailed('inv_1', $provider, 'Knowledge base', null, new Collection, null, $e),
    ]],
    'store.add_file' => ['store.add_file', fn (Provider $provider, Throwable $e): array => [
        new AddingFileToStore('inv_1', $provider, 'vs_1', 'file_1'),
        new AddingFileToStoreFailed('inv_1', $provider, 'vs_1', 'file_1', $e),
    ]],
    'store.remove_file' => ['store.remove_file', fn (Provider $provider, Throwable $e): array => [
        new RemovingFileFromStore('inv_1', $provider, 'vs_1', 'doc_1'),
        new RemovingFileFromStoreFailed('inv_1', $provider, 'vs_1', 'doc_1', $e),
    ]],
    'file.store' => ['file.store', fn (Provider $provider, Throwable $e): array => [
        new StoringFile('inv_1', $provider, new Base64Document(base64_encode('Hello'), 'text/plain')),
        new StoringFileFailed('inv_1', $provider, new Base64Document(base64_encode('Hello'), 'text/plain'), $e),
    ]],
]);

it('records a failed storage operation', function (string $operation, Closure $events): void {
    $provider = Ai::fakeableStoreProvider('openai');

    foreach ($events($provider, new RuntimeException('The provider is down.')) as $event) {
        event($event);
    }

    $span = aiSpans($operation)[0];

    expect($span->getStatus()->getCode())->toBe(StatusCode::STATUS_ERROR)
        ->and($span->getStatus()->getDescription())->toBe('The provider is down.');
})->with('failed storage operations');
