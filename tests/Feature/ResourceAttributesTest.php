<?php

declare(strict_types=1);

use OpenTelemetry\API\Trace\TracerInterface;

/**
 * @return array<mixed>
 */
function resourceAttributes(): array
{
    app(TracerInterface::class)->spanBuilder('request')->startSpan()->end();

    return exportedSpans()[0]->getResource()->getAttributes()->toArray();
}

it('adds the environment to the resource', function (): void {
    expect(resourceAttributes())->toMatchArray(['deployment.environment.name' => 'testing']);
});

it('adds no service version when no deployment is known', function (): void {
    expect(resourceAttributes())->not->toHaveKey('service.version');
});

it('uses the deployment as the service version', function (string $name): void {
    refreshWithEnv($name, 'deploy-123');

    expect(resourceAttributes())->toMatchArray(['service.version' => 'deploy-123']);
})->with(['LARAVEL_CLOUD_DEPLOY_UUID', 'FORGE_DEPLOY_COMMIT', 'ATHENA_DEPLOY_COMMIT']);

it('lets OTEL_RESOURCE_ATTRIBUTES override the defaults', function (): void {
    putenv('LARAVEL_CLOUD_DEPLOY_UUID=deploy-123');

    try {
        refreshWithEnv('OTEL_RESOURCE_ATTRIBUTES', 'deployment.environment.name=staging,service.version=v2');
    } finally {
        putenv('LARAVEL_CLOUD_DEPLOY_UUID');
    }

    expect(resourceAttributes())->toMatchArray([
        'deployment.environment.name' => 'staging',
        'service.version' => 'v2',
    ]);
});
