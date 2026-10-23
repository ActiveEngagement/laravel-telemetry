<?php

declare(strict_types=1);

namespace ActiveEngagement\Telemetry\Watchers;

use ActiveEngagement\Telemetry\Logging\SendToOpenTelemetry;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Log\LogManager;
use InvalidArgumentException;
use Monolog\Level;

class LogWatcher extends Watcher
{
    protected Level $level;

    /**
     * @param  array<mixed>  $options
     */
    public function __construct(array $options = [])
    {
        parent::__construct($options);

        $this->level = $this->level($options['level'] ?? 'debug');
    }

    public function register(Application $app): void
    {
        $config = $app->make(Repository::class);
        $channel = $config->get('logging.default');

        if (! is_string($channel)) {
            return;
        }

        $config->set("logging.channels.{$channel}.tap", [
            ...$this->otherTaps($config->get("logging.channels.{$channel}.tap")),
            SendToOpenTelemetry::class.':'.$this->level->toPsrLogLevel(),
        ]);

        $app->make(LogManager::class)->forgetChannel($channel);
    }

    /**
     * @return list<mixed>
     */
    protected function otherTaps(mixed $taps): array
    {
        return array_values(array_filter(
            is_array($taps) ? $taps : [],
            fn (mixed $tap): bool => ! is_string($tap) || ! str_starts_with($tap, SendToOpenTelemetry::class),
        ));
    }

    protected function level(mixed $level): Level
    {
        foreach (Level::cases() as $case) {
            if (is_string($level) && strtolower($level) === $case->toPsrLogLevel()) {
                return $case;
            }
        }

        throw new InvalidArgumentException('The log watcher level must be a PSR-3 log level.');
    }
}
