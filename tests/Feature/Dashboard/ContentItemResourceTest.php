<?php

use App\Enums\ContentStatus;
use App\Filament\Pages\ContentCalendar;
use App\Filament\Resources\ContentItems\Pages\ListContentItems;
use App\Jobs\DraftContent;
use App\Models\ContentItem;
use Database\Seeders\MomentCalendarSeeder;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(admin());
    $this->client = client(['name' => 'Studio Kalender']);
    $this->item = ContentItem::query()->create(['workspace_id' => $this->client->workspace_id, 'client_id' => $this->client->id,
        'month' => '2026-12', 'plan_item_key' => 'x1', 'platform' => 'instagram', 'format' => 'carousel', 'pillar' => 'process',
        'publish_date' => '2026-12-12', 'angle' => 'Lima langkah pertama', 'cta' => 'Pesan', 'status' => ContentStatus::AwaitingApproval,
        'draft' => ['caption' => 'Halo', 'hashtags' => []]]);
});

it('approves from the content list', function () {
    Livewire::test(ListContentItems::class)->callTableAction('approve', $this->item)->assertHasNoTableActionErrors();

    expect($this->item->fresh()->status)->toBe(ContentStatus::Approved);
});

it('requests a revision and queues a redraft of that item only', function () {
    Queue::fake();

    Livewire::test(ListContentItems::class)
        ->callTableAction('revise', $this->item, ['comment' => 'Hook kurang kuat', 'major' => false])
        ->assertHasNoTableActionErrors();

    expect($this->item->fresh()->status)->toBe(ContentStatus::RevisionRequested);
    Queue::assertPushed(DraftContent::class, fn (DraftContent $job) => $job->itemIds === [$this->item->id]);
});

it('shows items and moments on the calendar', function () {
    $this->seed(MomentCalendarSeeder::class);

    Livewire::test(ContentCalendar::class, ['month' => '2026-12'])
        ->assertSee(['Lima langkah pertama', 'Harbolnas 12.12', 'Studio Kalender']);
});
