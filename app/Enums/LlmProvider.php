<?php

namespace App\Enums;

enum LlmProvider: string
{
    case Anthropic = 'anthropic';
    case OpenAi = 'openai';
    case Gemini = 'gemini';
    case DeepSeek = 'deepseek';

    public function label(): string
    {
        return match ($this) {
            self::Anthropic => 'Anthropic Claude',
            self::OpenAi => 'OpenAI',
            self::Gemini => 'Google Gemini',
            self::DeepSeek => 'DeepSeek',
        };
    }
}
