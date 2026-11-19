<?php

use App\Enums\ContentStatus;
use App\Enums\PublishStatus;
use App\Models\PublishJob;
use App\Services\Publish\Publishing;
use App\Services\Telegram\UpdateHandler;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('assets');
    $this->travelTo(CarbonImmutable::parse('2026-11-30 09:00', 'Asia/Jakarta'));
});

it('schedules a rendered item at its planned local time, automatic when the publisher can post', function () {
    $item = renderedItem();

    $result = app(Publishing::class)->schedule($item, null, 'admin:1');

    expect($result['job']->scheduled_at->toIso8601String())->toBe('2026-12-01T12:00:00+00:00')
        ->and($result['job']->mode)->toBe('api')
        ->and($item->fresh()->status)->toBe(ContentStatus::Scheduled);
});

it('falls back to a manual package when automatic posting is not possible', function () {
    fakePublisher()->available = false;

    $job = app(Publishing::class)->schedule(renderedItem(), null, 'admin:1')['job'];

    expect($job->mode)->toBe('manual_package');
});

it('blocks scheduling in the past and close to another post on the same platform', function () {
    $publishing = app(Publishing::class);
    $first = renderedItem();
    $second = $first->replicate(['ulid']);
    $second->plan_item_key = 'dec-01-b';
    $second->save();
    $publishing->schedule($first, null, 'admin:1');

    $close = $publishing->schedule($second, CarbonImmutable::parse('2026-12-01 19:30', 'Asia/Jakarta'), 'admin:1');
    $past = $publishing->schedule($second, CarbonImmutable::parse('2026-11-01 10:00', 'Asia/Jakarta'), 'admin:1');

    expect($close['job'])->toBeNull()->and($close['issues'][0]['note'])->toContain('berdekatan')
        ->and($past['issues'][0]['note'])->toContain('sudah lewat');
});

it('publishes due posts once and records the link', function () {
    $job = app(Publishing::class)->schedule(renderedItem(), null, 'admin:1')['job'];
    $this->travelTo(CarbonImmutable::parse('2026-12-01 19:01', 'Asia/Jakarta'));

    expect(app(Publishing::class)->runDue())->toBe(1);

    $job->refresh();
    expect($job->status)->toBe(PublishStatus::Published)->and($job->permalink)->toContain('instagram.example')
        ->and($job->contentItem->status)->toBe(ContentStatus::Published)
        ->and(app(Publishing::class)->runDue())->toBe(0);
});

it('alerts the admin on a failed publish and never retries by itself', function () {
    linkedAdmin();
    fakePublisher()->failWith = 'container_http_400_9004';
    $job = app(Publishing::class)->schedule(renderedItem(), null, 'admin:1')['job'];
    $this->travelTo(CarbonImmutable::parse('2026-12-01 19:01', 'Asia/Jakarta'));

    app(Publishing::class)->runDue();
    app(Publishing::class)->runDue();

    expect($job->fresh()->status)->toBe(PublishStatus::Failed)
        ->and($job->fresh()->contentItem->status)->toBe(ContentStatus::PublishFailed)
        ->and(implode("\n", fakeTelegram()->textsTo(4242)))->toContain('Gagal terbit')->toContain('9004')
        ->and(fakePublisher()->published)->toBe([]);
});

it('turns a slot missed while the Mac was off into a package and a phone reminder, not a late post', function () {
    linkedAdmin();
    $job = app(Publishing::class)->schedule(renderedItem(), null, 'admin:1')['job'];
    $this->travelTo(CarbonImmutable::parse('2026-12-01 21:00', 'Asia/Jakarta')); // 2 hours late

    app(Publishing::class)->runDue();

    $job->refresh();
    $texts = implode("\n", fakeTelegram()->textsTo(4242));
    expect($job->status)->toBe(PublishStatus::Missed)->and($job->package_asset_id)->not->toBeNull()
        ->and(fakePublisher()->published)->toBe([])
        ->and($texts)->toContain('Jadwal terlewat')->toContain('Tiga jam, satu meja');

    $zip = new ZipArchive;
    $path = tempnam(sys_get_temp_dir(), 'z');
    file_put_contents($path, Storage::disk('assets')->get($job->package->path));
    $zip->open($path);
    expect($zip->locateName('caption.txt'))->not->toBeFalse()->and($zip->locateName('03.png'))->not->toBeFalse();
});

it('marks a package as uploaded from the reminder button', function () {
    linkedAdmin();
    fakePublisher()->available = false;
    $job = app(Publishing::class)->schedule(renderedItem(), null, 'admin:1')['job'];
    $this->travelTo(CarbonImmutable::parse('2026-12-01 19:00', 'Asia/Jakarta'));
    app(Publishing::class)->runDue();
    $token = $job->fresh()->reminder_token;

    $outcome = app(UpdateHandler::class)->handle(['update_id' => 900, 'callback_query' => ['id' => 'c', 'data' => "u:{$token}", 'message' => ['chat' => ['id' => 4242]]]]);
    $stranger = app(UpdateHandler::class)->handle(['update_id' => 901, 'callback_query' => ['id' => 'd', 'data' => "u:{$token}", 'message' => ['chat' => ['id' => 1]]]]);

    expect($outcome)->toBe('uploaded')->and($stranger)->toBe('not_allowed')
        ->and($job->fresh()->status)->toBe(PublishStatus::ManuallyUploaded)
        ->and($job->fresh()->contentItem->status)->toBe(ContentStatus::ManuallyUploaded);
});

it('runs from the scheduler command', function () {
    app(Publishing::class)->schedule(renderedItem(), null, 'admin:1');
    $this->travelTo(CarbonImmutable::parse('2026-12-01 19:05', 'Asia/Jakarta'));

    $this->artisan('publish:due')->assertSuccessful();

    expect(PublishJob::query()->sole()->status)->toBe(PublishStatus::Published);
});
