<?php

declare(strict_types=1);

namespace ActiveEngagement\Telemetry\Browser;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * @implements Arrayable<string, mixed>
 */
class BrowserConfig implements Arrayable, JsonSerializable
{
    public const int SCHEMA_VERSION = 1;

    /**
     * @param  array{id: int|string, name: string|null, email: string|null}|null  $user
     */
    public function __construct(
        public string $url,
        public string $apiKey,
        public string $service,
        public string $environment,
        public ?string $version,
        public ?array $user,
        public bool $replay,
        public bool $maskAllInputs,
        public ?string $blockSelector,
        public bool $consoleCapture,
    ) {}

    /**
     * @return array{v: int, enabled: false}
     */
    public static function disabled(): array
    {
        return ['v' => static::SCHEMA_VERSION, 'enabled' => false];
    }

    /**
     * @return array{v: int, enabled: true, url: string, apiKey: string, service: string, environment: string, version: string|null, user: array{id: int|string, name: string|null, email: string|null}|null, replay: array{enabled: bool, maskAllInputs: bool, blockSelector: string|null}, consoleCapture: bool}
     */
    public function toArray(): array
    {
        return [
            'v' => static::SCHEMA_VERSION,
            'enabled' => true,
            'url' => $this->url,
            'apiKey' => $this->apiKey,
            'service' => $this->service,
            'environment' => $this->environment,
            'version' => $this->version,
            'user' => $this->user,
            'replay' => [
                'enabled' => $this->replay,
                'maskAllInputs' => $this->maskAllInputs,
                'blockSelector' => $this->blockSelector,
            ],
            'consoleCapture' => $this->consoleCapture,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
