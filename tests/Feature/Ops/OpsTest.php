<?php

use App\Console\Commands\DemoSeed;
use App\Enums\ArtifactType;
use App\Enums\ContentStatus;
use App\Enums\PublishStatus;
use App\Models\Client;
use App\Models\ContentItem;
use App\Models\LlmCall;
use App\Models\PublishJob;
use App\Models\Workspace;
use App\Services\Artifacts\ArtifactService;
use App\Services\Ops\BackupService;
use App\Services\Ops\Heartbeat;
use App\Services\Ops\MvpGate;
use App\Services\Ops\PilotReadiness;
use Carbon\CarbonImmutable;
use Database\Seeders\OfferingTypeSeeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => Storage::fake('assets'));

it('creates and purges a clearly marked demo client', function () {
    $this->seed(OfferingTypeSeeder::class);
    admin();

    $this->artisan('demo:seed --with-artifacts')->assertSuccessful();
    $client = Client::query()->where('slug', DemoSeed::SLUG)->sole();
    expect($client->name)->toStartWith('[DEMO]')
        ->and($client->knowledgeItems()->usable()->count())->toBe(5)
        ->and(app(ArtifactService::class)->approved($client, ArtifactType::BrandBrief))->not->toBeNull();

    $this->artisan('demo:seed --purge')->assertSuccessful();
    expect(Client::query()->where('slug', DemoSeed::SLUG)->exists())->toBeFalse();
});

it('reports what blocks the pilot', function () {
    $this->seed(OfferingTypeSeeder::class);
    Http::fake(['gotenberg.invalid*' => Http::response('', 200)]);
    workspace();

    $system = collect(app(PilotReadiness::class)->system())->keyBy('label');
    expect($system['Akun admin']->status)->toBe('fail')
        ->and($system['Bot Telegram']->status)->toBe('fail')
        ->and($system['Renderer (Gotenberg)']->status)->toBe('ok')
        ->and($system['Tunnel media publik']->status)->toBe('warn');

    app(Heartbeat::class)->beat('scheduler');
    expect(collect(app(PilotReadiness::class)->system())->firstWhere('label', 'Scheduler berjalan')->status)->toBe('ok');

    $this->artisan('pilot:check')->assertFailed();
});

it('computes the MVP gate: weeks live, clean approvals, cost and Paperclip savings', function () {
    $this->travelTo(CarbonImmutable::parse('2026-12-31 12:00', 'Asia/Jakarta'));
    $client = client();
    Workspace::current()->update(['settings' => ['paperclip_baseline_usd' => 40]]);
    $make = fn (string $key, ContentStatus $status, bool $major) => ContentItem::query()->create(['workspace_id' => $client->workspace_id,
        'client_id' => $client->id, 'month' => '2026-12', 'plan_item_key' => $key, 'platform' => 'instagram', 'format' => 'story',
        'pillar' => 'p', 'publish_date' => '2026-12-01', 'angle' => 'a', 'cta' => 'c', 'status' => $status, 'major_revision' => $major]);
    $items = [];
    foreach (range(1, 8) as $i) {
        $items[] = $make("ok{$i}", ContentStatus::Published, false);
    }
    $make('major', ContentStatus::Published, true);
    $make('rejected', ContentStatus::Rejected, true);
    $make('pending', ContentStatus::AwaitingApproval, false); // not decided yet
    PublishJob::query()->create(['workspace_id' => $client->workspace_id, 'client_id' => $client->id, 'content_item_id' => $items[0]->id,
        'platform' => 'instagram', 'scheduled_at' => now()->subWeeks(5), 'mode' => 'api', 'status' => PublishStatus::Published, 'published_at' => now()->subWeeks(5)]);
    LlmCall::query()->create(['workspace_id' => $client->workspace_id, 'client_id' => $client->id, 'role_key' => 'copywriter', 'task_ref' => 't',
        'prompt_key' => 'copywriter', 'prompt_version' => 1, 'attempt' => 1, 'cost_usd' => 4, 'status' => 'ok', 'request_hash' => str_repeat('a', 64)]);

    $gate = app(MvpGate::class)->metrics($client);

    expect($gate['weeks'])->toBe(5)->and($gate['decided'])->toBe(10)->and($gate['clean_pct'])->toBe(80.0)
        ->and($gate['llm_cost_month'])->toBe(4.0)->and($gate['savings_pct'])->toBe(90.0)->and($gate['passed'])->toBeTrue();
});

it('writes encrypted backups that only this APP_KEY can read', function () {
    $version = Process::run(['pg_dump', '--version']);
    if (! $version->successful() || ! preg_match('/(\d+)\./', $version->output(), $m) || (int) $m[1] < 17) {
        $this->markTestSkipped('pg_dump 17+ is not available here (runs inside the app container).');
    }

    $service = app(BackupService::class);
    $file = $service->run();

    expect(File::get($file))->toStartWith("AGSB1\n")->not->toContain('CREATE TABLE')
        ->and($service->verify($file))->toBeGreaterThan(10);

    config(['app.key' => 'base64:'.base64_encode(str_repeat('x', 32))]);
    expect(fn () => app(BackupService::class)->verify($file))->toThrow(RuntimeException::class, 'wrong APP_KEY');
    File::delete($file);
});
