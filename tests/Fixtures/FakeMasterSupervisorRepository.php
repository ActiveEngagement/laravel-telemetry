<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use Laravel\Horizon\MasterSupervisor;
use RuntimeException;

class FakeMasterSupervisorRepository implements MasterSupervisorRepository
{
    /**
     * @param  list<string>|null  $statuses
     */
    public function __construct(protected ?array $statuses = []) {}

    public function names()
    {
        return [];
    }

    public function all()
    {
        if ($this->statuses === null) {
            throw new RuntimeException('Connection refused');
        }

        return array_map(fn (string $status): object => (object) ['status' => $status], $this->statuses);
    }

    public function find($name)
    {
        return null;
    }

    public function get(array $names)
    {
        return [];
    }

    public function update(MasterSupervisor $master) {}

    public function forget($name) {}

    public function flushExpired() {}
}
