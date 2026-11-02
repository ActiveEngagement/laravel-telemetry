<?php

declare(strict_types=1);

namespace ActiveEngagement\Telemetry;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Inertia\Inertia;
use Laravel\Ai\Events\PromptingAgent;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;

class Telemetry
{
    use RegistersWatchers;

    public static bool $shouldRecord = false;

    /**
     * @var (Closure(Authenticatable): array<non-empty-string, bool|int|float|string|array<mixed>|null>)|null
     */
    public static ?Closure $userResolver = null;

    public static function startRecording(): void
    {
        static::$shouldRecord = true;
    }

    public static function stopRecording(): void
    {
        static::$shouldRecord = false;
    }

    public static function isRecording(): bool
    {
        return static::$shouldRecord;
    }

    /**
     * @param  Closure(Authenticatable): array<non-empty-string, bool|int|float|string|array<mixed>|null>  $resolver
     */
    public static function user(Closure $resolver): void
    {
        static::$userResolver = $resolver;
    }

    /**
     * @return array<non-empty-string, bool|int|float|string|array<mixed>|null>
     */
    public static function collectUserAttributes(Authenticatable $user): array
    {
        if (static::$userResolver instanceof Closure) {
            return (static::$userResolver)($user);
        }

        return array_filter([
            'user.id' => $user->getAuthIdentifier(),
            'user.name' => data_get($user, 'name'),
            'user.email' => data_get($user, 'email'),
        ], is_scalar(...));
    }

    public static function isHorizonInstalled(): bool
    {
        return interface_exists(MasterSupervisorRepository::class);
    }

    public static function isAiInstalled(): bool
    {
        return class_exists(PromptingAgent::class);
    }

    public static function isInertiaInstalled(): bool
    {
        return class_exists(Inertia::class);
    }

    /**
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public static function withoutRecording(callable $callback): mixed
    {
        $shouldRecord = static::$shouldRecord;

        static::$shouldRecord = false;

        try {
            return $callback();
        } finally {
            static::$shouldRecord = $shouldRecord;
        }
    }
}
