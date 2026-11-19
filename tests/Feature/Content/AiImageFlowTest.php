<?php

use App\Enums\AssetKind;
use App\Enums\ContentStatus;
use App\Filament\Resources\ContentItems\Pages\ListContentItems;
use App\Jobs\RenderAndSend;
use App\Models\Asset;
use App\Models\ClientContact;
use App\Models\ContentItem;
use App\Services\Content\AiImageUpload;
use App\Services\Content\ContentReview;
use App\Services\Content\ImagePrompt;
use App\Services\Publish\Publishing;
use App\Services\Render\ContentRenderer;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(fn () => Storage::fake('assets'));

/** An item awaiting approval whose draft asks for an AI image, with one linked approver. */
function aiImageItem(): ContentItem
{
    $item = awaitingItem(['format' => 'feed_image']);
    $draft = $item->draft;
    $draft['visual'] = ['mode' => 'ai_image', 'headline' => 'Pulang bawa karya sendiri', 'image_prompt' => 'Close-up of hands shaping a clay cup on a wooden table'];
    $item->update(['draft' => $draft]);
    ClientContact::query()->create(['workspace_id' => $item->workspace_id, 'client_id' => $item->client_id, 'name' => 'Pemilik', 'telegram_chat_id' => 777, 'linked_at' => now()]);

    return $item->fresh();
}

function storedUpload(ContentItem $item): string
{
    $path = $item->client->ulid.'/ai.png';
    Storage::disk('assets')->put($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII='));

    return $path;
}

it('composes a paste-ready prompt from the draft and the Visual Rules', function () {
    $prompt = app(ImagePrompt::class)->for(aiImageItem());

    expect($prompt)->toContain('Close-up of hands shaping a clay cup')
        ->toContain('Style: Foto natural, cahaya hangat')
        ->toContain('warm natural light, earthy palette')
        ->toContain('Aspect ratio 4:5 (1080×1350 px)')
        ->toContain('text, letters, words')
        ->toContain('hasil karya peserta'); // the brand's "AI must not depict" list
});

it('holds the item back (no render, nothing sent) until the image is uploaded', function () {
    $item = aiImageItem();

    dispatch(new RenderAndSend([$item->id]));

    expect($item->fresh()->render)->toBeNull()->and(fakeTelegram()->sent)->toBeEmpty()->and($item->fresh()->awaitsAiImage())->toBeTrue();
});

it('uses the uploaded image as the background, then renders and sends to the client', function () {
    $this->actingAs(admin());
    $item = aiImageItem();

    Livewire::test(ListContentItems::class)
        ->callTableAction('upload_ai_image', $item, ['image' => UploadedFile::fake()->image('hasil.png', 1080, 1350)])
        ->assertHasNoTableActionErrors();

    $item->refresh();
    $asset = $item->visualAsset;
    expect($asset->kind)->toBe(AssetKind::AiImage)->and($asset->ai_generated)->toBeTrue()
        ->and(fakeRenderer()->renders[0]['files'])->toContain('photo')
        ->and($item->render['assets'])->toHaveCount(1)
        ->and(collect(fakeTelegram()->sent)->where('chat_id', 777)->pluck('type')->all())->toBe(['photos', 'message']);
});

it('makes the render stale when the image is replaced', function () {
    $item = aiImageItem();
    app(AiImageUpload::class)->attach($item, storedUpload($item));
    expect(app(ContentRenderer::class)->isCurrent($item->fresh()))->toBeTrue();

    app(AiImageUpload::class)->attach($item->fresh(), storedUpload($item));

    expect(Asset::query()->where('kind', AssetKind::AiImage)->count())->toBe(2)
        ->and(app(ContentRenderer::class)->isCurrent($item->fresh()))->toBeTrue(); // re-rendered by the dispatched job
});

it('does not accept a new image after approval', function () {
    $item = aiImageItem();
    app(AiImageUpload::class)->attach($item, storedUpload($item));
    app(ContentReview::class)->approve($item->fresh(), 'admin:1');

    app(AiImageUpload::class)->attach($item->fresh(), storedUpload($item));
})->throws(InvalidArgumentException::class);

it('adds no AI label to the package or the phone reminder', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-30 09:00', 'Asia/Jakarta'));
    linkedAdmin(4242);
    fakePublisher()->available = false;
    $item = aiImageItem();
    app(AiImageUpload::class)->attach($item, storedUpload($item));
    app(ContentReview::class)->approve($item->fresh(), 'admin:1');
    $job = app(Publishing::class)->schedule($item->fresh(), null, 'admin:1')['job'];
    $this->travelTo(CarbonImmutable::parse('2026-12-01 19:00', 'Asia/Jakarta'));

    app(Publishing::class)->runDue();

    $zip = new ZipArchive;
    $tmp = tempnam(sys_get_temp_dir(), 'z');
    file_put_contents($tmp, Storage::disk('assets')->get($job->fresh()->package->path));
    $zip->open($tmp);
    $texts = $zip->getFromName('info.txt').$zip->getFromName('caption.txt').implode("\n", fakeTelegram()->textsTo(4242));
    expect($job->fresh()->contentItem->status)->toBe(ContentStatus::Packaged)
        ->and(mb_strtolower($texts))->not->toContain('label')->not->toContain(' ai ')->not->toContain('dibuat ai');
});
