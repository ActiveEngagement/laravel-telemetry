<?php

use ActiveEngagement\Telemetry\Contracts\ContextResolver;
use ActiveEngagement\Telemetry\Logging\SendToOpenTelemetry;
use ActiveEngagement\Telemetry\Telemetry;
use ActiveEngagement\Telemetry\Watchers\LogWatcher;
use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Log\Context\Repository as ContextRepository;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Log;
use Monolog\Handler\NullHandler;
use Monolog\Handler\TestHandler;
use OpenTelemetry\API\Logs\LoggerInterface;
use OpenTelemetry\API\Logs\Severity;
use OpenTelemetry\API\Trace\TracerInterface;
use Tests\Fixtures\FakeLogger;

function watchLogsWith(LoggerInterface $logger): void
{
    app()->instance(LoggerInterface::class, $logger);

    rewatch();
}

it('records logs in OpenTelemetry', function (): void {
    Log::warning('disk almost full', ['disk' => 'sda']);

    $logs = exportedLogs();

    expect($logs)->toHaveCount(1)
        ->and($logs[0]->getBody())->toBe('disk almost full')
        ->and($logs[0]->getSeverityText())->toBe('warning')
        ->and($logs[0]->getSeverityNumber())->toBe(Severity::WARN->value)
        ->and($logs[0]->getAttributes()->toArray())->toBe(['disk' => 'sda']);
});

it('links logs to the active trace', function (): void {
    $span = app(TracerInterface::class)->spanBuilder('request')->startSpan();
    $scope = $span->activate();

    Log::info('inside the span');

    $scope->detach();
    $span->end();

    $logs = exportedLogs();

    expect($logs)->toHaveCount(1)
        ->and($logs[0]->getSpanContext()->getTraceId())->toBe($span->getContext()->getTraceId())
        ->and($logs[0]->getSpanContext()->getSpanId())->toBe($span->getContext()->getSpanId());
});

it('records exceptions with the OpenTelemetry exception attributes', function (): void {
    Log::error('Connection timed out', ['exception' => new RuntimeException('Connection timed out')]);

    $attributes = exportedLogs()[0]->getAttributes();

    expect($attributes->has('exception'))->toBeFalse()
        ->and($attributes->get('exception.type'))->toBe(RuntimeException::class)
        ->and($attributes->get('exception.message'))->toBe('Connection timed out')
        ->and($attributes->get('exception.stacktrace'))->toContain('RuntimeException: Connection timed out');
});

it('keeps an exception context value that is not a Throwable', function (): void {
    Log::error('failed', ['exception' => 'timeout']);

    $attributes = exportedLogs()[0]->getAttributes();

    expect($attributes->get('exception'))->toBe('timeout')
        ->and($attributes->has('exception.type'))->toBeFalse();
});

it('removes the trace id field that keepsuit adds to the log context', function (): void {
    Log::info('hello', ['trace_id' => 'abc123', 'disk' => 'sda']);

    expect(exportedLogs()[0]->getAttributes()->toArray())->toBe(['disk' => 'sda']);
});

it('adds the user attributes when a user is logged in', function (): void {
    $this->actingAs(new GenericUser(['id' => 42]));

    Log::info('hello');

    expect(exportedLogs()[0]->getAttributes()->get('user.id'))->toBe(42);
});

it('leaves out the user attributes when user context is off', function (): void {
    config()->set('opentelemetry.user_context', false);
    $this->actingAs(new GenericUser(['id' => 42]));

    Log::info('hello');

    expect(exportedLogs()[0]->getAttributes()->has('user.id'))->toBeFalse();
});

it('uses the bound context resolver', function (): void {
    app()->instance(ContextResolver::class, new class implements ContextResolver
    {
        public function resolve(array $context): array
        {
            return ['tenant' => 'acme'];
        }

        public function userAttributes(?Authenticatable $user = null): array
        {
            return [];
        }

        public function dehydrating(ContextRepository $context): void {}
    });

    rewatch();
    Log::info('hello', ['disk' => 'sda']);

    expect(exportedLogs()[0]->getAttributes()->toArray())->toBe(['tenant' => 'acme']);
});

