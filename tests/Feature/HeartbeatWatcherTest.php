<?php

use Actengage\Telemetry\Jobs\RecordQueueHeartbeat;
use Actengage\Telemetry\Watchers\HeartbeatWatcher;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;

/**
 * @param  array<string, mixed>  $options
 * @return list<string>
 */
function scheduledHeartbeats(array $options): array
{
    $schedule = new Schedule;

    app()->make(HeartbeatWatcher::class, ['options' => $options])->schedule($schedule);

    return array_map(
        fn (Event $event): string => $event->expression.' '.($event->command ?? $event->description),
        $schedule->events(),
    );
}

it('schedules the scheduler heartbeat every minute by default', function (): void {
    expect(scheduledHeartbeats([]))->toHaveCount(1)
        ->and(scheduledHeartbeats([])[0])->toStartWith('* * * * *')
        ->and(scheduledHeartbeats([])[0])->toContain('telemetry:heartbeat');
});

it('does not schedule the scheduler heartbeat when it is off', function (): void {
    expect(scheduledHeartbeats(['scheduler' => false]))->toBeEmpty();
});

it('schedules the Horizon heartbeat in horizon mode', function (): void {
    $events = scheduledHeartbeats(['scheduler' => false, 'queue' => 'horizon']);

    expect($events)->toHaveCount(1)
        ->and($events[0])->toStartWith('* * * * *')
        ->and($events[0])->toContain('telemetry:horizon-heartbeat');
});

it('schedules the queue heartbeat job in queue mode', function (): void {
    $events = scheduledHeartbeats(['scheduler' => false, 'queue' => 'queue']);

    expect($events)->toHaveCount(1)
        ->and($events[0])->toStartWith('* * * * *')
        ->and($events[0])->toContain(RecordQueueHeartbeat::class);
});

it('schedules no queue heartbeat in none mode', function (): void {
    expect(scheduledHeartbeats(['scheduler' => false, 'queue' => 'none']))->toBeEmpty();
});

it('rejects an unknown queue mode', function (): void {
    app()->make(HeartbeatWatcher::class, ['options' => ['queue' => 'cloud']])->register(app());
})->throws(InvalidArgumentException::class);

it('schedules the heartbeats from the config when the app boots', function (): void {
    refreshWithEnv('TELEMETRY_QUEUE_MONITOR', 'queue');

    $commands = array_map(
        fn (Event $event): string => (string) ($event->command ?? $event->description),
        app(Schedule::class)->events(),
    );

    expect($commands)->toHaveCount(2)
        ->and(implode(' ', $commands))->toContain('telemetry:heartbeat')
        ->and(implode(' ', $commands))->toContain(RecordQueueHeartbeat::class);
});
