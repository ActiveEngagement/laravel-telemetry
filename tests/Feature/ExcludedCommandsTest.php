<?php

declare(strict_types=1);

use ActiveEngagement\Telemetry\Instrumentation\ConsoleInstrumentation;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Keepsuit\LaravelOpenTelemetry\Instrumentation\ConsoleInstrumentation as KeepsuitConsoleInstrumentation;
use OpenTelemetry\SDK\Trace\ImmutableSpan;

/**
 * @param  list<string>  $commands
 * @return list<string>
 */
function tracedCommands(array $commands): array
{
    $kernel = resolve(Kernel::class);
    $kernel->rerouteSymfonyCommandEvents();
    $kernel->setArtisan(null);

    foreach ($commands as $command) {
        Artisan::command($command, fn (): int => 0);
    }

    foreach ($commands as $command) {
        Artisan::call($command);
    }

    return collect(exportedSpans())
        ->map(fn (ImmutableSpan $span): string => $span->getName())
        ->intersect($commands)
        ->values()
        ->all();
}

it('does not trace the default excluded commands', function (): void {
    $defaults = array_map(fn (string $pattern): string => str_replace('*', 'work', $pattern), ConsoleInstrumentation::EXCLUDED);

    expect(config('telemetry.excluded_commands'))->toBe(ConsoleInstrumentation::EXCLUDED)
        ->and(tracedCommands([...$defaults, 'reports:send']))->toBe(['reports:send']);
});

it('does not trace a command that the app adds to its telemetry config', function (): void {
    $this->refreshWithConfig(['telemetry.excluded_commands' => [...ConsoleInstrumentation::EXCLUDED, 'app:listen']]);

    expect(tracedCommands(['app:listen', 'queue:work', 'reports:send']))->toBe(['reports:send']);
});

it('excludes only the commands that the app lists in its telemetry config', function (): void {
    $this->refreshWithConfig(['telemetry.excluded_commands' => ['app:listen']]);

    expect(tracedCommands(['app:listen', 'queue:work']))->toBe(['queue:work']);
});

it('also excludes the commands in the keepsuit config', function (): void {
    $this->refreshWithConfig([
        'opentelemetry.instrumentation.'.KeepsuitConsoleInstrumentation::class => ['enabled' => true, 'excluded' => ['reports:*']],
    ]);

    expect(tracedCommands(['reports:send', 'queue:work', 'orders:import']))->toBe(['orders:import']);
});
