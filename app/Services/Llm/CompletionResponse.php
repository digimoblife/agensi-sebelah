<?php

namespace App\Services\Llm;

/** Token counts are normalized: $inputTokens excludes cached reads and cache writes. */
final readonly class CompletionResponse
{
    public function __construct(
        public string $text,
        public int $inputTokens,
        public int $cachedInputTokens,
        public int $cacheWriteTokens,
        public int $outputTokens,
        public ?string $stopReason,
        public string $modelId,
    ) {}
}
