<?php

declare(strict_types=1);

namespace ActiveEngagement\Telemetry\Watchers;

use ActiveEngagement\Telemetry\Console\HeartbeatCommand;
use ActiveEngagement\Telemetry\Console\HorizonHeartbeatCommand;
use ActiveEngagement\Telemetry\Jobs\RecordQueueHeartbeat;
use ActiveEngagement\Telemetry\Telemetry;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Foundation\Application;
use InvalidArgumentException;

class HeartbeatWatcher extends Watcher
{
    /**
     * @var list<string>
     */
    public const array SUPPORTED_QUEUE_MONITORS = ['horizon', 'queue', 'none'];

    protected string $queueMonitor;

    /**
     * @param  array<mixed>  $options
     */
    public function __construct(array $options = [])
    {
        parent::__construct($options);

        $this->queueMonitor = $this->resolveQueueMonitor($options['queue'] ?? 'none');
    }

    public function register(Application $app): void
    {
        $app->afterResolving(Schedule::class, $this->schedule(...));

        if ($app->resolved(Schedule::class)) {
            $this->schedule($app->make(Schedule::class));
        }
    }

    public function schedule(Schedule $schedule): void
    {
        if ($this->options['scheduler'] ?? true) {
            $schedule->command(HeartbeatCommand::class)->everyMinute();
        }

        match ($this->queueMonitor) {
            'horizon' => $schedule->command(HorizonHeartbeatCommand::class)->everyMinute(),
            'queue' => $schedule->job(new RecordQueueHeartbeat)->everyMinute(),
            default => null,
        };
    }

    protected function resolveQueueMonitor(mixed $monitor): string
    {
        if (! in_array($monitor, static::SUPPORTED_QUEUE_MONITORS, true)) {
            throw new InvalidArgumentException(sprintf(
                'The heartbeat queue option must be one of [%s].',
                implode(', ', static::SUPPORTED_QUEUE_MONITORS)
            ));
        }

        if ($monitor === 'horizon' && ! Telemetry::isHorizonInstalled()) {
            throw new InvalidArgumentException('The horizon queue heartbeat needs laravel/horizon.');
        }

        return $monitor;
    }
}
