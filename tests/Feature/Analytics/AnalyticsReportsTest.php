<?php

use App\Enums\ArtifactStatus;
use App\Jobs\GenerateWeeklyReport;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\KpiGoal;
use App\Models\OAuthConnection;
use App\Models\Report;
use App\Services\Analytics\KpiSnapshotBuilder;
use App\Services\Analytics\MetricsPuller;
use App\Services\Analytics\Sources\Ga4Reports;
use App\Services\Reports\WeeklyReports;
use Carbon\CarbonImmutable;
use Database\Seeders\OfferingTypeSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('assets');
    $this->seed(OfferingTypeSeeder::class);
    $this->travelTo(CarbonImmutable::parse('2026-12-08 09:00', 'Asia/Jakarta')); // Tuesday
    $this->client = client(['primary_offering_type' => 'event_ticket']);
});

function metric(int $clientId, string $date, string $key, float $value, string $source = 'ga4', string $type = 'event'): void
{
    DB::table('metrics_daily')->insert(['client_id' => $clientId, 'source' => $source, 'entity_type' => $type, 'entity_ref' => $key,
        'date' => $date, 'metric_key' => $key, 'value' => $value, 'pulled_at' => now()]);
}

function connectGa4(Client $client): void
{
    OAuthConnection::query()->create(['workspace_id' => $client->workspace_id, 'client_id' => $client->id, 'platform' => 'ga4',
        'account_ref' => '123456789', 'access_token' => 'a', 'refresh_token' => 'r', 'expires_at' => now()->addHour(), 'status' => 'active']);
}

it('pulls GA4 traffic and conversion events, catching up from the last complete day', function () {
    connectGa4($this->client);
    Http::fake(['analyticsdata.googleapis.com/*' => Http::sequence()
        ->push(['rows' => [['dimensionValues' => [['value' => '20261206']], 'metricValues' => [['value' => '40'], ['value' => '31']]]]])
        ->push(['rows' => [['dimensionValues' => [['value' => '20261206'], ['value' => 'ticket_booking']], 'metricValues' => [['value' => '3']]]]])]);
    DB::table('metric_pulls')->insert(['client_id' => $this->client->id, 'source' => 'ga4', 'last_complete_date' => '2026-12-03', 'created_at' => now(), 'updated_at' => now()]);

    expect(app(MetricsPuller::class)->pullSource($this->client, app(Ga4Reports::class)))->toBe('ok:3');

    Http::assertSent(fn ($r) => $r['dateRanges'][0] === ['startDate' => '2026-12-04', 'endDate' => '2026-12-07']
        || in_array('ticket_booking', $r['dimensionFilter']['filter']['inListFilter']['values'] ?? [], true));
    expect(DB::table('metrics_daily')->where('metric_key', 'ticket_booking')->value('value'))->toEqual(3)
        ->and(DB::table('metric_pulls')->value('last_complete_date'))->toBe('2026-12-07');
});

it('computes the KPI snapshot with code: sums, change, pro-rata targets and data quality', function () {
    connectGa4($this->client);
    KpiGoal::query()->create(['workspace_id' => $this->client->workspace_id, 'client_id' => $this->client->id, 'metric_key' => 'ticket_booking',
        'label' => 'Booking tiket', 'target_value' => 120, 'period' => 'month', 'priority' => 1]);
    foreach (['2026-12-01' => 5, '2026-12-03' => 7] as $date => $v) {
        metric($this->client->id, $date, 'ticket_booking', $v);
    }
    metric($this->client->id, '2026-11-25', 'ticket_booking', 8);

    $version = app(KpiSnapshotBuilder::class)->build($this->client, CarbonImmutable::parse('2026-11-30'), CarbonImmutable::parse('2026-12-06'), '2026-W49');

    $row = $version->payload['rows'][0];
    expect($version->status)->toBe(ArtifactStatus::Approved)
        ->and($row)->toMatchArray(['metric_key' => 'ticket_booking', 'value' => 12.0, 'previous' => 8.0, 'change_pct' => 50.0, 'target' => 28.0, 'is_primary_goal' => true])
        ->and($version->payload['data_quality'])->toContain('Data ga4 hanya ada untuk 2 dari 7 hari.');
});

it('writes, renders and sends the weekly report; numbers stay in the table, words come from the writer', function () {
    connectGa4($this->client);
    Http::fake(['analyticsdata.googleapis.com/*' => Http::response(['rows' => []])]);
    metric($this->client->id, '2026-12-02', 'ticket_booking', 9);
    ClientContact::query()->create(['workspace_id' => $this->client->workspace_id, 'client_id' => $this->client->id, 'name' => 'Owner', 'telegram_chat_id' => 777, 'linked_at' => now()]);
    linkedAdmin(4242);
    routeRole('report_writer');

    $reports = app(WeeklyReports::class);
    $report = $reports->generate($this->client, $reports->lastWeekStart($this->client));

    expect($report->status)->toBe('sent')->and($report->period_start->toDateString())->toBe('2026-11-30')
        ->and($report->narrative['headline'])->toBe('Booking naik, jangkauan Instagram stabil')
        ->and(fakeRenderer()->pdfs[0])->toContain('Booking naik')->toContain('<td class="n">9</td>')
        ->and(collect(fakeTelegram()->sent)->where('chat_id', 777)->pluck('type')->all())->toBe(['message', 'document'])
        ->and(fakeTelegram()->textsTo(4242))->not->toBeEmpty()
        ->and($reports->exists($this->client, $reports->lastWeekStart($this->client)))->toBeTrue();
});

it('still sends the numbers when the writer is unavailable', function () {
    connectGa4($this->client);
    Http::fake(['analyticsdata.googleapis.com/*' => Http::response(['rows' => []])]);

    $report = app(WeeklyReports::class)->generate($this->client, CarbonImmutable::parse('2026-11-30'), send: false);

    expect($report->narrative)->toBeNull()->and($report->status)->toBe('ready')
        ->and(fakeRenderer()->pdfs[0])->toContain('Komentar otomatis tidak tersedia');
});

it('queues reports only for clients with a connected source, once per week', function () {
    Queue::fake();
    $other = client();
    connectGa4($this->client);

    $this->artisan('reports:weekly')->assertSuccessful();
    $this->artisan('reports:weekly')->assertSuccessful();

    Queue::assertPushed(GenerateWeeklyReport::class, 1);
    expect(Report::query()->count())->toBe(0);
});
