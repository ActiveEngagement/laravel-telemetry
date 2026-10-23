<?php

namespace Actengage\Telemetry\Support;

use OpenTelemetry\API\Trace\SpanInterface;

class ActiveSpans
{
    /**
     * @var array<string, OpenSpan>
     */
    protected array $spans = [];

    public function has(string $key): bool
    {
        return isset($this->spans[$key]);
    }

    public function open(string $key, SpanInterface $span): void
    {
        $this->spans[$key] = new OpenSpan($span, $span->activate());
    }

    public function close(string $key): ?SpanInterface
    {
        if (! $this->has($key)) {
            return null;
        }

        $this->abandonOpenedAfter($key);

        return array_pop($this->spans)?->close();
    }

    protected function abandonOpenedAfter(string $key): void
    {
        while (array_key_last($this->spans) !== $key) {
            array_pop($this->spans)?->abandon();
        }
    }
}
