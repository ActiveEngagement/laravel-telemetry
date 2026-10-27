# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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

[unreleased]: https://github.com/ActiveEngagement/laravel-telemetry/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/ActiveEngagement/laravel-telemetry/releases/tag/v0.1.0
