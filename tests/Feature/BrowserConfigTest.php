<?php

declare(strict_types=1);

use ActiveEngagement\Telemetry\Browser\BrowserConfig;
use ActiveEngagement\Telemetry\Contracts\BrowserContext;
use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Keepsuit\LaravelOpenTelemetry\Facades\OpenTelemetry;

function resolvedBrowserConfig(): ?BrowserConfig
{
    return resolve(BrowserContext::class)->config();
}

it('builds the config that the browser package reads from the app settings', function (): void {
    configureBrowser([
        'opentelemetry.service_name' => 'savant',
        'opentelemetry.resource_attributes' => [
            'deployment.environment.name' => 'production',
            'service.version' => '4f15fac2d1e0b3a9c8e7f6a5b4c3d2e1f0a9b8c7',
        ],
        'telemetry.browser.api_key' => '00000000-0000-4000-8000-000000000000',
    ]);
    OpenTelemetry::user(fn (Authenticatable $user): array => [
        'user.id' => $user->getAuthIdentifier(),
        'user.name' => 'Ada Lovelace',
        'user.email' => 'ada@example.com',
    ]);
    $this->actingAs(new GenericUser(['id' => 7]));

    $json = json_encode(resolvedBrowserConfig(), JSON_THROW_ON_ERROR);

    expect(json_decode($json, true))->toBe(json_decode((string) file_get_contents(__DIR__.'/../Fixtures/browser-config.v1.json'), true));
});

it('defaults the URL to the OTLP endpoint', function (): void {
    refreshWithEnv('OTEL_EXPORTER_OTLP_ENDPOINT', 'https://otlp.example.com');
    config()->set('telemetry.browser.api_key', 'ingest-key');

    $config = resolvedBrowserConfig();

    expect($config?->url)->toBe('https://otlp.example.com');
});

it('defaults the API key to the authorization header of the OTLP exporter', function (?string $apiKey): void {
    configureBrowser([
        'telemetry.browser.api_key' => $apiKey,
        'opentelemetry.exporters.otlp.traces_headers' => 'x-team=ops,Authorization=ingest%2Bkey',
    ]);

    $config = resolvedBrowserConfig();

    expect($config?->apiKey)->toBe('ingest+key');
})->with(['unset' => [null], 'blank' => ['']]);

it('prefers TELEMETRY_BROWSER_API_KEY to the OTLP authorization header', function (): void {
    configureBrowser(['opentelemetry.exporters.otlp.traces_headers' => 'authorization=server-key']);

    $config = resolvedBrowserConfig();

    expect($config?->apiKey)->toBe('ingest-key');
});

it('is null when telemetry or browser telemetry is off, even without settings', function (string $key): void {
    config()->set($key, false);

    $config = resolvedBrowserConfig();

    expect($config)->toBeNull();
})->with(['telemetry.enabled', 'telemetry.browser.enabled']);

it('throws when a setting is unset, blank or the wrong type', function (array $settings, string $message): void {
    configureBrowser($settings);

    expect(fn (): ?BrowserConfig => resolvedBrowserConfig())->toThrow(InvalidArgumentException::class, $message);
})->with([
    'unset URL' => [['telemetry.browser.url' => null], 'Configuration value for key [telemetry.browser.url] must be a string, NULL given.'],
    'blank URL' => [['telemetry.browser.url' => ''], 'Browser telemetry has no URL.'],
    'unset API key' => [['telemetry.browser.api_key' => null, 'opentelemetry.exporters.otlp.traces_headers' => 'x-team=ops'], 'Browser telemetry has no API key.'],
    'blank service name' => [['opentelemetry.service_name' => ''], 'Browser telemetry has no service name.'],
    'unset environment' => [['opentelemetry.resource_attributes' => []], 'Array value for key [deployment.environment.name] must be a string, NULL found.'],
    'blank environment' => [['opentelemetry.resource_attributes' => ['deployment.environment.name' => '']], 'Browser telemetry has no environment.'],
    'switch that is not a boolean' => [['telemetry.browser.replay.enabled' => '1'], 'Configuration value for key [telemetry.browser.replay.enabled] must be a boolean, string given.'],
]);

it('has no version when no deployment is known', function (): void {
    configureBrowser();

    $config = resolvedBrowserConfig();

    expect($config?->version)->toBeNull();
});

it('has no user when no user is logged in', function (): void {
    configureBrowser();

    $config = resolvedBrowserConfig();

    expect($config?->user)->toBeNull();
});

it('has only the user id when the resolver returns no name or email', function (): void {
    configureBrowser();
    $this->actingAs(new GenericUser(['id' => 42]));

    $config = resolvedBrowserConfig();

    expect($config?->user)->toBe(['id' => 42, 'name' => null, 'email' => null]);
});

it('throws when the user resolver fails', function (): void {
    configureBrowser();
    OpenTelemetry::user(fn () => throw new RuntimeException('Resolver is broken'));
    $this->actingAs(new GenericUser(['id' => 42]));

    expect(fn (): ?BrowserConfig => resolvedBrowserConfig())->toThrow(RuntimeException::class, 'Resolver is broken');
});

it('turns replay off and blocks nothing when the app says so', function (): void {
    configureBrowser([
        'telemetry.browser.replay.enabled' => false,
        'telemetry.browser.replay.block_selector' => null,
    ]);

    $config = resolvedBrowserConfig();

    expect($config?->toArray()['replay'])->toBe(['enabled' => false, 'maskAllInputs' => true, 'blockSelector' => null]);
});
