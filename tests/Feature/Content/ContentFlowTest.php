<?php

use App\Enums\ArtifactType;
use App\Enums\ContentStatus;
use App\Enums\KnowledgeKind;
use App\Models\Client;
use App\Models\ContentItem;
use App\Models\KnowledgeItem;
use App\Models\WorkflowEvent;
use App\Services\Artifacts\ArtifactGeneration;
use App\Services\Artifacts\ArtifactService;
use App\Services\Content\ContentDrafting;
use App\Services\Content\ContentReview;
use App\Services\Content\GuardianRules;
use App\Services\Llm\Exceptions\OutputInvalid;
use Database\Seeders\MomentCalendarSeeder;
use Database\Seeders\OfferingTypeSeeder;

function example(string $name): array
{
    $data = json_decode(file_get_contents(resource_path("schemas/examples/{$name}.v1.json")), true);
    unset($data['meta']);

    return $data;
}

/** Approved Brand Brief + Visual Rules + fact kb:2, models routed for every content role. */
function contentReadyClient(): Client
{
    test()->seed([OfferingTypeSeeder::class, MomentCalendarSeeder::class]);
    $client = client(['primary_offering_type' => 'event_ticket']);
    KnowledgeItem::query()->forceCreate(['id' => 2, 'workspace_id' => $client->workspace_id, 'client_id' => $client->id,
        'kind' => KnowledgeKind::Fact, 'title' => 'Kelas', 'body' => 'Kelas maksimal 10 peserta.', 'approved_at' => now()]);
    $service = app(ArtifactService::class);
    $user = admin();
    $service->approve($service->createVersion($client, ArtifactType::BrandBrief, example('brand_brief')), $user);
    $service->approve($service->createVersion($client, ArtifactType::VisualRules, example('visual_rules')), $user);
    $model = routeRole('content_strategist');
    routeRole('copywriter', $model);
    routeRole('brand_guardian', $model);

    return $client;
}

/** A plan for 2026-12 with the given keys, valid against the example brief. */
function plan(array $keys): array
{
    return ['month' => '2026-12', 'campaign_ref' => null, 'focus' => 'Uji', 'items' => array_map(fn (string $key, int $i) => [
        'key' => $key, 'date' => sprintf('2026-12-%02d', $i + 1), 'time' => '19:00', 'platform' => 'instagram', 'format' => 'carousel',
        'pillar' => 'process', 'offering_id' => null, 'moment_key' => null, 'angle' => "Angle {$key}", 'cta' => 'Pesan kursimu', 'asset_hint' => null,
    ], $keys, array_keys($keys))];
}

function copyFor(string $key, array $overrides = []): array
{
    $item = example('content_batch')['items'][0];

    return array_replace_recursive($item, ['plan_item_key' => $key], $overrides);
}

function guardianPass(array $keys): array
{
    return ['items' => array_map(fn ($k) => ['plan_item_key' => $k, 'verdict' => 'pass', 'issues' => []], $keys)];
}

it('generates a content plan with moments and the month in the request', function () {
    $client = contentReadyClient();
    fakeLlm()->respond(plan(['dec-01-a', 'dec-02-b']));

    $version = app(ArtifactGeneration::class)->generate($client, ArtifactType::ContentPlan, ['month' => '2026-12', 'posts_per_week' => 2, 'platforms' => ['instagram']]);

    $task = fakeLlm()->requests[0]->task;
    expect($version->artifact->scope_ref)->toBe('2026-12')
        ->and($task)->toContain('Harbolnas 12.12')->toContain('"jumlah_konten":9')
        ->and($task)->not->toContain('tiktok');
});

it('rejects plans with dates outside the month, unknown pillars or unrequested formats', function () {
    $client = contentReadyClient();
    $bad = plan(['dec-01-a']);
    $bad['items'][0]['date'] = '2027-01-03';
    $bad['items'][0]['pillar'] = 'unknown';
    $bad['items'][0]['format'] = 'short_video';
    fakeLlm()->respond($bad)->respond($bad);

    expect(fn () => app(ArtifactGeneration::class)->generate($client, ArtifactType::ContentPlan, ['month' => '2026-12']))
        ->toThrow(OutputInvalid::class, 'bulan 2026-12');
});

