<?php

return [
    // "fake" routes every provider to FakeLlmProvider (forced in tests, refused in production).
    'provider' => env('LLM_PROVIDER', 'real'),

    'timeout_seconds' => 120,

    // Rough token estimate before a call (docs/LLM-GATEWAY.md); corrected afterwards from provider usage.
    'chars_per_token' => 3.5,

    // Per-client monthly defaults until the admin sets a budget (DECISIONS: budgets are in USD).
    'budget' => [
        'default_llm_limit_usd' => (float) env('BUDGET_DEFAULT_LLM_USD', 15),
        'default_media_limit_usd' => (float) env('BUDGET_DEFAULT_MEDIA_USD', 10),
        'alert_ratio' => 0.8,
    ],

    'endpoints' => [
        'openai' => 'https://api.openai.com/v1',
        'gemini' => 'https://generativelanguage.googleapis.com/v1beta',
        'deepseek' => 'https://api.deepseek.com',
    ],
];
