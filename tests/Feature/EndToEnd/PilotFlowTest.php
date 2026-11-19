<?php

use App\Console\Commands\DemoSeed;
use App\Enums\ArtifactType;
use App\Enums\ContentStatus;
use App\Enums\PublishStatus;
use App\Jobs\DraftContent;
use App\Models\ApprovalRequest;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\ContentItem;
use App\Models\OAuthConnection;
use App\Models\PublishJob;
use App\Services\Artifacts\ArtifactGeneration;
use App\Services\Artifacts\ArtifactService;
use App\Services\Ops\MvpGate;
use App\Services\Publish\Publishing;
use App\Services\Reports\WeeklyReports;
use App\Services\Telegram\UpdateHandler;
use Carbon\CarbonImmutable;
use Database\Seeders\MomentCalendarSeeder;
use Database\Seeders\OfferingTypeSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * The whole MVP loop with every external service faked: plan → copy → Guardian → render → client approval on
 * Telegram → schedule → publish → metrics → weekly report → MVP gate numbers.
 */
it('runs the full pilot loop for one content item', function () {
    Storage::fake('assets');
    $this->seed([OfferingTypeSeeder::class, MomentCalendarSeeder::class]);
    $this->travelTo(CarbonImmutable::parse('2026-11-25 09:00', 'Asia/Jakarta'));
    $admin = linkedAdmin(4242);
    $this->artisan('demo:seed --with-artifacts')->assertSuccessful();
    $client = Client::query()->where('slug', DemoSeed::SLUG)->sole();
    $model = routeRole('content_strategist');
    foreach (['copywriter', 'brand_guardian', 'report_writer'] as $role) {
        routeRole($role, $model);
    }
    ClientContact::query()->create(['workspace_id' => $client->workspace_id, 'client_id' => $client->id, 'name' => 'Pemilik', 'telegram_chat_id' => 777, 'linked_at' => now()]);
    $offering = $client->offerings()->sole();
    $kb = $client->knowledgeItems()->orderBy('id')->pluck('id')->all();

    // 1. Content plan for December, approved → content items.
    fakeLlm()->respond(['month' => '2026-12', 'campaign_ref' => null, 'focus' => 'Isi kursi Desember', 'items' => [[
        'key' => 'dec-05-steps', 'date' => '2026-12-05', 'time' => '19:00', 'platform' => 'instagram', 'format' => 'carousel',
        'pillar' => 'process', 'offering_id' => $offering->id, 'moment_key' => null, 'angle' => 'Lima langkah cangkir pertama',
        'cta' => 'Pesan kursimu', 'asset_hint' => 'tangan tanah liat',
    ]]]);
    $plan = app(ArtifactGeneration::class)->generate($client, ArtifactType::ContentPlan, ['month' => '2026-12', 'posts_per_week' => 1]);
    app(ArtifactService::class)->approve($plan, $admin);
    $item = ContentItem::query()->where('client_id', $client->id)->sole();

    // 2. Copy (batch) → Guardian → render → sent to the client on Telegram.
    $copy = json_decode(file_get_contents(resource_path('schemas/examples/content_batch.v1.json')), true)['items'][0];
    $copy['plan_item_key'] = 'dec-05-steps';
    $copy['caption'] = 'Tiga jam, satu meja, dan tanganmu sendiri. Kelas maksimal 10 peserta.';
    $copy['fact_refs'] = ['kb:'.$kb[2]];
    fakeLlm()->respond(['items' => [$copy]])->respond(['items' => [['plan_item_key' => 'dec-05-steps', 'verdict' => 'pass', 'issues' => []]]]);
    DraftContent::request($client, [$item->id], $admin, 'admin:'.$admin->id);

    $item->refresh();
    expect($item->status)->toBe(ContentStatus::AwaitingApproval)->and($item->render['assets'])->toHaveCount(3)
        ->and(collect(fakeTelegram()->sent)->where('chat_id', 777)->pluck('type')->all())->toBe(['photos', 'message']);

    // 3. Client approves on Telegram → visual is final.
    $token = ApprovalRequest::query()->sole()->token;
    app(UpdateHandler::class)->handle(['update_id' => 1, 'callback_query' => ['id' => 'c1', 'data' => "a:{$token}", 'message' => ['chat' => ['id' => 777]]]]);
    expect($item->fresh()->status)->toBe(ContentStatus::Rendered);

    // 4. Schedule and publish at 19:00 on the 5th.
    $job = app(Publishing::class)->schedule($item->fresh(), null, 'admin:'.$admin->id)['job'];
    $this->travelTo(CarbonImmutable::parse('2026-12-05 19:00', 'Asia/Jakarta'));
    app(Publishing::class)->runDue();
    expect($job->fresh()->status)->toBe(PublishStatus::Published)->and($item->fresh()->status)->toBe(ContentStatus::Published);

    // 5. Metrics + weekly report for the week of the post.
    $this->travelTo(CarbonImmutable::parse('2026-12-08 08:30', 'Asia/Jakarta'));
    OAuthConnection::query()->create(['workspace_id' => $client->workspace_id, 'client_id' => $client->id, 'platform' => 'ga4',
        'account_ref' => '123456789', 'access_token' => 'a', 'refresh_token' => 'r', 'expires_at' => now()->addHour(), 'status' => 'active']);
    Http::fake(['analyticsdata.googleapis.com/*' => Http::sequence()
        ->push(['rows' => [['dimensionValues' => [['value' => '20261205']], 'metricValues' => [['value' => '120'], ['value' => '90']]]]])
        ->push(['rows' => [['dimensionValues' => [['value' => '20261205'], ['value' => 'ticket_booking']], 'metricValues' => [['value' => '6']]]]])]);
    fakeLlm()->respond(['headline' => 'Booking pertama dari konten carousel', 'summary' => 'Minggu pertama pilot berjalan; booking tercatat dari data GA4.',
        'highlights' => ['Konten carousel terbit tepat waktu.'], 'concerns' => [], 'next_steps' => ['Lanjutkan konten proses.'],
        'metric_notes' => [['metric_key' => 'ticket_booking', 'note' => 'Booking tercatat.']]]);
    $report = app(WeeklyReports::class)->generate($client, app(WeeklyReports::class)->lastWeekStart($client));

    expect($report->status)->toBe('sent')
        ->and(DB::table('metrics_daily')->where(['client_id' => $client->id, 'metric_key' => 'ticket_booking'])->value('value'))->toEqual(6)
        ->and(fakeRenderer()->pdfs[0])->toContain('Booking pertama dari konten carousel')
        ->and(collect(fakeTelegram()->sent)->where('chat_id', 777)->pluck('type')->all())->toContain('document');

    // 6. MVP gate numbers.
    $gate = app(MvpGate::class)->metrics($client);
    expect($gate['decided'])->toBe(1)->and($gate['clean_pct'])->toBe(100.0)->and($gate['calls_month'])->toBeGreaterThan(0)
        ->and($gate['live_month'])->toBe(1)->and($gate['passed'])->toBeFalse(); // only 0 weeks live so far
    expect(PublishJob::query()->sole()->permalink)->not->toBeNull();
});
