<?php

use Actengage\Telemetry\Contracts\ContextResolver;
use Actengage\Telemetry\Support\KeepsuitContextResolver;
use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;

/**
 * @param  array<mixed>|null  $payload
 */
function runInWorker(?array $payload): void
{
    Auth::forgetGuards();
    Context::flush();
    Context::hydrate($payload);
}

it('carries the logged-in user into a queued job', function (): void {
    $this->actingAs(new GenericUser(['id' => 42]));

    runInWorker(Context::dehydrate());

    expect(app(ContextResolver::class)->userAttributes())->toBe(['user.id' => 42]);
});

it('adds the carried user to logs written in a queued job', function (): void {
    $this->actingAs(new GenericUser(['id' => 42]));

    runInWorker(Context::dehydrate());
    Log::info('inside the job');

    expect(exportedLogs()[0]->getAttributes()->get('user.id'))->toBe(42);
});

it('keeps the original user when a queued job dispatches another job', function (): void {
    Context::addHidden(KeepsuitContextResolver::USER_CONTEXT_KEY, ['user.id' => 7]);
    $this->actingAs(new GenericUser(['id' => 42]));

    runInWorker(Context::dehydrate());

    expect(app(ContextResolver::class)->userAttributes())->toBe(['user.id' => 7]);
});

it('carries nothing when no user is logged in', function (): void {
    expect(Context::dehydrate())->toBeNull();
});

it('carries nothing when user context is off', function (): void {
    config()->set('opentelemetry.user_context', false);
    $this->actingAs(new GenericUser(['id' => 42]));

    expect(Context::dehydrate())->toBeNull();
});

it('does not add the carried user to the live context', function (): void {
    $this->actingAs(new GenericUser(['id' => 42]));

    Context::dehydrate();

    expect(Context::hasHidden(KeepsuitContextResolver::USER_CONTEXT_KEY))->toBeFalse();
});

it('prefers the logged-in user over the carried user', function (): void {
    Context::addHidden(KeepsuitContextResolver::USER_CONTEXT_KEY, ['user.id' => 7]);
    $this->actingAs(new GenericUser(['id' => 42]));

    expect(app(ContextResolver::class)->userAttributes())->toBe(['user.id' => 42]);
});
