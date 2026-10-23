# Laravel Telemetry

Shared OpenTelemetry setup for ActiveEngagement Laravel apps. Extends [keepsuit/laravel-opentelemetry](https://github.com/keepsuit/laravel-opentelemetry) with command spans, log export, heartbeats, AI spans and resource attributes.

Requires PHP 8.4+ and Laravel 12 or 13.30+. AI tracing requires `laravel/ai` 1.3+.

## Installation

```json
"repositories": [
    { "type": "vcs", "url": "https://github.com/ActiveEngagement/laravel-telemetry" }
]
```

```bash
composer require activeengagement/laravel-telemetry
```

Delete the app's published `config/opentelemetry.php` unless it must override a keepsuit default.

To install the agent skill, run `php artisan boost:install` and select `activeengagement/laravel-telemetry`.

## Environment

```ini
OTEL_SERVICE_NAME=savant
OTEL_EXPORTER_OTLP_ENDPOINT=https://heimdall.actengage.com
OTEL_EXPORTER_OTLP_HEADERS=authorization=<ingestion-api-key>

TELEMETRY_ENABLED=true
TELEMETRY_LOG_LEVEL=info              # defaults to LOG_LEVEL
TELEMETRY_SCHEDULER_HEARTBEAT=true
TELEMETRY_QUEUE_MONITOR=horizon       # horizon, queue or none (default)
TELEMETRY_AI_WATCHER=true
```

Other `OTEL_*` variables work as keepsuit documents them.

`service.version` comes from the first one that is set: `LARAVEL_CLOUD_DEPLOY_UUID`, `FORGE_DEPLOY_COMMIT`, `ATHENA_DEPLOY_COMMIT`.

- **Laravel Cloud:** add the Composer auth step for private packages to the build commands.
- **Forge:** the deploy script must run `artisan config:cache`, because `FORGE_DEPLOY_COMMIT` exists only during the deploy.
- **athena:** set `ATHENA_DEPLOY_COMMIT` in `deploy.php` before `artisan config:cache`. Remove `OTEL_LOGS_EXPORTER=null`. Stop the collector's log-file tail for the app, or each log arrives two times.

## Logs

The package taps the default log channel. Context data becomes log attributes. To send another channel that is not in `LOG_STACK`, add the tap:

```php
'tap' => [ActiveEngagement\Telemetry\Logging\SendToOpenTelemetry::class.':info'],
```

## Commands

Every command gets a span, except workers, the scheduler and `telemetry:*` (see `ConsoleInstrumentation::EXCLUDED`). To exclude more, add patterns in `config/opentelemetry.php`:

```php
Instrumentation\ConsoleInstrumentation::class => [
    'enabled' => filter_var(env('OTEL_INSTRUMENTATION_CONSOLE', true), FILTER_VALIDATE_BOOLEAN),
    'excluded' => ['app:listen-*'],
],
```

## AI attributes

Agents and tools can add span attributes:

```php
class BillingAgent implements Agent, HasTelemetryAttributes
{
    public function telemetryAttributes(): array
    {
        return ['gen_ai.agent.name' => 'billing'];
    }
}
```

Listeners of `AiSpanStarted` can add attributes to any AI span:

```php
Event::listen(function (AiSpanStarted $event) {
    $event->span->setAttribute('app.team', 'finance');
});
```

## Testing

Add to the app's `phpunit.xml`:

```xml
<env name="OTEL_SDK_DISABLED" value="true"/>
<env name="TELEMETRY_ENABLED" value="false"/>
```

## Development

```bash
composer test
composer test:types
composer test:refactor
```
