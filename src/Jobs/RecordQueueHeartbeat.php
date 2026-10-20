<?php

namespace Actengage\Telemetry\Jobs;

use Actengage\Telemetry\Support\Heartbeat;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

class RecordQueueHeartbeat implements ShouldQueue
{
    use Queueable;

    public function handle(Heartbeat $heartbeat): void
    {
        $heartbeat->record('queue-heartbeat');
    }
}
