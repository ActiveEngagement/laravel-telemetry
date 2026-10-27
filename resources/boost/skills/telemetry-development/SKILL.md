---
name: telemetry-development
description: Use in apps that require activeengagement/laravel-telemetry. Trigger when working with OpenTelemetry, tracing, spans, log channels, heartbeats, AI token or spend attribution, Heimdall queries, config/opentelemetry.php, or telemetry settings in phpunit.xml. Covers what the package already does, its extension points, and the attribute names it sends.
license: MIT
metadata:
  author: activeengagement
---

# Telemetry

`activeengagement/laravel-telemetry` sets up OpenTelemetry for this app. It extends `keepsuit/laravel-opentelemetry`. Telemetry is automatic, so most tasks need no telemetry code.

## Do not add

The package already does these things:

- Do not add an `otlp` log channel or change `LOG_STACK`. The package taps the default log channel.
- Do not add heartbeat commands or schedule entries. Use `TELEMETRY_SCHEDULER_HEARTBEAT` and `TELEMETRY_QUEUE_MONITOR` (`horizon`, `queue` or `none`).
- Do not add laravel/ai listeners that record spans, tokens or the user. The package records every laravel/ai operation.
- Do not start spans for commands or jobs. Each command and job gets a span.
- Do not start spans or pass the trace into a streamed response body. The body gets a stream span in the request's trace.
- Do not add the user to spans or logs. The package adds it, also in queued jobs.
- Do not use `putenv()`, `OTEL_TRACES_EXPORTER=null` or `Event::fake()` to stop telemetry in tests. See [Testing](#testing).
- Do not publish `config/opentelemetry.php` unless a keepsuit default must change. keepsuit merges it by top-level key, so each key that the file sets must be complete, for example the whole `instrumentation` array.

## Extension points

To add attributes to an agent or tool span, implement `HasTelemetryAttributes`. `gen_ai.agent.name` also changes the span name.

```php
use ActiveEngagement\Telemetry\Contracts\HasTelemetryAttributes;

class BillingAgent implements Agent, HasTelemetryAttributes
{
    public function telemetryAttributes(): array
    {
        return ['gen_ai.agent.name' => 'billing', 'app.team' => 'finance'];
    }
}
```

To add attributes to any AI span, listen for `AiSpanStarted`. `$event->source` is the laravel/ai event that started the span.

```php
use ActiveEngagement\Telemetry\Events\AiSpanStarted;

Event::listen(function (AiSpanStarted $event) {
    $event->span->setAttribute('app.team', 'finance');
});
```

Other hooks:

- **User attributes:** `OpenTelemetry::user(fn (User $user) => ['user.id' => $user->id, 'user.email' => $user->email])` in a service provider (keepsuit facade).
- **Log attributes:** `Context::add('order_id', $order->id)`.
- **Another log channel:** add `ActiveEngagement\Telemetry\Logging\SendToOpenTelemetry::class.':info'` to its `tap`. Only for channels that are not in `LOG_STACK`, or each log is sent two times.
- **Exclude a command:** add a pattern to `'excluded'` in the `ConsoleInstrumentation` options of `config/opentelemetry.php`.
- **Pause:** `Telemetry::withoutRecording(fn () => ...)` stops the package's AI spans, logs and heartbeats.

## Attribute names

Use these names in Heimdall queries:

| Data | Name |
| --- | --- |
| Environment | `deployment.environment.name` (resource) |
| Deploy | `service.version` (resource) |
| User | `user.id` |
| Stream spans | `{method} {route} stream` (attributes `http.request.method`, `http.route`) |
| AI spans | `invoke_agent {agent}`, `execute_tool {tool}`, `{operation} {model}` |
| AI attributes | `gen_ai.provider.name`, `gen_ai.request.model`, `gen_ai.agent.name`, `gen_ai.conversation.id`, `gen_ai.tool.name` |
| Tokens | `gen_ai.usage.input_tokens` (includes cached tokens), `gen_ai.usage.output_tokens`, `gen_ai.usage.cache_read.input_tokens`, `gen_ai.usage.cache_creation.input_tokens` |
| Heartbeats | log body `scheduler-heartbeat`, `horizon-heartbeat` (attribute `status`), `queue-heartbeat` |

## Testing

`phpunit.xml` turns off telemetry:

```xml
<server name="OTEL_SDK_DISABLED" value="true"/>
<env name="TELEMETRY_ENABLED" value="false"/>
```

`OTEL_SDK_DISABLED` is a `<server>` entry, because PHPUnit writes an `<env>` value of `true` as `1` and the SDK reads `1` as `false`.

Do not test what the package does, such as spans, log export and heartbeats. The package has its own tests.

Test the app's own telemetry code: each `HasTelemetryAttributes` implementation, each `AiSpanStarted` listener and the `OpenTelemetry::user()` resolver.

- A listener: give it a span from an SDK `TracerProvider` with a `SimpleSpanProcessor` and an `InMemoryExporter`, then assert the exported attributes. `OTEL_SDK_DISABLED` does not affect a provider that the test builds itself.
- The user resolver: assert `resolve(ContextResolver::class)->userAttributes($user)`.

## More

Read `vendor/activeengagement/laravel-telemetry/README.md`.
