<?php

namespace Actengage\Telemetry\Watchers\Ai;

use Actengage\Telemetry\Support\ActiveSpans;
use Actengage\Telemetry\Telemetry;
use Illuminate\Contracts\Events\Dispatcher;
use Laravel\Ai\Events\InvokingTool;
use Laravel\Ai\Events\ToolFailed;
use Laravel\Ai\Events\ToolInvoked;
use Laravel\Ai\Tools\ToolNameResolver;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\TracerInterface;

class ToolEventSubscriber
{
    public function __construct(
        protected TracerInterface $tracer,
        protected ActiveSpans $spans,
    ) {}

    public function start(InvokingTool $event): void
    {
        if (! Telemetry::isRecording()) {
            return;
        }

        $tool = ToolNameResolver::resolve($event->tool);

        $span = $this->tracer->spanBuilder('execute_tool '.$tool)
            ->setSpanKind(SpanKind::KIND_INTERNAL)
            ->setAttribute('gen_ai.operation.name', 'execute_tool')
            ->setAttribute('gen_ai.tool.name', $tool)
            ->startSpan();

        $this->spans->open($event->toolInvocationId, $span);
    }

    public function end(ToolInvoked $event): void
    {
        $this->spans->close($event->toolInvocationId)?->end();
    }

    public function fail(ToolFailed $event): void
    {
        $this->spans->fail($event->toolInvocationId, $event->exception);
    }

    /**
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            InvokingTool::class => 'start',
            ToolInvoked::class => 'end',
            ToolFailed::class => 'fail',
        ];
    }
}
