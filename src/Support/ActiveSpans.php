<?php

namespace Actengage\Telemetry\Support;

use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\StatusCode;
use Throwable;

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

    public function get(string $key): ?SpanInterface
    {
        return ($this->spans[$key] ?? null)?->span;
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

    public function fail(string $key, Throwable $exception): void
    {
        $this->close($key)
            ?->recordException($exception)
            ->setStatus(StatusCode::STATUS_ERROR, $exception->getMessage())
            ->end();
    }

    public function abandonAll(): void
    {
        while ($this->spans !== []) {
            array_pop($this->spans)->abandon();
        }
    }

    protected function abandonOpenedAfter(string $key): void
    {
        while (array_key_last($this->spans) !== $key) {
            array_pop($this->spans)?->abandon();
        }
    }
}