it('materializes an approved plan into content items and reconciles a revised plan', function () {
    $client = contentReadyClient();
    $service = app(ArtifactService::class);
    $v1 = $service->createVersion($client, ArtifactType::ContentPlan, plan(['a1', 'b2', 'c3']), '2026-12');
    $service->approve($v1, admin());

    expect(ContentItem::query()->where('status', ContentStatus::Planned)->count())->toBe(3);

    $done = ContentItem::query()->where('plan_item_key', 'b2')->sole();
    $done->update(['status' => ContentStatus::Approved]); // finished work must not be touched

    $v2 = $service->createVersion($client, ArtifactType::ContentPlan, plan(['a1', 'd4']), '2026-12');
    $service->approve($v2, admin());

    $status = ContentItem::query()->pluck('status', 'plan_item_key');
    expect($status['a1'])->toBe(ContentStatus::Planned)
        ->and($status['b2'])->toBe(ContentStatus::Approved)
        ->and($status['c3'])->toBe(ContentStatus::Cancelled)
        ->and($status['d4'])->toBe(ContentStatus::Planned)
        ->and(ContentItem::query()->count())->toBe(4);
});

it('drafts a batch in one call and sends passing drafts to approval', function () {
    $client = contentReadyClient();
    app(ArtifactService::class)->approve(app(ArtifactService::class)->createVersion($client, ArtifactType::ContentPlan, plan(['a1', 'b2']), '2026-12'), admin());
    $items = ContentItem::query()->get();
    fakeLlm()->respond(['items' => [copyFor('a1'), copyFor('b2')]])->respond(guardianPass(['a1', 'b2']));

    $summary = app(ContentDrafting::class)->draft($client, $items, 'admin:1');

    expect($summary)->toBe(['awaiting_approval' => 2, 'guardian_failed' => 0])
        ->and(fakeLlm()->requests)->toHaveCount(2)
        ->and(ContentItem::query()->first()->draft['caption'])->not->toBeEmpty()
        ->and(WorkflowEvent::query()->where('subject_id', $items[0]->id)->pluck('to_status')->all())
        ->toBe(['drafting', 'drafted', 'awaiting_approval']);
});

it('fails drafts on rule violations without spending an LLM review, then revises once automatically', function () {
    $client = contentReadyClient();
    app(ArtifactService::class)->approve(app(ArtifactService::class)->createVersion($client, ArtifactType::ContentPlan, plan(['a1']), '2026-12'), admin());
    $item = ContentItem::query()->sole();
    $banned = copyFor('a1', ['caption' => 'Workshop termurah se-Jogja!']); // "termurah" is a banned word
    fakeLlm()->respond(['items' => [$banned]])          // draft → rule fail (no guardian call)
        ->respond(['items' => [copyFor('a1')]])         // automatic revision
        ->respond(guardianPass(['a1']));                // guardian passes the revision

    app(ContentDrafting::class)->draft($client, collect([$item]), 'admin:1');

    $item->refresh();
    $requests = fakeLlm()->requests;
    expect($item->status)->toBe(ContentStatus::AwaitingApproval)
        ->and($item->auto_revision_count)->toBe(1)
        ->and($item->revision_count)->toBe(0)
        ->and($requests)->toHaveCount(3)
        ->and($requests[1]->task)->toContain('termurah');
});

it('stops after one automatic revision and leaves the item for the admin', function () {
    $client = contentReadyClient();
    app(ArtifactService::class)->approve(app(ArtifactService::class)->createVersion($client, ArtifactType::ContentPlan, plan(['a1']), '2026-12'), admin());
    $item = ContentItem::query()->sole();
    $fail = ['items' => [['plan_item_key' => 'a1', 'verdict' => 'fail', 'issues' => [['type' => 'claim', 'severity' => 'major', 'note' => 'Klaim berlebihan']]]]];
    fakeLlm()->respond(['items' => [copyFor('a1')]])->respond($fail)->respond(['items' => [copyFor('a1')]])->respond($fail);

    $summary = app(ContentDrafting::class)->draft($client, collect([$item]), 'admin:1');

    expect($summary['guardian_failed'])->toBe(1)
        ->and($item->fresh()->guardian['issues'][0]['note'])->toBe('Klaim berlebihan')
        ->and(fakeLlm()->requests)->toHaveCount(4);
});

