<?php

namespace App\Services\Llm;

final readonly class RoleResult
{
    /** @param  array<string, mixed>  $payload  validated against the role's output schema (meta not included) */
    public function __construct(
        public array $payload,
        public int $llmCallId,
        public float $costUsd,
        public string $modelId,
        public bool $usedFallback,
    ) {}
}
