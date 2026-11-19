<?php

/*
| Application credentials entered in Pengaturan > Kredensial (docs/specs/credentials-settings.md).
| `secret` fields are never shown again after saving (only the last 4 characters).
| `hidden` providers are prepared but not shown until their phase.
*/

return [
    'providers' => [
        'anthropic' => [
            'label' => 'Anthropic Claude',
            'group' => 'ai',
            'fields' => [
                'api_key' => ['label' => 'API key', 'secret' => true, 'required' => true, 'prefix' => 'sk-ant-', 'min' => 30],
            ],
        ],
        'openai' => [
            'label' => 'OpenAI',
            'group' => 'ai',
            'fields' => [
                'api_key' => ['label' => 'API key', 'secret' => true, 'required' => true, 'prefix' => 'sk-', 'min' => 30],
                'organization' => ['label' => 'Organization ID (opsional)', 'secret' => false, 'required' => false],
            ],
        ],
        'gemini' => [
            'label' => 'Google Gemini',
            'group' => 'ai',
            'fields' => [
                'api_key' => ['label' => 'API key', 'secret' => true, 'required' => true, 'min' => 30],
            ],
        ],
        'deepseek' => [
            'label' => 'DeepSeek',
            'group' => 'ai',
            'fields' => [
                'api_key' => ['label' => 'API key', 'secret' => true, 'required' => true, 'prefix' => 'sk-', 'min' => 20],
                'base_url' => ['label' => 'Base URL', 'secret' => false, 'required' => false, 'default' => 'https://api.deepseek.com'],
            ],
        ],
        'telegram' => [
            'label' => 'Telegram Bot',
            'group' => 'channels',
            'fields' => [
                'bot_token' => ['label' => 'Bot token (dari @BotFather)', 'secret' => true, 'required' => true, 'regex' => '/^\d{5,}:[A-Za-z0-9_-]{30,}$/'],
            ],
        ],
        'meta' => [
            'label' => 'Meta (Instagram & Facebook)',
            'group' => 'channels',
            'fields' => [
                'app_id' => ['label' => 'App ID', 'secret' => false, 'required' => true, 'regex' => '/^\d{5,}$/'],
                'app_secret' => ['label' => 'App Secret', 'secret' => true, 'required' => true, 'min' => 20],
                'graph_version' => ['label' => 'Graph API version (mis. v24.0)', 'secret' => false, 'required' => true, 'regex' => '/^v\d+\.\d+$/'],
            ],
        ],
        'google_oauth' => [
            'label' => 'Google OAuth (GA4, Search Console, Business Profile)',
            'group' => 'channels',
            'fields' => [
                'client_id' => ['label' => 'Client ID', 'secret' => false, 'required' => true, 'regex' => '/\.apps\.googleusercontent\.com$/'],
                'client_secret' => ['label' => 'Client Secret', 'secret' => true, 'required' => true, 'min' => 10],
            ],
        ],
        'tiktok' => [
            'label' => 'TikTok',
            'group' => 'channels',
            'hidden' => true, // Phase 2
            'fields' => [
                'client_key' => ['label' => 'Client key', 'secret' => false, 'required' => true],
                'client_secret' => ['label' => 'Client secret', 'secret' => true, 'required' => true],
            ],
        ],
    ],

    'test_timeout_seconds' => 10,
];
