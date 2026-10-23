<?php

namespace Actengage\Telemetry\Listeners\Concerns;

use Laravel\Ai\Responses\Data\TextUsage;
use Laravel\Ai\Responses\Data\Usage;

trait DescribesTokenUsage
{
    /**
     * @return array<non-empty-string, int|null>
     */
    protected function usageAttributes(Usage $usage): array
    {
        return [
            'gen_ai.usage.input_tokens' => $usage->inputTokens,
            'gen_ai.usage.output_tokens' => $usage->outputTokens,
            ...$usage instanceof TextUsage ? [
                'gen_ai.usage.cache_read.input_tokens' => $usage->cacheReadInputTokens,
                'gen_ai.usage.cache_creation.input_tokens' => $usage->cacheWriteInputTokens,
                'gen_ai.usage.reasoning.output_tokens' => $usage->reasoningTokens,
            ] : [],
        ];
    }
}
