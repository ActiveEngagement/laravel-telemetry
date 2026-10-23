<?php

declare(strict_types=1);

namespace ActiveEngagement\Telemetry\Console;

use ActiveEngagement\Telemetry\Support\Heartbeat;
use Illuminate\Console\Command;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use Throwable;

class HorizonHeartbeatCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'telemetry:horizon-heartbeat';

    /**
     * @var string
     */
    protected $description = 'Record a Horizon heartbeat with the status of its masters';

    public function handle(Heartbeat $heartbeat, MasterSupervisorRepository $masters): int
    {
        try {
            $statuses = $this->statuses($masters);
        } catch (Throwable $throwable) {
            $heartbeat->record('horizon-heartbeat', [
                'status' => 'down',
                'masters' => 0,
                'error' => $throwable->getMessage(),
            ]);

            return self::SUCCESS;
        }

        $heartbeat->record('horizon-heartbeat', [
            'status' => $this->status($statuses),
            'masters' => count($statuses),
        ]);

        return self::SUCCESS;
    }

    /**
     * @return list<mixed>
     */
    protected function statuses(MasterSupervisorRepository $masters): array
    {
        return array_values(array_map(
            fn (mixed $master): mixed => is_object($master) ? ($master->status ?? null) : null,
            $masters->all(),
        ));
    }

    /**
     * @param  list<mixed>  $statuses
     */
    protected function status(array $statuses): string
    {
        return match (true) {
            $statuses === [] => 'down',
            array_all($statuses, fn (mixed $status): bool => $status === 'paused') => 'paused',
            default => 'running',
        };
    }
}
