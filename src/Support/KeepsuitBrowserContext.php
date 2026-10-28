<?php

declare(strict_types=1);

namespace ActiveEngagement\Telemetry\Support;

use ActiveEngagement\Telemetry\Browser\BrowserConfig;
use ActiveEngagement\Telemetry\Contracts\BrowserContext;
use ActiveEngagement\Telemetry\Contracts\ContextResolver;
use ActiveEngagement\Telemetry\Exceptions\MissingBrowserSetting;
use Illuminate\Config\Repository;
use Illuminate\Support\Arr;
use Keepsuit\LaravelOpenTelemetry\Facades\Tracer;

class KeepsuitBrowserContext implements BrowserContext
{
    public function __construct(
        protected Repository $config,
        protected ContextResolver $context,
    ) {}

    public function config(): ?BrowserConfig
    {
        if (! $this->config->boolean('telemetry.enabled') || ! $this->config->boolean('telemetry.browser.enabled')) {
            return null;
        }

        $attributes = $this->config->array('opentelemetry.resource_attributes');

        return new BrowserConfig(
            url: $this->config->string('telemetry.browser.url') ?: throw MissingBrowserSetting::url(),
            apiKey: $this->apiKey(),
            service: ($this->config->string('opentelemetry.service_name') ?: throw MissingBrowserSetting::serviceName()).'-browser',
            environment: Arr::string($attributes, 'deployment.environment.name') ?: throw MissingBrowserSetting::environment(),
            version: isset($attributes['service.version']) ? Arr::string($attributes, 'service.version') : null,
            user: $this->user(),
            replay: $this->config->boolean('telemetry.browser.replay.enabled'),
            maskAllInputs: $this->config->boolean('telemetry.browser.replay.mask_all_inputs'),
            blockSelector: $this->blockSelector(),
            consoleCapture: $this->config->boolean('telemetry.browser.console_capture'),
        );
    }

    public function user(): ?array
    {
        $attributes = $this->context->userAttributes();
        $id = $attributes['user.id'] ?? null;
        $name = $attributes['user.name'] ?? null;
        $email = $attributes['user.email'] ?? null;

        if (! is_int($id) && ! is_string($id)) {
            return null;
        }

        return [
            'id' => $id,
            'name' => is_string($name) ? $name : null,
            'email' => is_string($email) ? $email : null,
        ];
    }

    public function traceparent(): ?string
    {
        $traceparent = Tracer::propagationHeaders()['traceparent'] ?? null;

        return is_string($traceparent) ? $traceparent : null;
    }

    protected function apiKey(): string
    {
        if (filled($this->config->get('telemetry.browser.api_key'))) {
            return $this->config->string('telemetry.browser.api_key');
        }

        return $this->otlpIngestionKey() ?? throw MissingBrowserSetting::apiKey();
    }

    protected function otlpIngestionKey(): ?string
    {
        foreach (explode(',', $this->config->string('opentelemetry.exporters.otlp.traces_headers')) as $header) {
            [$name, $value] = array_pad(explode('=', $header, 2), 2, '');

            if (mb_strtolower(mb_trim($name)) === 'authorization' && mb_trim($value) !== '') {
                return urldecode(mb_trim($value));
            }
        }

        return null;
    }

    protected function blockSelector(): ?string
    {
        return $this->config->get('telemetry.browser.replay.block_selector') === null
            ? null
            : $this->config->string('telemetry.browser.replay.block_selector');
    }
}