it('flags facts without references, too-long headlines and short carousels', function () {
    $issues = app(GuardianRules::class)->check(
        ['caption' => 'Harga Rp150.000 per orang', 'hashtags' => [], 'slides' => [['headline' => str_repeat('x', 50)]], 'fact_refs' => []],
        'instagram', 'carousel', example('brand_brief'), example('visual_rules'),
    );

    expect(array_column($issues, 'type'))->toContain('fact', 'length', 'other');
});

it('revises only the commented item and counts repeated human revisions as major', function () {
    $client = contentReadyClient();
    app(ArtifactService::class)->approve(app(ArtifactService::class)->createVersion($client, ArtifactType::ContentPlan, plan(['a1', 'b2']), '2026-12'), admin());
    fakeLlm()->respond(['items' => [copyFor('a1'), copyFor('b2')]])->respond(guardianPass(['a1', 'b2']));
    app(ContentDrafting::class)->draft($client, ContentItem::query()->get(), 'admin:1');
    $item = ContentItem::query()->where('plan_item_key', 'a1')->sole();
    $review = app(ContentReview::class);

    foreach ([1, 2] as $round) {
        $review->requestRevision($item->fresh(), "Ganti hook ronde {$round}", false, 'admin:1');
        fakeLlm()->respond(['items' => [copyFor('a1', ['hook' => "Hook {$round}"])]])->respond(guardianPass(['a1']));
        app(ContentDrafting::class)->draft($client, collect([$item->fresh()]), 'admin:1');
    }

    $requests = fakeLlm()->requests;
    $item->refresh();
    expect($requests[2]->task)->toContain('Ganti hook ronde 1')->not->toContain('"b2"')
        ->and($item->revision_count)->toBe(2)
        ->and($item->major_revision)->toBeTrue()
        ->and(ContentItem::query()->where('plan_item_key', 'b2')->sole()->revision_count)->toBe(0)
        ->and($item->approvals()->count())->toBe(2);
});

it('returns items to their previous status when the copywriter call fails', function () {
    $client = contentReadyClient();
    app(ArtifactService::class)->approve(app(ArtifactService::class)->createVersion($client, ArtifactType::ContentPlan, plan(['a1']), '2026-12'), admin());
    fakeLlm()->respond('nope')->respond('nope');

    expect(fn () => app(ContentDrafting::class)->draft($client, ContentItem::query()->get(), 'admin:1'))->toThrow(OutputInvalid::class);
    expect(ContentItem::query()->sole()->status)->toBe(ContentStatus::Planned);
});

it('records approvals and treats rejection as a major revision', function () {
    $client = contentReadyClient();
    app(ArtifactService::class)->approve(app(ArtifactService::class)->createVersion($client, ArtifactType::ContentPlan, plan(['a1', 'b2']), '2026-12'), admin());
    fakeLlm()->respond(['items' => [copyFor('a1'), copyFor('b2')]])->respond(guardianPass(['a1', 'b2']));
    app(ContentDrafting::class)->draft($client, ContentItem::query()->get(), 'admin:1');
    [$a, $b] = ContentItem::query()->orderBy('plan_item_key')->get();

    app(ContentReview::class)->approve($a, 'admin:1');
    app(ContentReview::class)->reject($b, 'Tidak sesuai', 'admin:1');

    expect($a->fresh()->status)->toBe(ContentStatus::Approved)->and($a->fresh()->major_revision)->toBeFalse()
        ->and($b->fresh()->status)->toBe(ContentStatus::Rejected)->and($b->fresh()->major_revision)->toBeTrue();
});
