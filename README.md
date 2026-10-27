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

Delete the app's published `config/opentelemetry.php` unless it must override a keepsuit default. Without it, keepsuit's defaults apply: metrics also go over OTLP, and no request paths are excluded from tracing. If the app keeps the file, keepsuit merges it with its own config by top-level key, so each key that the file sets must be complete. For example, an `instrumentation` array must list every instrumentation, not only the changed one.

To install the agent skill, run `php artisan boost:install` and select `activeengagement/laravel-telemetry`.

## Environment

```ini
OTEL_SERVICE_NAME=savant
OTEL_EXPORTER_OTLP_ENDPOINT=https://heimdall.actengage.com
OTEL_EXPORTER_OTLP_HEADERS=authorization=<ingestion-api-key>
OTEL_EXPORTER_OTLP_TIMEOUT=3000       # milliseconds, for traces, logs and metrics

TELEMETRY_ENABLED=true
TELEMETRY_LOG_LEVEL=info              # defaults to LOG_LEVEL
TELEMETRY_SCHEDULER_HEARTBEAT=true
TELEMETRY_QUEUE_MONITOR=horizon       # horizon, queue or none (default)
TELEMETRY_AI_WATCHER=true
```

Other `OTEL_*` variables work as keepsuit documents them. Set the general `OTEL_EXPORTER_OTLP_TIMEOUT`, not only `OTEL_EXPORTER_OTLP_TRACES_TIMEOUT`: logs and metrics go over OTLP too, and their exporters use the default of 10 seconds with 3 retries. A PHP-FPM worker exports after it sends the response, so a slow endpoint keeps it busy for that time.

An app that runs Horizon sets `TELEMETRY_QUEUE_MONITOR=horizon`. With the default `none`, it sends no queue heartbeat.

`service.version` comes from the first one that is set: `LARAVEL_CLOUD_DEPLOY_UUID`, `FORGE_DEPLOY_COMMIT`, `ATHENA_DEPLOY_COMMIT`.

- **Laravel Cloud:** no extra step.
- **Forge:** the deploy script must run `artisan config:cache`, because `FORGE_DEPLOY_COMMIT` exists only during the deploy.
- **athena:** the Deployer recipe runs `artisan optimize` on the host, so `deploy.php` passes the commit that the GitHub Actions runner builds:

  ```php
  set('env', array_filter(['ATHENA_DEPLOY_COMMIT' => getenv('GITHUB_SHA') ?: null]));
  ```

  A later `artisan optimize` or `artisan config:cache` run by hand on the host drops `service.version` until the next deploy. Remove `OTEL_LOGS_EXPORTER=null` and `OTEL_METRICS_EXPORTER=null`. Stop the collector's log-file tail for the app, or each log arrives two times.

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
<server name="OTEL_SDK_DISABLED" value="true"/>
<env name="TELEMETRY_ENABLED" value="false"/>
```

`OTEL_SDK_DISABLED` is a `<server>` entry, because PHPUnit writes an `<env>` value of `true` as `1` and the SDK reads `1` as `false`.

## Development

```bash
composer qa          # fix code style, then run every check
composer lint        # Rector and Pint
composer test        # tests, code style and types
composer test:unit   # Pest
composer test:lint   # Pint, Rector and the changelog format, without changes
composer test:types  # PHPStan
```
