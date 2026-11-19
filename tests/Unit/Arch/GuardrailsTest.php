<?php

use Illuminate\Contracts\Queue\ShouldQueue;

// Guard rails from CLAUDE.md "Aturan yang tidak boleh dilanggar".

arch('no debugging output that could leak secrets or client data')
    ->expect(['dd', 'dump', 'var_dump', 'print_r', 'var_export', 'error_log', 'ray'])
    ->not->toBeUsed();

arch('rule 1: only LLM provider adapters may use the HTTP client')
    ->expect('Illuminate\Support\Facades\Http')
    ->toOnlyBeUsedIn([
        'App\Services\Llm\Providers',
        'App\Services\Telegram\HttpTelegramClient',
        'App\Services\Publish\Platforms',
        'App\Services\Connections',
        'App\Services\Publish\LinkChecker',
        'App\Services\Ops\PilotReadiness',
        'App\Services\Analytics\Sources',
        'App\Services\Render\GotenbergRenderer',
        'App\Services\Render\FontLibrary',
        'App\Services\Credentials\ConnectionTesters',
    ]);

arch('rule 1: provider SDKs are only used inside the LLM gateway adapters')
    ->expect(['Anthropic', 'OpenAI', 'Gemini', 'DeepSeek'])
    ->toOnlyBeUsedIn('App\Services\Llm\Providers');

arch('roles never call providers directly')
    ->expect('App\Roles')
    ->not->toUse(['App\Services\Llm\Providers', 'Illuminate\Support\Facades\Http']);

arch('queued jobs implement ShouldQueue')
    ->expect('App\Jobs')
    ->classes()
    ->toImplement(ShouldQueue::class);
