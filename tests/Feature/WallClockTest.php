<?php

use ActiveEngagement\Telemetry\Support\WallClock;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use OpenTelemetry\API\Common\Time\Clock;
use OpenTelemetry\API\Common\Time\ClockInterface;
use OpenTelemetry\API\Common\Time\SystemClock;

function wallClockNanos(): int
{
    return (int) (new DateTimeImmutable)->format('Uu') * 1000;
}

function shiftSystemClockReference(int $nanos): int
{
    SystemClock::create();

    $reference = new ReflectionProperty(SystemClock::class, 'referenceTime');
    $original = $reference->getValue();
    $reference->setValue(null, $original + $nanos);

    return $original;
}

it('makes the wall clock the default OpenTelemetry clock', function (): void {
    expect(Clock::getDefault())->toBeInstanceOf(WallClock::class);
});

it('reads the wall clock on every call', function (): void {
    $before = wallClockNanos();
    $now = (new WallClock)->now();
    $after = wallClockNanos();

    expect($now)->toBeGreaterThanOrEqual($before)
        ->and($now)->toBeLessThanOrEqual($after);
});

it('follows the Carbon test time', function (): void {
    CarbonImmutable::setTestNow('2026-10-20 12:00:00.123456 UTC');

    try {
        expect((new WallClock)->now())->toBe(1792497600123456000);
    } finally {
        CarbonImmutable::setTestNow();
    }
});

it('timestamps logs with the wall clock after the process was frozen', function (): void {
    $original = shiftSystemClockReference(-400 * ClockInterface::NANOS_PER_SECOND);

    try {
        $before = wallClockNanos();
        Log::info('after a freeze');
        $after = wallClockNanos();
    } finally {
        new ReflectionProperty(SystemClock::class, 'referenceTime')->setValue(null, $original);
    }

    expect(exportedLogs()[0]->getTimestamp())->toBeGreaterThanOrEqual($before)
        ->and(exportedLogs()[0]->getTimestamp())->toBeLessThanOrEqual($after);
});
