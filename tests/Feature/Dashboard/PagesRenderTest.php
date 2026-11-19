<?php

use App\Filament\Pages\ContentCalendar;
use App\Filament\Pages\Credentials;
use App\Filament\Resources\Clients\Pages\EditClient;
use App\Filament\Resources\Clients\Pages\ListClients;
use App\Filament\Resources\ContentItems\Pages\ListContentItems;
use App\Filament\Resources\JobRuns\Pages\ListJobRuns;
use App\Filament\Resources\LlmCalls\Pages\ListLlmCalls;
use App\Filament\Resources\ModelCatalog\Pages\ManageModelCatalog;
use App\Filament\Resources\Moments\Pages\ManageMoments;
use App\Filament\Resources\RoleConfigs\Pages\ManageRoleConfigs;
use Database\Seeders\ModelCatalogSeeder;
use Database\Seeders\MomentCalendarSeeder;
use Database\Seeders\OfferingTypeSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed([OfferingTypeSeeder::class, ModelCatalogSeeder::class, MomentCalendarSeeder::class]);
    $this->actingAs(admin());
});

it('renders every dashboard page', function (string $page) {
    Livewire::test($page)->assertSuccessful();
})->with([
    Credentials::class, ListClients::class, ManageModelCatalog::class, ManageRoleConfigs::class,
    ListLlmCalls::class, ListJobRuns::class, ListContentItems::class, ContentCalendar::class, ManageMoments::class,
]);

it('renders the client edit page with its relation tabs', function () {
    $client = client(['primary_offering_type' => 'event_ticket']);

    Livewire::test(EditClient::class, ['record' => $client->getKey()])
        ->assertSuccessful()
        ->assertSee(['Profil & onboarding', 'Knowledge base', 'Artefak', 'Bahan riset']);
});

it('lists the seeded Claude models as ready to route', function () {
    Livewire::test(ManageModelCatalog::class)->assertSee(['Claude Opus 5.5', 'Claude Haiku 5.5']);
});
