<?php

namespace App\Enums;

/** Every external service whose application credentials are entered in Pengaturan > Kredensial. */
enum CredentialProvider: string
{
    case Anthropic = 'anthropic';
    case OpenAi = 'openai';
    case Gemini = 'gemini';
    case DeepSeek = 'deepseek';
    case Telegram = 'telegram';
    case Meta = 'meta';
    case GoogleOauth = 'google_oauth';
    case TikTok = 'tiktok';

    public function llmProvider(): ?LlmProvider
    {
        return LlmProvider::tryFrom($this->value);
    }
}
