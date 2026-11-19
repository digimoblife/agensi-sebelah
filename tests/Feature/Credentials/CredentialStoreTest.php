<?php

use App\Enums\CredentialProvider;
use App\Enums\CredentialStatus;
use App\Enums\LlmProvider;
use App\Models\ActivityLog;
use App\Models\ProviderCredential;
use App\Services\Credentials\ConnectionResult;
use App\Services\Credentials\CredentialStore;
use App\Services\Credentials\CredentialValidator;
use Illuminate\Support\Facades\DB;
use Tests\Support\FakeSecrets;

beforeEach(fn () => workspace());

it('stores secrets encrypted and exposes only the last four characters', function () {
    $key = FakeSecrets::apiKey(LlmProvider::Anthropic, 'WXYZ');
    $store = app(CredentialStore::class);

    $store->save(CredentialProvider::Anthropic, ['api_key' => $key]);

    $raw = DB::table('provider_credentials')->value('value');
    expect($raw)->not->toContain($key)
        ->and($store->get(CredentialProvider::Anthropic, 'api_key'))->toBe($key)
        ->and($store->display(CredentialProvider::Anthropic, 'api_key'))->toBe('••••••••WXYZ')
        ->and(ProviderCredential::query()->first()->toArray())->not->toHaveKey('value');
});

it('keeps the stored value when a field is submitted empty', function () {
    $store = app(CredentialStore::class);
    $key = FakeSecrets::apiKey(LlmProvider::OpenAi);
    $store->save(CredentialProvider::OpenAi, ['api_key' => $key]);

    $changed = $store->save(CredentialProvider::OpenAi, ['api_key' => '', 'organization' => 'org-123']);

    expect($changed)->toBe(['organization'])
        ->and($store->get(CredentialProvider::OpenAi, 'api_key'))->toBe($key)
        ->and($store->display(CredentialProvider::OpenAi, 'organization'))->toBe('org-123');
});

it('uses field defaults and tracks completeness', function () {
    $store = app(CredentialStore::class);
    expect($store->isComplete(CredentialProvider::DeepSeek))->toBeFalse();

    $store->save(CredentialProvider::DeepSeek, ['api_key' => FakeSecrets::apiKey(LlmProvider::DeepSeek)]);

    expect($store->isComplete(CredentialProvider::DeepSeek))->toBeTrue()
        ->and($store->values(CredentialProvider::DeepSeek)['base_url'])->toBe('https://api.deepseek.com');
});

it('resets the status to untested when a value changes and marks failed providers unusable', function () {
    $store = app(CredentialStore::class);
    $store->save(CredentialProvider::Telegram, ['bot_token' => FakeSecrets::telegramToken()]);
    $store->recordTest(CredentialProvider::Telegram, ConnectionResult::failed('401_unauthorized', 'ditolak'));

    expect($store->isUsable(CredentialProvider::Telegram))->toBeFalse();

    $store->save(CredentialProvider::Telegram, ['bot_token' => '987654321:'.str_repeat('Zz', 18)]);

    expect($store->status(CredentialProvider::Telegram)->status)->toBe(CredentialStatus::Untested)
        ->and($store->isUsable(CredentialProvider::Telegram))->toBeTrue();
});

it('never writes credential values to the activity log', function () {
    $key = FakeSecrets::apiKey(LlmProvider::Gemini, 'SECRETTAIL');
    $store = app(CredentialStore::class);
    $store->save(CredentialProvider::Gemini, ['api_key' => $key]);
    $store->recordTest(CredentialProvider::Gemini, ConnectionResult::ok('OK'));
    $store->forget(CredentialProvider::Gemini);

    expect(ActivityLog::query()->pluck('action')->all())->toBe(['credentials.updated', 'credentials.tested', 'credentials.deleted'])
        ->and(json_encode(ActivityLog::query()->get()->toArray()))->not->toContain('SECRETTAIL')
        ->and(ProviderCredential::query()->count())->toBe(0);
});

it('rejects values that look pasted into the wrong field', function (CredentialProvider $provider, array $input, string $field) {
    expect(app(CredentialValidator::class)->errors($provider, $input))->toHaveKey($field);
})->with([
    'anthropic prefix' => [CredentialProvider::Anthropic, ['api_key' => str_repeat('x', 40)], 'api_key'],
    'telegram format' => [CredentialProvider::Telegram, ['bot_token' => 'not-a-token'], 'bot_token'],
    'meta app id' => [CredentialProvider::Meta, ['app_id' => 'abc'], 'app_id'],
    'google client id' => [CredentialProvider::GoogleOauth, ['client_id' => 'something.example.com'], 'client_id'],
]);
