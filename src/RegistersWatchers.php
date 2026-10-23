<?php

namespace ActiveEngagement\Telemetry;

use ActiveEngagement\Telemetry\Watchers\Watcher;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use InvalidArgumentException;

trait RegistersWatchers
{
    public static function registerWatchers(Application $app): void
    {
        $watchers = $app->make(Repository::class)->get('telemetry.watchers');

        foreach (is_array($watchers) ? $watchers : [] as $key => $options) {
            rescue(fn () => static::registerWatcher($app, $key, $options));
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    protected static function registerWatcher(Application $app, int|string $key, mixed $options): void
    {
        if ($options === false || (is_array($options) && ! ($options['enabled'] ?? true))) {
            return;
        }

        $class = is_string($key) ? $key : $options;

        if (! is_string($class) || ! is_a($class, Watcher::class, true)) {
            throw new InvalidArgumentException('Each telemetry watcher must extend '.Watcher::class.'.');
        }

        $app->make($class, ['options' => is_array($options) ? $options : []])->register($app);
    }
}
