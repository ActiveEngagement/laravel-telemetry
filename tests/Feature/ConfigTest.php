<?php

use ActiveEngagement\Telemetry\TelemetryServiceProvider;
use Illuminate\Support\ServiceProvider;

it('publishes the config file', function (): void {
    $paths = ServiceProvider::pathsToPublish(TelemetryServiceProvider::class, 'telemetry-config');

    expect(array_map(realpath(...), array_keys($paths)))->toBe([realpath(__DIR__.'/../../config/telemetry.php')])
        ->and(array_values($paths))->toBe([config_path('telemetry.php')]);
});
