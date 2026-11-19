<?php

use App\Enums\ContentStatus;
use App\Models\ContentItem;
use App\Models\WorkflowEvent;
use App\Services\Workflow\ContentWorkflow;
use App\Services\Workflow\InvalidTransition;

it('refuses transitions outside the matrix and records allowed ones', function () {
    $client = client();
    $item = ContentItem::query()->create(['workspace_id' => $client->workspace_id, 'client_id' => $client->id, 'month' => '2026-12',
        'plan_item_key' => 'x1', 'platform' => 'instagram', 'format' => 'story', 'pillar' => 'p', 'publish_date' => '2026-12-01',
        'angle' => 'a', 'cta' => 'c', 'status' => ContentStatus::Planned]);
    $workflow = app(ContentWorkflow::class);

    expect(fn () => $workflow->transition($item, ContentStatus::Approved, 'admin:1'))->toThrow(InvalidTransition::class);

    $workflow->transition($item, ContentStatus::Drafting, 'admin:1', 'mulai');
    expect($item->status)->toBe(ContentStatus::Drafting)
        ->and(WorkflowEvent::query()->sole()->only(['from_status', 'to_status', 'actor', 'reason']))
        ->toBe(['from_status' => 'planned', 'to_status' => 'drafting', 'actor' => 'admin:1', 'reason' => 'mulai']);
});
