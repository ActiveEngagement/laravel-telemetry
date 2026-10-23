<?php

use ActiveEngagement\Telemetry\Telemetry;
use Illuminate\Support\Facades\Log;

it('starts recording when telemetry is enabled', function (): void {
    expect(Telemetry::isRecording())->toBeTrue();
});

it('does not start recording when TELEMETRY_ENABLED is false', function (): void {
    Telemetry::stopRecording();

    refreshWithEnv('TELEMETRY_ENABLED', 'false');

    expect(Telemetry::isRecording())->toBeFalse();
});

it('records nothing while recording is stopped', function (): void {
    Telemetry::stopRecording();

    Log::error('not recorded');

    expect(exportedLogs())->toBeEmpty();
});

it('records nothing inside withoutRecording', function (): void {
    Telemetry::withoutRecording(fn () => Log::error('not recorded'));
    Log::error('recorded');

    $logs = exportedLogs();

    expect($logs)->toHaveCount(1)
        ->and($logs[0]->getBody())->toBe('recorded');
});

it('returns the value of the withoutRecording callback', function (): void {
    expect(Telemetry::withoutRecording(fn (): string => 'done'))->toBe('done');
});

it('restores recording when the withoutRecording callback throws', function (): void {
    try {
        Telemetry::withoutRecording(fn () => throw new RuntimeException('failed'));
    } catch (RuntimeException) {
    }

    expect(Telemetry::isRecording())->toBeTrue();
});
