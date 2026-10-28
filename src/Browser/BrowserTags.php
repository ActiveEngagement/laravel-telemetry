<?php

declare(strict_types=1);

namespace ActiveEngagement\Telemetry\Browser;

use ActiveEngagement\Telemetry\Contracts\BrowserContext;

class BrowserTags
{
    public const string CONFIG_ELEMENT_ID = 'telemetry-config';

    public function __construct(protected BrowserContext $context) {}

    public static function compile(): string
    {
        return '<?php echo app(\\'.static::class.'::class)->render(); ?>';
    }

    public function render(): string
    {
        return rescue(
            fn (): string => $this->traceparentTag().$this->configTag($this->context->config()?->toArray() ?? BrowserConfig::disabled()),
            fn (): string => $this->configTag(BrowserConfig::disabled()),
        );
    }

    protected function traceparentTag(): string
    {
        $traceparent = $this->context->traceparent();

        return $traceparent === null ? '' : '<meta name="traceparent" content="'.e($traceparent).'">'.PHP_EOL;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function configTag(array $payload): string
    {
        $json = json_encode($payload, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        return '<script id="'.static::CONFIG_ELEMENT_ID.'" type="application/json">'.$json.'</script>';
    }
}
