<?php

use App\Enums\ArtifactStatus;
use App\Enums\ArtifactType;
use App\Filament\Resources\Clients\Pages\CreateClient;
use App\Filament\Resources\Clients\Pages\EditClient;
use App\Filament\Resources\Clients\RelationManagers\ArtifactVersionsRelationManager;
use App\Filament\Resources\Clients\RelationManagers\KnowledgeItemsRelationManager;
use App\Models\Client;
use App\Models\KnowledgeItem;
use App\Services\Artifacts\ArtifactService;
use Database\Seeders\OfferingTypeSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(OfferingTypeSeeder::class);
    $this->actingAs(admin());
});

it('creates a client in the current workspace with onboarding answers', function () {
    Livewire::test(CreateClient::class)
        ->fillForm([
            'name' => 'Studio Uji', 'slug' => 'studio-uji', 'status' => 'onboarding', 'primary_offering_type' => 'service',
            'timezone' => 'Asia/Jakarta', 'onboarding.business_description' => 'Studio desain interior.',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $client = Client::query()->sole();
    expect($client->workspace_id)->toBe(workspace()->id)
        ->and($client->onboarding['business_description'])->toBe('Studio desain interior.');
});

it('withdraws approval when a knowledge item is edited', function () {
    $client = client(['primary_offering_type' => 'service']);
    $item = KnowledgeItem::query()->create(['workspace_id' => $client->workspace_id, 'client_id' => $client->id, 'kind' => 'fact',
        'title' => 'Jam buka', 'body' => 'Senin-Jumat 09.00-17.00', 'approved_at' => now()]);

    Livewire::test(KnowledgeItemsRelationManager::class, ['ownerRecord' => $client, 'pageClass' => EditClient::class])
        ->callTableAction('edit', $item, ['body' => 'Senin-Sabtu 09.00-17.00'])
        ->assertHasNoTableActionErrors();

    expect($item->fresh()->approved_at)->toBeNull();
});

it('saves an admin edit as a new validated version and approves it', function () {
    $client = client(['primary_offering_type' => 'service']);
    $payload = json_decode(file_get_contents(resource_path('schemas/examples/visual_rules.v1.json')), true);
    $v1 = app(ArtifactService::class)->createVersion($client, ArtifactType::VisualRules, $payload);
    $payload['colors']['primary'] = '#112233';

    $component = Livewire::test(ArtifactVersionsRelationManager::class, ['ownerRecord' => $client, 'pageClass' => EditClient::class])
        ->callTableAction('edit_json', $v1, ['json' => json_encode($payload), 'change_note' => 'Warna utama']);

    $v2 = $client->artifactVersions()->where('version', 2)->sole();
    expect($v2->payload['colors']['primary'])->toBe('#112233')->and($v2->change_note)->toBe('Warna utama');

    $component->callTableAction('approve', $v2);
    expect($v2->fresh()->status)->toBe(ArtifactStatus::Approved);
});

it('keeps the old version when an edit breaks the schema', function () {
    $client = client(['primary_offering_type' => 'service']);
    $payload = json_decode(file_get_contents(resource_path('schemas/examples/visual_rules.v1.json')), true);
    $v1 = app(ArtifactService::class)->createVersion($client, ArtifactType::VisualRules, $payload);
    $payload['colors']['primary'] = 'red';

    Livewire::test(ArtifactVersionsRelationManager::class, ['ownerRecord' => $client, 'pageClass' => EditClient::class])
        ->callTableAction('edit_json', $v1, ['json' => json_encode($payload)]);

    expect($client->artifactVersions()->count())->toBe(1);
});
