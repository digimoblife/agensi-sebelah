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

    Livewire::test(Credentials::class)
        ->set('data.anthropic.api_key', $key)
        ->call('saveProvider', CredentialProvider::Anthropic)
        ->assertHasNoErrors()
        ->assertRedirect(Credentials::getUrl());

    // After the reload the section shows the masked value and the test button is enabled.
    $page = Livewire::test(Credentials::class);
    expect(app(CredentialStore::class)->get(CredentialProvider::Anthropic, 'api_key'))->toBe($key)
        ->and($page->get('data.anthropic'))->toBeNull()
        ->and($page->html())->not->toContain($key)->toContain('••••••••PAGE')
        // "Belum dites" is shown only when the provider is complete, i.e. when "Tes koneksi" is enabled.
        ->toContain('Belum dites');
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

    Livewire::test(Credentials::class)->call('testProvider', CredentialProvider::OpenAi)->assertRedirect(Credentials::getUrl());

    expect(app(CredentialStore::class)->status(CredentialProvider::OpenAi)->status)->toBe(CredentialStatus::Ok);
});

it('explains that Meta and Google are the agency app, connected per client elsewhere', function () {
    Livewire::test(Credentials::class)->assertSee(['Diisi sekali untuk agensi: satu Meta App untuk semua klien', 'Klien → Koneksi akun']);
});
