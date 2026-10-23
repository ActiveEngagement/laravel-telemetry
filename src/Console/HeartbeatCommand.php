<?php

namespace ActiveEngagement\Telemetry\Console;

use ActiveEngagement\Telemetry\Support\Heartbeat;
use Illuminate\Console\Command;

class HeartbeatCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'telemetry:heartbeat';

    /**
     * @var string
     */
    protected $description = 'Record a scheduler heartbeat';

    public function handle(Heartbeat $heartbeat): int
    {
        $heartbeat->record('scheduler-heartbeat');

        return self::SUCCESS;
    }
}
