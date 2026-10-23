<?php

namespace Actengage\Telemetry\Listeners;

use Actengage\Telemetry\Telemetry;
use Illuminate\Contracts\Events\Dispatcher;
use Laravel\Ai\Events\InvokingTool;
use Laravel\Ai\Events\ToolFailed;
use Laravel\Ai\Events\ToolInvoked;
use Laravel\Ai\Tools\ToolNameResolver;
use OpenTelemetry\API\Trace\SpanKind;

class ToolEventSubscriber extends AiEventSubscriber
{
    public function start(InvokingTool $event): void
    {
        if (! Telemetry::isRecording()) {
            return;
        }

        $tool = ToolNameResolver::resolve($event->tool);

        $span = $this->tracer->spanBuilder('execute_tool '.$tool)
            ->setSpanKind(SpanKind::KIND_INTERNAL)
            ->setAttributes($this->withoutNulls([
                'gen_ai.operation.name' => 'execute_tool',
                'gen_ai.tool.name' => $tool,
                ...$this->classAttributes($event->tool),
            ]))
            ->startSpan();

        $this->openSpan($event->toolInvocationId, $span, $event);
    }

    public function end(ToolInvoked $event): void
    {
        $this->spans->close($event->toolInvocationId)?->end();
    }

    public function fail(ToolFailed $event): void
    {
        $this->spans->fail($event->toolInvocationId, $event->exception);
    }

    public function subscribe(Dispatcher $events): array
    {
        return [
            InvokingTool::class => 'start',
            ToolInvoked::class => 'end',
            ToolFailed::class => 'fail',
        ];
    }
}
