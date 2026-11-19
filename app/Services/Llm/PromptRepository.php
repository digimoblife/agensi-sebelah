<?php

namespace App\Services\Llm;

use RuntimeException;

/** Versioned prompts in resources/prompts/<key>/v<N>.md. Never edit a published version; add v<N+1>. */
class PromptRepository
{
    public function get(string $key, int $version): string
    {
        $path = resource_path("prompts/{$key}/v{$version}.md");
        if (! is_file($path)) {
            throw new RuntimeException("Prompt [{$key}@v{$version}] does not exist.");
        }

        return trim((string) file_get_contents($path));
    }

    public function checksum(string $key, int $version): string
    {
        return hash('sha256', $this->get($key, $version));
    }
}
