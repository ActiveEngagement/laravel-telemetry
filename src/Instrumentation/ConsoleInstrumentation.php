<?php

namespace Actengage\Telemetry\Instrumentation;

use Illuminate\Support\Str;
use Keepsuit\LaravelOpenTelemetry\Instrumentation\ConsoleInstrumentation as KeepsuitConsoleInstrumentation;

class ConsoleInstrumentation extends KeepsuitConsoleInstrumentation
{
    /**
     * @var list<string>
     */
    public const array EXCLUDED = [
        'help',
        'list',
        'tinker',
        'serve',
        'pail',
        'queue:work',
        'queue:listen',
        'horizon',
        'horizon:*',
        'schedule:run',
        'schedule:work',
        'schedule:finish',
        'octane:*',
        'reverb:start',
        'pulse:work',
        'pulse:check',
        'nightwatch:agent',
        'telemetry:*',
    ];

    /**
     * @var list<string>
     */
    protected array $excluded = self::EXCLUDED;

    /**
     * @param  array{commands?: string[], excluded?: string[]}  $options
     */
    public function register(array $options): void
    {
        $this->excluded = [...static::EXCLUDED, ...array_values($options['excluded'] ?? [])];

        parent::register($options);
    }

    protected function shouldRecord(string $command): bool
    {
        return ! Str::is($this->excluded, $command);
    }
}
