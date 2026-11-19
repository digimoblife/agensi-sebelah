<?php

namespace App\Services\Llm;

/**
 * One provider call. The prompt is split so the stable part (system + client context) comes first and can be
 * prefix-cached (docs/LLM-GATEWAY.md step 3).
 */
final readonly class CompletionRequest
{
    /**
     * @param  array<string, mixed>|null  $jsonSchema  provider-safe schema (SchemaRepository::providerSchema)
     */
    public function __construct(
        public string $modelId,
        public string $system,
        public string $cachedContext,
        public string $task,
        public ?array $jsonSchema,
        public string $schemaName,
        public int $maxTokens,
        public ?string $effort = null,
        public bool $serverFallback = false,
    ) {}
}
