<?php

namespace App\Services\Llm;

use App\Enums\LlmProvider;
use App\Services\Llm\Fakes\FakeLlmProvider;
use App\Services\Llm\Providers\AnthropicProvider;
use App\Services\Llm\Providers\DeepSeekProvider;
use App\Services\Llm\Providers\GeminiProvider;
use App\Services\Llm\Providers\OpenAiProvider;

class ProviderRegistry
{
    /** @var array<string, LlmProviderAdapter> */
    private array $fakes = [];

    public function adapter(LlmProvider $provider): LlmProviderAdapter
    {
        if (config('llm.provider') === 'fake') {
            return $this->fake($provider);
        }

        return match ($provider) {
            LlmProvider::Anthropic => app(AnthropicProvider::class),
            LlmProvider::OpenAi => app(OpenAiProvider::class),
            LlmProvider::Gemini => app(GeminiProvider::class),
            LlmProvider::DeepSeek => app(DeepSeekProvider::class),
        };
    }

    public function fake(LlmProvider $provider): FakeLlmProvider
    {
        $fake = $this->fakes[$provider->value] ??= new FakeLlmProvider($provider);
        assert($fake instanceof FakeLlmProvider);

        return $fake;
    }
}
