<?php

namespace ActiveEngagement\Telemetry\Watchers;

use Illuminate\Contracts\Foundation\Application;

abstract class Watcher
{
    /**
     * @param  array<mixed>  $options
     */
    public function __construct(public array $options = []) {}

    abstract public function register(Application $app): void;
}
