<?php

namespace Actengage\Telemetry;

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
