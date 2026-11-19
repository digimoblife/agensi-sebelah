<?php

use App\Enums\ArtifactStatus;
use App\Enums\ArtifactType;
use App\Enums\JobRunStatus;
use App\Enums\KnowledgeKind;
use App\Enums\ResearchInputKind;
use App\Jobs\GenerateArtifact;
use App\Models\Client;
use App\Models\JobRun;
use App\Models\KnowledgeItem;
use App\Services\Artifacts\ArtifactGeneration;
use App\Services\Artifacts\ArtifactService;
use App\Services\Artifacts\PrerequisiteMissing;
use App\Services\Knowledge\KnowledgeBase;
use App\Services\Llm\Exceptions\OutputInvalid;
use Database\Seeders\OfferingTypeSeeder;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->seed(OfferingTypeSeeder::class);
    $this->client = client([
        'primary_offering_type' => 'event_ticket',
        'onboarding' => ['business_description' => 'Workshop kerajinan akhir pekan.', 'account_access' => ['instagram' => 'granted']],
    ]);
});

function fact(Client $client, array $attributes = []): KnowledgeItem
{
    return KnowledgeItem::query()->forceCreate($attributes + [
        'workspace_id' => $client->workspace_id, 'client_id' => $client->id, 'kind' => KnowledgeKind::Fact,
        'title' => 'Ukuran kelas', 'body' => 'Kelas maksimal 10 peserta.', 'approved_at' => now(),
    ]);
}

it('gives roles only approved, unexpired facts', function () {
    $ok = fact($this->client);
    fact($this->client, ['title' => 'Draf', 'approved_at' => null]);
    fact($this->client, ['title' => 'Promo lama', 'valid_until' => now()->subDay()]);

    $facts = app(KnowledgeBase::class)->facts($this->client);

    expect($facts)->toHaveCount(1)->and($facts[0])->toBe(['id' => $ok->id, 'text' => 'Fakta · Ukuran kelas: Kelas maksimal 10 peserta.']);
});

it('requires onboarding answers and an approved fact before a Brand Brief', function () {
    $client = client(['onboarding' => []]);
    expect(fn () => app(ArtifactGeneration::class)->generate($client, ArtifactType::BrandBrief))
        ->toThrow(PrerequisiteMissing::class, 'deskripsi bisnis');

    expect(fn () => app(ArtifactGeneration::class)->generate($this->client, ArtifactType::BrandBrief))
        ->toThrow(PrerequisiteMissing::class, 'knowledge base');
});

it('generates a Brand Brief draft from onboarding, the offering profile and facts', function () {
    fact($this->client, ['id' => 1, 'title' => 'Format', 'body' => 'Workshop akhir pekan.']);
    fact($this->client, ['id' => 2]);
    routeRole('brand_strategist');

    $version = app(ArtifactGeneration::class)->generate($this->client, ArtifactType::BrandBrief);

    $request = fakeLlm()->requests[0];
    expect($version->status)->toBe(ArtifactStatus::Draft)
        ->and($version->produced_by_llm_call_id)->not->toBeNull()
        ->and($version->payload['meta']['client_id'])->toBe($this->client->id)
        ->and($request->task)->toContain('Tiket / event / workshop')->toContain('Workshop kerajinan')
        ->and($request->task)->not->toContain('account_access')
        ->and($request->cachedContext)->toContain('[kb:2]');
});

it('needs an approved Brand Brief for Visual Rules and never lets the model pick the logo', function () {
    routeRole('art_director');
    expect(fn () => app(ArtifactGeneration::class)->generate($this->client, ArtifactType::VisualRules))
        ->toThrow(PrerequisiteMissing::class, 'Brand Brief');

    $brief = json_decode(file_get_contents(resource_path('schemas/examples/brand_brief.v1.json')), true);
    $service = app(ArtifactService::class);
    $service->approve($service->createVersion($this->client, ArtifactType::BrandBrief, $brief), admin());

    $version = app(ArtifactGeneration::class)->generate($this->client, ArtifactType::VisualRules);

    expect($version->payload['logo']['asset_id'])->toBe('pending')
        ->and(fakeLlm()->requests[0]->cachedContext)->toContain('## Brand Brief');
});

