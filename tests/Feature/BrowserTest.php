<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Exceptions;
use Inertia\Inertia;
use Keepsuit\LaravelOpenTelemetry\Facades\OpenTelemetry;
use OpenTelemetry\API\Trace\TracerInterface;

/**
 * @return array<mixed>
 */
function renderedBrowserConfig(): array
{
    preg_match('#<script id="telemetry-config" type="application/json">(.*)</script>#', Blade::render('@telemetry'), $matches);

    return json_decode($matches[1] ?? 'null', true, flags: JSON_THROW_ON_ERROR) ?? [];
}

it('renders the config in the tag that the browser package reads', function (): void {
    configureBrowser();

    $config = renderedBrowserConfig();

    expect($config)->toMatchArray(['v' => 1, 'enabled' => true, 'url' => 'https://telemetry.example.com']);
});

it('renders the disabled config when browser telemetry is off', function (): void {
    config()->set('telemetry.browser.enabled', false);

    $config = renderedBrowserConfig();

    expect($config)->toBe(['v' => 1, 'enabled' => false]);
});

it('renders the disabled config when telemetry is off at boot', function (): void {
    $this->refreshWithConfig(['telemetry.enabled' => false]);

    $config = renderedBrowserConfig();

    expect($config)->toBe(['v' => 1, 'enabled' => false]);
});

it('reports a missing setting and renders the disabled config', function (): void {
    Exceptions::fake();
    configureBrowser(['telemetry.browser.url' => null]);

    $config = renderedBrowserConfig();

    expect($config)->toBe(['v' => 1, 'enabled' => false]);
    Exceptions::assertReported(InvalidArgumentException::class);
});

it('escapes a closing script tag in a value', function (): void {
    configureBrowser();
    OpenTelemetry::user(fn (Authenticatable $user): array => [
        'user.id' => $user->getAuthIdentifier(),
        'user.name' => '</script><script>alert(1)</script>',
    ]);
    $this->actingAs(new GenericUser(['id' => 42]));

    $html = Blade::render('@telemetry');

    expect(mb_substr_count($html, '</script>'))->toBe(1)
        ->and(renderedBrowserConfig()['user']['name'])->toBe('</script><script>alert(1)</script>');
});

it('renders the trace parent of the request trace', function (): void {
    configureBrowser();
    $span = resolve(TracerInterface::class)->spanBuilder('GET /')->startSpan();
    $scope = $span->activate();

    try {
        $html = Blade::render('@telemetry');
    } finally {
        $scope->detach();
        $span->end();
    }

    expect($html)->toMatch(sprintf('#<meta name="traceparent" content="00-%s-[0-9a-f]{16}-01">#', $span->getContext()->getTraceId()));
});

it('renders no trace parent outside a trace', function (): void {
    configureBrowser();

    $html = Blade::render('@telemetry');

    expect($html)->not->toContain('traceparent');
});

it('shares the logged-in user with Inertia pages', function (): void {
    $this->actingAs(new GenericUser(['id' => 42]));

    $shared = value(Inertia::getShared('telemetry'));

    expect($shared)->toBe(['user' => ['id' => 42, 'name' => null, 'email' => null]]);
});

it('reports a user resolver failure and shares no user with Inertia pages', function (): void {
    Exceptions::fake();
    OpenTelemetry::user(fn () => throw new RuntimeException('Resolver is broken'));
    $this->actingAs(new GenericUser(['id' => 42]));

    $shared = value(Inertia::getShared('telemetry'));

    expect($shared)->toBe(['user' => null]);
    Exceptions::assertReported(RuntimeException::class);
});

it('does not share the user when browser telemetry is off', function (): void {
    $this->refreshWithConfig(['telemetry.browser.enabled' => false]);

    $shared = Inertia::getShared('telemetry');

    expect($shared)->toBeNull();
});
