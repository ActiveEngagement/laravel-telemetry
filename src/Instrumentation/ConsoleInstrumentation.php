<?php

namespace Actengage\Telemetry\Instrumentation;

use Illuminate\Console\Events\CommandFinished;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Str;
use Keepsuit\LaravelOpenTelemetry\Instrumentation\ConsoleInstrumentation as KeepsuitConsoleInstrumentation;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\Context\ScopeInterface;

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
     * @var list<array{0: SpanInterface, 1: ScopeInterface}>
     */
    protected array $failedCommands = [];

    public function __construct(protected Application $app) {}

    /**
     * @param  array{commands?: string[], excluded?: string[]}  $options
     */
    public function register(array $options): void
    {
        $this->excluded = [...static::EXCLUDED, ...array_values($options['excluded'] ?? [])];

        parent::register($options);

        $this->app->terminating($this->endFailedCommands(...));
    }

    public function commandFinished(CommandFinished $event): void
    {
        $trace = $this->startedCommands[$event->input] ?? null;

        if ($event->exitCode === 0 || $trace === null) {
            parent::commandFinished($event);

            return;
        }

        $trace[0]->setStatus(StatusCode::STATUS_ERROR);

        $this->failedCommands[] = $trace;
    }

    public function endFailedCommands(): void
    {
        foreach (array_reverse($this->failedCommands) as [$span, $scope]) {
            if ($span->isRecording()) {
                $scope->detach();
                $span->end();
            }
        }

        $this->failedCommands = [];
    }

    protected function shouldRecord(string $command): bool
    {
        return ! Str::is($this->excluded, $command);
    }
}
