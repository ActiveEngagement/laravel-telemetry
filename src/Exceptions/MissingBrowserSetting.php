<?php

declare(strict_types=1);

namespace ActiveEngagement\Telemetry\Exceptions;

use InvalidArgumentException;

class MissingBrowserSetting extends InvalidArgumentException
{
    public static function url(): self
    {
        return new self('Browser telemetry has no URL. Set TELEMETRY_BROWSER_URL or OTEL_EXPORTER_OTLP_ENDPOINT, or turn it off with TELEMETRY_BROWSER_ENABLED=false.');
    }

    public static function apiKey(): self
    {
        return new self('Browser telemetry has no API key. Set TELEMETRY_BROWSER_API_KEY or an authorization header in OTEL_EXPORTER_OTLP_HEADERS, or turn it off with TELEMETRY_BROWSER_ENABLED=false.');
    }

    public static function serviceName(): self
    {
        return new self('Browser telemetry has no service name. Set OTEL_SERVICE_NAME.');
    }

    public static function environment(): self
    {
        return new self('Browser telemetry has no environment. The deployment.environment.name resource attribute is not set.');
    }
}
