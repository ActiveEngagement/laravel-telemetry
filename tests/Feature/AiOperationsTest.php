<?php

use ActiveEngagement\Telemetry\Support\KeepsuitContextResolver;
use Illuminate\Support\Facades\Context;
use Laravel\Ai\Audio;
use Laravel\Ai\Classification;
use Laravel\Ai\Classification\Boolean;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Image;
use Laravel\Ai\Reranking;
use Laravel\Ai\Responses\AudioResponse;
use Laravel\Ai\Responses\ClassificationResponse;
use Laravel\Ai\Responses\EmbeddingsResponse;
use Laravel\Ai\Responses\ImageResponse;
use Laravel\Ai\Responses\RerankingResponse;
use Laravel\Ai\Responses\TranscriptionResponse;
use Laravel\Ai\Transcription;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;

dataset('provider operations', [
    'embeddings' => ['embeddings', Embeddings::fake(...), fn (): EmbeddingsResponse => Embeddings::for(['Hello'])->generate()],
    'rerank' => ['rerank', Reranking::fake(...), fn (): RerankingResponse => Reranking::of(['First', 'Second'])->rerank('First')],
    'image' => ['generate_image', Image::fake(...), fn (): ImageResponse => Image::of('A red bicycle')->generate()],
    'audio' => ['generate_audio', Audio::fake(...), fn (): AudioResponse => Audio::of('Hello')->generate()],
    'transcription' => ['transcription', Transcription::fake(...), fn (): TranscriptionResponse => Transcription::fromBase64(base64_encode('audio'), 'audio/mpeg')->generate()],
    'classification' => ['classify', Classification::fake(...), fn (): ClassificationResponse => Classification::of('I love it')->question('positive', new Boolean('Is it positive?'))->classify()],
]);

it('traces a provider operation', function (string $operation, Closure $fake, Closure $call): void {
    $fake();

    $call();

    $spans = aiSpans($operation);

    expect($spans)->toHaveCount(1)
        ->and($spans[0]->getName())->toStartWith($operation.' ')
        ->and($spans[0]->getKind())->toBe(SpanKind::KIND_CLIENT)
        ->and($spans[0]->getAttributes()->toArray())->toHaveKeys([
            'gen_ai.provider.name',
            'gen_ai.request.model',
            'gen_ai.usage.input_tokens',
            'gen_ai.usage.output_tokens',
        ]);
})->with('provider operations');

it('records a failed provider operation', function (string $operation, Closure $fake, Closure $call): void {
    $fake(fn () => throw new RuntimeException('The provider is down.'));

    expect($call)->toThrow(RuntimeException::class);

    $span = aiSpans($operation)[0];

    expect($span->getStatus()->getCode())->toBe(StatusCode::STATUS_ERROR)
        ->and($span->getStatus()->getDescription())->toBe('The provider is down.');
})->with('provider operations');

it('adds the user carried into a queued job to a provider operation span', function (): void {
    Context::addHidden(KeepsuitContextResolver::USER_CONTEXT_KEY, ['user.id' => 7]);
    Embeddings::fake();

    Embeddings::for(['Hello'])->generate();

    expect(aiSpans('embeddings')[0]->getAttributes()->get('user.id'))->toBe(7);
});
