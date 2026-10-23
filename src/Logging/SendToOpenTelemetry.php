<?php

namespace ActiveEngagement\Telemetry\Logging;

use Illuminate\Contracts\Container\Container;
use Illuminate\Log\Logger;
use Monolog\Logger as Monolog;

class SendToOpenTelemetry
{
    public function __construct(protected Container $container) {}

    public function __invoke(Logger $logger, string $level): void
    {
        rescue(fn () => $this->pushHandler($logger, $level), report: false);
    }

    protected function pushHandler(Logger $logger, string $level): void
    {
        $monolog = $logger->getLogger();

        if ($monolog instanceof Monolog) {
            $monolog->pushHandler($this->container->make(OpenTelemetryHandler::class, ['level' => $level]));
        }
    }
}
