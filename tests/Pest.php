<?php

use App\Enums\ArtifactType;
use App\Enums\ContentStatus;
use App\Enums\CredentialProvider;
use App\Enums\LlmProvider;
use App\Models\Client;
use App\Models\ContentItem;
use App\Models\ModelCatalogEntry;
use App\Models\RoleConfig;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Artifacts\ArtifactService;
use App\Services\Content\ContentReview;
use App\Services\Credentials\CredentialStore;
use App\Services\Llm\Fakes\FakeLlmProvider;
use App\Services\Llm\ProviderRegistry;
use App\Services\Publish\FakePublisher;
use App\Services\Publish\Publisher;
use App\Services\Render\ContentRenderer;
use App\Services\Render\FakeRenderer;
use App\Services\Render\HtmlRenderer;
use App\Services\Telegram\FakeTelegramClient;
use App\Services\Telegram\TelegramClient;
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

function fakeTelegram(): FakeTelegramClient
{
    $client = app(TelegramClient::class);
    assert($client instanceof FakeTelegramClient);

    return $client;
}

function fakeRenderer(): FakeRenderer
{
    $renderer = app(HtmlRenderer::class);
    assert($renderer instanceof FakeRenderer);

    return $renderer;
}

/** A client with approved Brand Brief + Visual Rules and one content item awaiting approval. */
function awaitingItem(array $overrides = []): ContentItem
{
    $client = client(['primary_offering_type' => null]);
    $service = app(ArtifactService::class);
    $user = admin();
    foreach (['brand_brief' => ArtifactType::BrandBrief, 'visual_rules' => ArtifactType::VisualRules] as $name => $type) {
        $payload = json_decode(file_get_contents(resource_path("schemas/examples/{$name}.v1.json")), true);
        unset($payload['meta']);
        $service->approve($service->createVersion($client, $type, $payload), $user);
    }
    $draft = json_decode(file_get_contents(resource_path('schemas/examples/content_batch.v1.json')), true)['items'][0];

    return ContentItem::query()->create($overrides + [
        'workspace_id' => $client->workspace_id, 'client_id' => $client->id, 'month' => '2026-12', 'plan_item_key' => 'dec-01-process',
        'platform' => 'instagram', 'format' => 'carousel', 'pillar' => 'process', 'publish_date' => '2026-12-01', 'publish_time' => '19:00',
        'angle' => 'Lima langkah', 'cta' => 'Pesan kursimu', 'status' => ContentStatus::AwaitingApproval, 'draft' => $draft,
    ]);
}

function fakePublisher(): FakePublisher
{
    $publisher = app(Publisher::class);
    assert($publisher instanceof FakePublisher);

    return $publisher;
}

/** An approved item whose visual is final (status rendered), publishing on 2026-12-01 19:00 Jakarta. */
function renderedItem(array $overrides = []): ContentItem
{
    $item = awaitingItem($overrides);
    app(ContentRenderer::class)->render($item);
    app(ContentReview::class)->approve($item->fresh(), 'admin:1');

    return $item->fresh();
}

function linkedAdmin(int $chatId = 4242): User
{
    $user = admin();
    $user->forceFill(['telegram_chat_id' => $chatId])->save();

    return $user;
}
