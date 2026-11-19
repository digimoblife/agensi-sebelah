<?php

use App\Enums\CredentialProvider;
use App\Enums\CredentialStatus;
use App\Enums\LlmProvider;
use App\Filament\Pages\Credentials;
use App\Services\Credentials\CredentialStore;
use Livewire\Livewire;
use Tests\Support\FakeSecrets;

beforeEach(fn () => $this->actingAs(admin()));

it('saves a credential without echoing it back to the page', function () {
    $key = FakeSecrets::apiKey(LlmProvider::Anthropic, 'PAGE');

    $page = Livewire::test(Credentials::class)
        ->set('data.anthropic.api_key', $key)
        ->call('saveProvider', CredentialProvider::Anthropic)
        ->assertHasNoErrors();

    expect(app(CredentialStore::class)->get(CredentialProvider::Anthropic, 'api_key'))->toBe($key)
        ->and($page->get('data.anthropic'))->toBe([])
        ->and($page->html())->not->toContain($key)->toContain('••••••••PAGE');
});

it('shows validation errors for malformed values and stores nothing', function () {
    Livewire::test(Credentials::class)
        ->set('data.telegram.bot_token', 'nope')
        ->call('saveProvider', CredentialProvider::Telegram)
        ->assertHasErrors(['data.telegram.bot_token']);

    expect(app(CredentialStore::class)->isComplete(CredentialProvider::Telegram))->toBeFalse();
});

it('tests an AI provider connection through its adapter and records the result', function () {
    app(CredentialStore::class)->save(CredentialProvider::OpenAi, ['api_key' => FakeSecrets::apiKey(LlmProvider::OpenAi)]);

    Livewire::test(Credentials::class)->call('testProvider', CredentialProvider::OpenAi);

    expect(app(CredentialStore::class)->status(CredentialProvider::OpenAi)->status)->toBe(CredentialStatus::Ok);
});