it('builds a Research Pack whose sources and volumes come from the backend', function () {
    foreach ([
        ['kind' => ResearchInputKind::Website, 'ref' => 'https://example.com/a', 'note' => 'Kompetitor fokus paket korporat.'],
        ['kind' => ResearchInputKind::Serp, 'ref' => 'Workshop Kerajinan Jogja', 'note' => 'Hasil teratas didominasi marketplace.', 'keyword_volume' => 1300],
    ] as $input) {
        $this->client->researchInputs()->create($input + ['workspace_id' => $this->client->workspace_id]);
    }
    routeRole('researcher');
    fakeLlm()->respond([
        'period' => ['from' => '2000-01-01', 'to' => '2000-01-31'],
        'sources' => [['id' => 's9', 'kind' => 'website', 'ref' => 'https://made-up.example']],
        'competitors' => [['name' => 'A', 'observations' => ['Fokus korporat'], 'source_ids' => ['s1']]],
        'trends' => [],
        'keywords' => [['term' => 'workshop kerajinan jogja', 'intent' => 'local', 'volume' => 99999], ['term' => 'kelas keramik', 'intent' => 'commercial', 'volume' => 500]],
        'opportunities' => ['Konten proses untuk pemula'],
    ]);

    $payload = app(ArtifactGeneration::class)->generate($this->client, ArtifactType::ResearchPack)->payload;

    expect(array_column($payload['sources'], 'id'))->toBe(['s1', 's2'])
        ->and($payload['period']['from'])->not->toBe('2000-01-01')
        ->and($payload['keywords'][0]['volume'])->toBe(1300)
        ->and($payload['keywords'][1]['volume'])->toBeNull();
});

it('rejects research output that cites unknown sources', function () {
    $this->client->researchInputs()->create(['workspace_id' => $this->client->workspace_id, 'kind' => ResearchInputKind::ManualNote, 'ref' => 'catatan', 'note' => 'x']);
    routeRole('researcher');
    $bad = ['period' => ['from' => '2026-11-01', 'to' => '2026-11-19'], 'sources' => [], 'trends' => [], 'keywords' => [], 'opportunities' => ['x'],
        'competitors' => [['name' => 'A', 'observations' => ['y'], 'source_ids' => ['s7']]]];
    fakeLlm()->respond($bad)->respond($bad);

    app(ArtifactGeneration::class)->generate($this->client, ArtifactType::ResearchPack);
})->throws(OutputInvalid::class, 's7');

it('queues a generation once and records the outcome in job_runs', function () {
    Queue::fake();
    $user = admin();

    expect(GenerateArtifact::request($this->client, ArtifactType::BrandBrief, $user))->toBeTrue()
        ->and(GenerateArtifact::request($this->client, ArtifactType::BrandBrief, $user))->toBeFalse();

    Queue::assertPushedOn('llm', GenerateArtifact::class);
    expect(JobRun::query()->sole()->status)->toBe(JobRunStatus::Queued);
});

it('marks missing prerequisites as failed and notifies the admin', function () {
    $user = admin();
    GenerateArtifact::request($this->client, ArtifactType::BrandBrief, $user); // sync queue in tests

    $run = JobRun::query()->sole();
    expect($run->status)->toBe(JobRunStatus::Failed)->and($run->error_code)->toBe('prerequisite_missing')
        ->and($user->notifications()->count())->toBe(1)
        ->and($user->notifications()->first()->data['title'])->toContain('belum bisa dibuat');
});

it('records success with a pointer to the new version', function () {
    fact($this->client, ['id' => 1]);
    fact($this->client, ['id' => 2]);
    routeRole('brand_strategist');
    $user = admin();

    GenerateArtifact::request($this->client, ArtifactType::BrandBrief, $user);

    $run = JobRun::query()->sole();
    expect($run->status)->toBe(JobRunStatus::Succeeded)->and($run->result_ref)->toStartWith('artifact_version:')
        ->and($run->attempts)->toBe(1);
});
