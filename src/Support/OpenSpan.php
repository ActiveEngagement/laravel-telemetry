<?php

declare(strict_types=1);

namespace ActiveEngagement\Telemetry\Support;

use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\Context\ScopeInterface;

readonly class OpenSpan
{
    public function __construct(
        public SpanInterface $span,
        protected ScopeInterface $scope,
    ) {}

    public function close(): SpanInterface
    {
        $this->scope->detach();

        return $this->span;
    }

    public function abandon(): void
    {
        $this->scope->detach();
        $this->span->setStatus(StatusCode::STATUS_ERROR, 'The operation never completed.');
        $this->span->end();
    }
}
