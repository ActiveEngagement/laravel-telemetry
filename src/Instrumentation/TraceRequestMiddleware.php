<?php

declare(strict_types=1);

namespace ActiveEngagement\Telemetry\Instrumentation;

use Closure;
use Illuminate\Http\Request;
use Keepsuit\LaravelOpenTelemetry\Instrumentation\Support\Http\Server\TraceRequestMiddleware as KeepsuitTraceRequestMiddleware;
use OpenTelemetry\API\Trace\SpanBuilderInterface;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\Context\Context;
use Override;
use ReflectionFunction;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class TraceRequestMiddleware extends KeepsuitTraceRequestMiddleware
{
    public function __construct(protected TracerInterface $tracer) {}

    /**
     * @param  array<non-empty-string, bool|int|float|string|array<mixed>|null>  $attributes
     */
    #[Override]
    protected function recordTraceAttributes(SpanInterface $span, Request $request, Response $response, array $attributes): void
    {
        parent::recordTraceAttributes($span, $request, $response, $attributes);

        if ($response instanceof StreamedResponse) {
            rescue(fn () => $this->traceStream($span, $request, $response), report: false);
        }
    }

    protected function traceStream(SpanInterface $requestSpan, Request $request, StreamedResponse $response): void
    {
        $body = $response->getCallback();

        if (! $body instanceof Closure || $this->isSentByOctane($body)) {
            return;
        }

        $route = $this->resolveRouteName($request);

        $streamSpan = $this->tracer->spanBuilder(mb_trim(sprintf('%s %s', $request->method(), $route ?? '')).' stream')
            ->setParent($requestSpan->storeInContext(Context::getCurrent()))
            ->setSpanKind(SpanKind::KIND_INTERNAL)
            ->setAttribute('http.request.method', $request->method())
            ->setAttribute('http.route', $route);

        $response->setCallback(fn () => $this->stream($streamSpan, $body));
    }

    /**
     * @throws Throwable
     */
    protected function stream(SpanBuilderInterface $streamSpan, Closure $body): void
    {
        $span = $streamSpan->startSpan();
        $scope = $span->activate();

        try {
            $body();
        } catch (Throwable $throwable) {
            $span->recordException($throwable)->setStatus(StatusCode::STATUS_ERROR, $throwable->getMessage());

            throw $throwable;
        } finally {
            $scope->detach();
            $span->end();
        }
    }

    protected function isSentByOctane(Closure $body): bool
    {
        return new ReflectionFunction($body)->isGenerator();
    }
}
