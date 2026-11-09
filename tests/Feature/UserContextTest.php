<?php

declare(strict_types=1);

use ActiveEngagement\Telemetry\Contracts\ContextResolver;
use ActiveEngagement\Telemetry\Support\KeepsuitContextResolver;
use ActiveEngagement\Telemetry\Telemetry;
use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Tests\Fixtures\User;

/**
 * @param  array<mixed>|null  $payload
 */
function runInWorker(?array $payload): void
{
    Auth::forgetGuards();
    Context::flush();
    Context::hydrate($payload);
}

it('collects the id, name and email of the user by default', function (): void {
    $user = new GenericUser(['id' => 42, 'name' => 'Ada Lovelace', 'email' => 'ada@example.com']);

    expect(resolve(ContextResolver::class)->userAttributes($user))->toBe([
        'user.id' => 42,
        'user.name' => 'Ada Lovelace',
        'user.email' => 'ada@example.com',
    ]);
});

it('collects only the id of a user without a name or email', function (): void {
    expect(resolve(ContextResolver::class)->userAttributes(new GenericUser(['id' => 42])))->toBe(['user.id' => 42]);
});

it('collects only the id of a strict model without a name or email', function (): void {
    Model::preventAccessingMissingAttributes();

    try {
        $attributes = resolve(ContextResolver::class)->userAttributes((new User)->newFromBuilder(['id' => 42]));
    } finally {
        Model::preventAccessingMissingAttributes(false);
    }

    expect($attributes)->toBe(['user.id' => 42]);
});

it('collects the attributes of the Telemetry::user resolver instead of the default', function (): void {
    Telemetry::user(fn (Authenticatable $user): array => ['user.id' => $user->getAuthIdentifier(), 'user.plan' => 'pro']);

    $attributes = resolve(ContextResolver::class)->userAttributes(new GenericUser(['id' => 42, 'name' => 'Ada Lovelace']));

    expect($attributes)->toBe(['user.id' => 42, 'user.plan' => 'pro']);
});

it('keeps a Telemetry::user resolver that the app sets before the package boots', function (): void {
    Telemetry::user(fn (): array => ['user.id' => 'custom']);

    $this->refreshApplication();

    expect(resolve(ContextResolver::class)->userAttributes(new GenericUser(['id' => 42])))->toBe(['user.id' => 'custom']);
});

it('collects no user attributes when user context is off', function (): void {
    config()->set('opentelemetry.user_context', false);

    $user = new GenericUser(['id' => 42, 'name' => 'Ada Lovelace', 'email' => 'ada@example.com']);

    expect(resolve(ContextResolver::class)->userAttributes($user))->toBe([]);
});

it('carries the logged-in user into a queued job', function (): void {
    $this->actingAs(new GenericUser(['id' => 42]));

    runInWorker(Context::dehydrate());

    expect(resolve(ContextResolver::class)->userAttributes())->toBe(['user.id' => 42]);
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

    expect(resolve(ContextResolver::class)->userAttributes())->toBe(['user.id' => 7]);
});

it('keeps dispatching jobs when the user resolver fails', function (): void {
    Telemetry::user(fn (): never => throw new RuntimeException('Resolver is broken'));
    $this->actingAs(new GenericUser(['id' => 42]));

    expect(Context::dehydrate())->toBeNull();
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

    expect(resolve(ContextResolver::class)->userAttributes())->toBe(['user.id' => 42]);
});
