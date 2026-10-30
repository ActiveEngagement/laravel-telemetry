# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.2.3] - 2026-10-30

### Fixed

- Queue workers, also under Horizon, export a job's spans and logs when they start the next loop, and again when they stop. A busy worker exports at most once every 5 seconds. Before, an idle worker kept them until it ran another job or exited, so traces, logs and failed-job alerts from a quiet queue could arrive minutes late. A worker that a job timeout killed lost them.

## [0.2.2] - 2026-10-29

### Changed

- Requests to `/up`, Laravel's health route, are no longer traced.

## [0.2.1] - 2026-10-28

### Security

- Request spans and outgoing HTTP spans redact `code`, `state`, `access_token`, `refresh_token`, `id_token`, `token`, `client_secret`, `password`, `api_key`, `X-Amz-Signature`, `X-Amz-Credential` and `X-Amz-Security-Token` in `url.query` and `url.full`. Before, keepsuit redacted only a few signing parameters, so OAuth login codes and S3 download signatures were exported in clear text. The list is `telemetry.sensitive_query_parameters`, so an app can change it in `config/telemetry.php`.

## [0.2.0] - 2026-10-28

### Added

- The `@telemetry` Blade directive renders the config that `@actengage/telemetry-browser` reads, and a `traceparent` meta tag, so the first page load joins the request's trace. The `TELEMETRY_BROWSER_*` variables set it. The URL and the key default to `OTEL_EXPORTER_OTLP_ENDPOINT` and the authorization header in `OTEL_EXPORTER_OTLP_HEADERS`. A setting that is unset, blank or the wrong type reports an `InvalidArgumentException`, and the page renders with browser telemetry off.
- In Inertia apps, the package shares the logged-in user as the `telemetry.user` prop.

## [0.1.2] - 2026-10-27

### Changed

- The README covers the athena `deploy.php` line for `ATHENA_DEPLOY_COMMIT`, the general `OTEL_EXPORTER_OTLP_TIMEOUT`, `TELEMETRY_QUEUE_MONITOR=horizon` for Horizon apps, and what changes when an app deletes or keeps `config/opentelemetry.php`.
- The Boost skill tells apps to test their own telemetry code (`HasTelemetryAttributes`, `AiSpanStarted` listeners, the user resolver), and shows how.
- The README shows the `post-update-cmd` entry that runs `boost:update`, so the app's skill follows each new version of the package.

### Fixed

- The README no longer asks Laravel Cloud apps for a Composer auth step, and the Boost skill's license is MIT.

## [0.1.1] - 2026-10-27

### Changed

- The package is open source under the MIT license, and the repository is public. Apps install it without Composer credentials.

### Fixed

- A streamed response body (`response()->stream()`, `eventStream()`, `streamJson()`) now runs in a `{method} {route} stream` span in the request's trace. Before, its spans, jobs and logs started a new trace. The request span still ends when the controller returns.
- The README and the Boost skill now turn off the OpenTelemetry SDK in tests with `<server name="OTEL_SDK_DISABLED" value="true"/>`. The `<env>` entry did not turn it off. Update the app's `phpunit.xml`.

## [0.1.0] - 2026-10-23

### Added

- Spans for Artisan commands, except workers, the scheduler and `telemetry:*` commands.
- Log export through a tap on the default log channel. `TELEMETRY_LOG_LEVEL` sets the level, and Context data becomes log attributes.
- Scheduler and queue heartbeats. The queue monitor is `queue` or `horizon`.
- Spans for `laravel/ai` agents, tools, provider operations, stores and files.
- `HasTelemetryAttributes` and the `AiSpanStarted` event, to add attributes to AI spans.
- The authenticated user on AI spans and logs, also in queued jobs.
- `deployment.environment.name` and `service.version` resource attributes.
- A Boost skill for coding agents.

[unreleased]: https://github.com/ActiveEngagement/laravel-telemetry/compare/v0.2.3...HEAD
[0.2.3]: https://github.com/ActiveEngagement/laravel-telemetry/compare/v0.2.2...v0.2.3
[0.2.2]: https://github.com/ActiveEngagement/laravel-telemetry/compare/v0.2.1...v0.2.2
[0.2.1]: https://github.com/ActiveEngagement/laravel-telemetry/compare/v0.2.0...v0.2.1
[0.2.0]: https://github.com/ActiveEngagement/laravel-telemetry/compare/v0.1.2...v0.2.0
[0.1.2]: https://github.com/ActiveEngagement/laravel-telemetry/compare/v0.1.1...v0.1.2
[0.1.1]: https://github.com/ActiveEngagement/laravel-telemetry/compare/v0.1.0...v0.1.1
[0.1.0]: https://github.com/ActiveEngagement/laravel-telemetry/releases/tag/v0.1.0
