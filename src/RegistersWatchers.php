<?php

namespace Actengage\Telemetry;

use Actengage\Telemetry\Watchers\Watcher;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use InvalidArgumentException;

trait RegistersWatchers
{
    public static function registerWatchers(Application $app): void
    {
        $watchers = $app->make(Repository::class)->get('telemetry.watchers');

        foreach (is_array($watchers) ? $watchers : [] as $key => $options) {
            if ($options === false || (is_array($options) && ! ($options['enabled'] ?? true))) {
                continue;
            }

            $class = is_string($key) ? $key : $options;

            if (! is_string($class) || ! is_a($class, Watcher::class, true)) {
                throw new InvalidArgumentException('Each telemetry watcher must extend '.Watcher::class.'.');
            }

            $app->make($class, ['options' => is_array($options) ? $options : []])->register($app);
        }
    }
}
