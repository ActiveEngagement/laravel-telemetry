<?php

namespace Actengage\Telemetry;

use Laravel\Ai\Events\PromptingAgent;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;

class Telemetry
{
    use RegistersWatchers;

    public static bool $shouldRecord = false;

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

    public static function isHorizonInstalled(): bool
    {
        return interface_exists(MasterSupervisorRepository::class);
    }

    public static function isAiInstalled(): bool
    {
        return class_exists(PromptingAgent::class);
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
