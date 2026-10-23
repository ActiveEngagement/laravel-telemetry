<?php

declare(strict_types=1);

use ActiveEngagement\Telemetry\Events\HeartbeatRecorded;
use ActiveEngagement\Telemetry\Jobs\RecordQueueHeartbeat;
use ActiveEngagement\Telemetry\Support\Heartbeat;
use ActiveEngagement\Telemetry\Telemetry;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Event;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use OpenTelemetry\API\Logs\Severity;
use Tests\Fixtures\FakeMasterSupervisorRepository;

it('records a heartbeat as an info log in OpenTelemetry', function (): void {
    resolve(Heartbeat::class)->record('scheduler-heartbeat', ['region' => 'us']);

    $logs = exportedLogs();

    expect($logs)->toHaveCount(1)
        ->and($logs[0]->getBody())->toBe('scheduler-heartbeat')
        ->and($logs[0]->getSeverityNumber())->toBe(Severity::INFO->value)
        ->and($logs[0]->getAttributes()->toArray())->toBe(['region' => 'us']);
});

it('dispatches HeartbeatRecorded after recording', function (): void {
    Event::fake([HeartbeatRecorded::class]);

    resolve(Heartbeat::class)->record('scheduler-heartbeat', ['region' => 'us']);

    Event::assertDispatched(HeartbeatRecorded::class, fn (HeartbeatRecorded $event): bool => $event->name === 'scheduler-heartbeat'
        && $event->attributes === ['region' => 'us']);
});

it('records nothing while recording is stopped', function (): void {
    Telemetry::stopRecording();

    resolve(Heartbeat::class)->record('scheduler-heartbeat');

    expect(exportedLogs())->toBeEmpty();
});

it('records a scheduler heartbeat from telemetry:heartbeat', function (): void {
    $this->artisan('telemetry:heartbeat')->assertSuccessful();

    expect(exportedLogs()[0]->getBody())->toBe('scheduler-heartbeat');
});

it('records a queue heartbeat from the queued job', function (): void {
    expect(new RecordQueueHeartbeat)->toBeInstanceOf(ShouldQueue::class);

    resolve(Dispatcher::class)->dispatchSync(new RecordQueueHeartbeat);

    expect(exportedLogs()[0]->getBody())->toBe('queue-heartbeat');
});

it('reports the Horizon status', function (array $statuses, string $status): void {
    $this->app->instance(MasterSupervisorRepository::class, new FakeMasterSupervisorRepository($statuses));

    $this->artisan('telemetry:horizon-heartbeat')->assertSuccessful();

    $log = exportedLogs()[0];

    expect($log->getBody())->toBe('horizon-heartbeat')
        ->and($log->getAttributes()->toArray())->toBe(['status' => $status, 'masters' => count($statuses)]);
})->with([
    'running while any master is unpaused' => [['running', 'paused'], 'running'],
    'paused when every master is paused' => [['paused'], 'paused'],
    'down when no master is running' => [[], 'down'],
]);

it('reports Horizon as down when its state cannot be read', function (): void {
    $this->app->instance(MasterSupervisorRepository::class, new FakeMasterSupervisorRepository(null));

    $this->artisan('telemetry:horizon-heartbeat')->assertSuccessful();

    expect(exportedLogs()[0]->getAttributes()->toArray())
        ->toBe(['status' => 'down', 'masters' => 0, 'error' => 'Connection refused']);
});
