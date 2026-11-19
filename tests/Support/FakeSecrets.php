<?php

namespace Tests\Support;

use App\Enums\LlmProvider;

/**
 * Credential-shaped test values assembled at runtime, so no literal that looks like a real key lives in the repo
 * (secret scanners) and every value is obviously fake.
 */
final class FakeSecrets
{
    public static function apiKey(LlmProvider $provider, string $suffix = 'TEST'): string
    {
        $prefix = match ($provider) {
            LlmProvider::Anthropic => 'sk-'.'ant-',
            LlmProvider::OpenAi, LlmProvider::DeepSeek => 'sk-',
            LlmProvider::Gemini => '',
        };

        return $prefix.str_repeat('fake', 8).$suffix;
    }

    public static function telegramToken(): string
    {
        return '123456789'.':'.str_repeat('Fk', 18);
    }
}
