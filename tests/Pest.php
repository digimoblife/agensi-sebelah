<?php

use App\Enums\CredentialProvider;
use App\Enums\LlmProvider;
use App\Models\Client;
use App\Models\ModelCatalogEntry;
use App\Models\RoleConfig;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Credentials\CredentialStore;
use App\Services\Llm\Fakes\FakeLlmProvider;
use App\Services\Llm\ProviderRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeSecrets;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature');

function workspace(): Workspace
{
    return Workspace::query()->orderBy('id')->first() ?? Workspace::query()->create(['name' => 'Test workspace']);
}

function admin(): User
{
    return User::factory()->create(['workspace_id' => workspace()->id, 'role' => 'owner']);
}

function client(array $attributes = []): Client
{
    workspace();

    return Client::factory()->create($attributes);
}

/** A routable model with saved credentials, wired as the role's primary (and optional fallback). */
function routeRole(string $role, ?ModelCatalogEntry $primary = null, ?ModelCatalogEntry $fallback = null): ModelCatalogEntry
{
    workspace();
    $primary ??= ModelCatalogEntry::factory()->create();
    foreach (array_filter([$primary, $fallback]) as $model) {
        app(CredentialStore::class)->save(CredentialProvider::from($model->provider->value), ['api_key' => FakeSecrets::apiKey($model->provider)]);
    }
    RoleConfig::query()->updateOrCreate(
        ['workspace_id' => workspace()->id, 'role_key' => $role],
        ['primary_model_id' => $primary->id, 'fallback_model_id' => $fallback?->id],
    );

    return $primary;
}

function fakeLlm(LlmProvider $provider = LlmProvider::Anthropic): FakeLlmProvider
{
    return app(ProviderRegistry::class)->fake($provider);
}
