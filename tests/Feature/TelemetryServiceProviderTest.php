<?php

declare(strict_types=1);

use ActiveEngagement\Telemetry\TelemetryServiceProvider;
use Keepsuit\LaravelOpenTelemetry\LaravelOpenTelemetryServiceProvider;

it('loads the telemetry service provider', function (): void {
    expect($this->app->providerIsLoaded(TelemetryServiceProvider::class))->toBeTrue();
});

it('loads the OpenTelemetry service provider', function (): void {
    expect($this->app->providerIsLoaded(LaravelOpenTelemetryServiceProvider::class))->toBeTrue();
});
