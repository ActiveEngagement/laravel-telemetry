<?php

declare(strict_types=1);

namespace ActiveEngagement\Telemetry\Listeners;

use ActiveEngagement\Telemetry\Telemetry;
use Laravel\Ai\Events\InvokingTool;
use Laravel\Ai\Events\ToolFailed;
use Laravel\Ai\Events\ToolInvoked;
use Laravel\Ai\Tools\ToolNameResolver;
use OpenTelemetry\API\Trace\SpanKind;

class ToolEventSubscriber extends AiEventSubscriber
{
    /**
     * @var array<class-string, string>
     */
    protected array $listen = [
        InvokingTool::class => 'start',
        ToolInvoked::class => 'end',
        ToolFailed::class => 'fail',
    ];

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
}
