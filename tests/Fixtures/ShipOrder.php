<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ShipOrder implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $order) {}

    public function handle(): void
    {
        Log::info("shipping order {$this->order}");
    }
}