it('does not pass a Throwable exception to the context resolver', function (): void {
    app()->instance(ContextResolver::class, $resolver = new class implements ContextResolver
    {
        public array $received = [];

        public function resolve(array $context): array
        {
            return $this->received = $context;
        }

        public function userAttributes(?Authenticatable $user = null): array
        {
            return [];
        }

        public function dehydrating(ContextRepository $context): void {}
    });

    rewatch();
    Log::error('failed', ['exception' => new RuntimeException('boom'), 'disk' => 'sda']);

    expect($resolver->received)->toBe(['disk' => 'sda']);
});

it('does not record logs below TELEMETRY_LOG_LEVEL', function (): void {
    refreshWithEnv('TELEMETRY_LOG_LEVEL', 'warning');

    Log::info('not recorded');
    Log::warning('recorded');

    $logs = exportedLogs();

    expect($logs)->toHaveCount(1)
        ->and($logs[0]->getBody())->toBe('recorded');
});

it('falls back to LOG_LEVEL', function (): void {
    refreshWithEnv('LOG_LEVEL', 'error');

    Log::warning('not recorded');
    Log::error('recorded');

    $logs = exportedLogs();

    expect($logs)->toHaveCount(1)
        ->and($logs[0]->getBody())->toBe('recorded');
});

it('reports a level that is not a PSR-3 level and sends no logs', function (): void {
    Exceptions::fake();
    config()->set('telemetry.watchers', [LogWatcher::class => ['level' => 'loud']]);

    rewatch();
    Log::error('not recorded');

    Exceptions::assertReported(InvalidArgumentException::class);
    expect(exportedLogs())->toBeEmpty();
});

it('sends logs to the bound OpenTelemetry logger', function (): void {
    watchLogsWith($logger = new FakeLogger);

    Log::info('hello');

    expect($logger->records)->toHaveCount(1);
});

it('does not record logs written while recording', function (): void {
    watchLogsWith($logger = new FakeLogger(fn () => Log::warning('logged while recording')));

    Log::info('hello');

    expect($logger->records)->toHaveCount(1);
});

it('never breaks the app when recording fails', function (): void {
    watchLogsWith(new FakeLogger(fn () => throw new RuntimeException('Exporter is down')));

    expect(fn () => Log::error('hello'))->not->toThrow(Exception::class);
});

it('sends logs below LOG_LEVEL when TELEMETRY_LOG_LEVEL is lower', function (): void {
    config()->set('logging.channels.files', ['driver' => 'monolog', 'handler' => TestHandler::class, 'with' => ['level' => 'warning']]);
    config()->set('logging.default', 'files');
    config()->set('telemetry.watchers', [LogWatcher::class => ['level' => 'info']]);

    rewatch();
    Log::info('only in OpenTelemetry');

    $files = collect(Log::channel('files')->getLogger()->getHandlers())->first(fn ($handler): bool => $handler instanceof TestHandler);

    expect(exportedLogs())->toHaveCount(1)
        ->and($files->getRecords())->toBeEmpty();
});

it('adds the Laravel Context data to the log attributes', function (): void {
    Context::add('tenant', 'acme');

    Log::info('hello');

    expect(exportedLogs()[0]->getAttributes()->get('tenant'))->toBe('acme');
});

it('does not send logs written to another channel', function (): void {
    Log::build(['driver' => 'monolog', 'handler' => NullHandler::class])->info('not recorded');

    expect(exportedLogs())->toBeEmpty();
});

it('keeps the other taps on the default channel', function (): void {
    config()->set('logging.channels.test.tap', ['App\Logging\CustomizeFormatter']);

    Telemetry::registerWatchers(app());
    Telemetry::registerWatchers(app());

    expect(config('logging.channels.test.tap'))->toBe([
        'App\Logging\CustomizeFormatter',
        SendToOpenTelemetry::class.':debug',
    ]);
});
