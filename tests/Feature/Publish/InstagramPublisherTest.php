<?php

use App\Enums\CredentialProvider;
use App\Enums\PublishStatus;
use App\Models\Asset;
use App\Models\OAuthConnection;
use App\Models\PublishJob;
use App\Services\Credentials\CredentialStore;
use App\Services\Publish\Platforms\InstagramPublisher;
use App\Services\Publish\PublicMediaUrl;
use App\Services\Publish\PublishFailed;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('assets');
    config(['services.publish.public_media_base' => 'https://media.example.test']);
    $this->item = renderedItem();
    app(CredentialStore::class)->save(CredentialProvider::Meta, ['app_id' => '1234567', 'app_secret' => str_repeat('s', 32), 'graph_version' => 'v24.0']);
    OAuthConnection::query()->create(['workspace_id' => $this->item->workspace_id, 'client_id' => $this->item->client_id, 'platform' => 'instagram',
        'account_ref' => '1789', 'access_token' => 'page-token', 'status' => 'active']);
    $this->job = PublishJob::query()->create(['workspace_id' => $this->item->workspace_id, 'client_id' => $this->item->client_id,
        'content_item_id' => $this->item->id, 'platform' => 'instagram', 'scheduled_at' => now(), 'mode' => 'api', 'status' => PublishStatus::Publishing]);
});

it('publishes a carousel through child containers and public signed media URLs', function () {
    Http::fake([
        'graph.facebook.com/v24.0/1789/media_publish' => Http::response(['id' => 'm99']),
        'graph.facebook.com/v24.0/1789/media' => Http::sequence()->push(['id' => 'c1'])->push(['id' => 'c2'])->push(['id' => 'c3'])->push(['id' => 'carousel']),
        'graph.facebook.com/v24.0/carousel*' => Http::response(['id' => 'carousel', 'status_code' => 'FINISHED']),
        'graph.facebook.com/v24.0/m99*' => Http::response(['id' => 'm99', 'permalink' => 'https://www.instagram.com/p/xyz/']),
    ]);
    $publisher = app(InstagramPublisher::class);

    expect($publisher->canPublish($this->job))->toBeTrue();
    $result = $publisher->publish($this->job);

    expect($result)->toBe(['id' => 'm99', 'permalink' => 'https://www.instagram.com/p/xyz/']);
    Http::assertSent(fn ($r) => str_ends_with($r->url(), '1789/media') && ($r['is_carousel_item'] ?? null) === 'true'
        && str_starts_with($r['image_url'], 'https://media.example.test/media/'));
    Http::assertSent(fn ($r) => str_ends_with($r->url(), '1789/media') && ($r['media_type'] ?? null) === 'CAROUSEL' && ($r['children'] ?? null) === 'c1,c2,c3');
});

it('reports Graph errors as codes only', function () {
    Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'secret detail', 'code' => 9004]], 400)]);

    try {
        app(InstagramPublisher::class)->publish($this->job);
        $this->fail('expected failure');
    } catch (PublishFailed $e) {
        expect($e->getMessage())->toBe('container_http_400_9004');
    }
});

it('cannot publish without a public media tunnel', function () {
    config(['services.publish.public_media_base' => null]);

    expect(app(InstagramPublisher::class)->canPublish($this->job))->toBeFalse();
});

it('serves media through the tunnel with a relative signature', function () {
    $asset = Asset::query()->where('ulid', $this->item->render['assets'][0])->sole();
    $url = app(PublicMediaUrl::class)->for($asset);
    $path = substr($url, strlen('https://media.example.test'));

    $this->get($path)->assertOk()->assertHeader('Content-Type', 'image/png');
    $this->get('/media/'.$asset->ulid)->assertForbidden();
});
