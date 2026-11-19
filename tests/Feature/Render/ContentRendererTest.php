<?php

use App\Enums\AssetKind;
use App\Models\ArtifactVersion;
use App\Models\Asset;
use App\Models\Client;
use App\Services\Artifacts\PrerequisiteMissing;
use App\Services\Assets\AssetLibrary;
use App\Services\Render\ContentRenderer;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => Storage::fake('assets'));

function photo(Client $client, array $attributes = []): Asset
{
    return app(AssetLibrary::class)->storeContents($client, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII='), 'png',
        $attributes + ['kind' => AssetKind::Photo, 'source' => 'client', 'title' => 'Foto tangan membentuk tanah liat', 'tags' => ['tanah liat', 'tangan']]);
}

it('renders a carousel as a cover plus one page per slide and stores the files', function () {
    $item = awaitingItem();

    $assets = app(ContentRenderer::class)->render($item);

    $renders = fakeRenderer()->renders;
    expect($assets)->toHaveCount(3)
        ->and($renders[0]['html'])->toContain('5 langkah cangkir pertamamu')->toContain('#8A4B2A')
        ->and($renders[1]['html'])->toContain('1. Kenali tanahnya')->toContain('Instruktur mendampingi')
        ->and($renders[2]['html'])->toContain('Pesan kursimu')
        ->and([$renders[0]['width'], $renders[0]['height']])->toBe([1080, 1350])
        ->and($item->fresh()->render['assets'])->toBe(array_map(fn (Asset $a) => $a->ulid, $assets))
        ->and(Asset::query()->where('kind', AssetKind::Render)->count())->toBe(3);
    Storage::disk('assets')->assertExists($assets[0]->path);
});

it('knows when the render is stale after the draft changes', function () {
    $item = awaitingItem();
    $renderer = app(ContentRenderer::class);
    $renderer->render($item);
    expect($renderer->isCurrent($item->fresh()))->toBeTrue();

    $item->update(['draft' => ['caption' => 'baru'] + $item->draft]);

    expect($renderer->isCurrent($item->fresh()))->toBeFalse();
});

it('renders a story-size cover for video formats and flags that the clip is pending', function () {
    $item = awaitingItem(['format' => 'reel']);

    app(ContentRenderer::class)->render($item);

    expect(fakeRenderer()->renders[0]['height'])->toBe(1920)->and($item->fresh()->render['needs_video'])->toBeTrue();
});

it('uses the logo and a matching photo, and never a photo of children without consent', function () {
    $item = awaitingItem(['format' => 'feed_image']);
    $client = $item->client;
    app(AssetLibrary::class)->storeContents($client, 'logo-bytes', 'png', ['kind' => AssetKind::Logo, 'source' => 'client', 'title' => 'Logo']);
    photo($client, ['title' => 'Anak-anak membentuk tanah liat', 'has_children' => true, 'consent_status' => 'pending']);
    $ok = photo($client);

    app(ContentRenderer::class)->render($item);

    expect(fakeRenderer()->renders[0]['files'])->toContain('logo', 'photo')
        ->and($ok->usages()->where('role', 'source')->count())->toBe(1);
});

it('needs approved Visual Rules', function () {
    $item = awaitingItem();
    ArtifactVersion::query()->whereHas('artifact', fn ($q) => $q->where('type', 'visual_rules'))->update(['status' => 'superseded']);

    app(ContentRenderer::class)->render($item);
})->throws(PrerequisiteMissing::class);

it('serves asset previews only through signed URLs', function () {
    $item = awaitingItem();
    [$asset] = app(ContentRenderer::class)->render($item);

    $this->get('/assets/'.$asset->ulid)->assertForbidden();
    $this->get($asset->previewUrl())->assertOk()->assertHeader('Content-Type', 'image/png');
});
