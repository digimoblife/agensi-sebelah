<?php

namespace App\Services\Publish\Platforms;

use App\Models\Asset;
use App\Models\ContentItem;
use App\Models\OAuthConnection;
use App\Models\PublishJob;
use App\Services\Connections\MetaConnection;
use App\Services\Publish\PublicMediaUrl;
use App\Services\Publish\Publisher;
use App\Services\Publish\PublishFailed;
use App\Services\Publish\QaPublisher;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;

/**
 * F4.2 Instagram Content Publishing API: create media container(s) from public image URLs, wait until ready,
 * publish. Feed image, carousel (2–10) and story. Reels need the video (phase 2) and go out as packages.
 */
class InstagramPublisher implements Publisher
{
    public const FORMATS = ['feed_image', 'carousel', 'story'];

    private const READY_POLLS = 6;

    public function __construct(private readonly MetaConnection $meta, private readonly PublicMediaUrl $media, private readonly QaPublisher $qa) {}

    public function canPublish(PublishJob $job): bool
    {
        $item = $job->contentItem;

        return $item !== null && $job->platform === 'instagram' && in_array($item->format, self::FORMATS, true)
            && $this->media->available() && ($this->connection($item)?->isActive() ?? false);
    }

    public function publish(PublishJob $job): array
    {
        /** @var ContentItem $item */
        $item = $job->contentItem;
        $connection = $this->connection($item) ?? throw new PublishFailed('not_connected');
        $ig = (string) $connection->account_ref;
        $token = (string) $connection->access_token;
        $urls = Asset::query()->whereIn('ulid', $item->render['assets'] ?? [])->get()
            ->sortBy(fn (Asset $a) => array_search($a->ulid, $item->render['assets'] ?? [], true))
            ->map(fn (Asset $a) => $this->media->for($a))->values()->all();
        if ($urls === []) {
            throw new PublishFailed('no_media');
        }
        $caption = $this->qa->caption($item);

        $container = match ($item->format) {
            'story' => $this->create($ig, $token, ['media_type' => 'STORIES', 'image_url' => $urls[0]]),
            'carousel' => $this->create($ig, $token, [
                'media_type' => 'CAROUSEL',
                'caption' => $caption,
                'children' => implode(',', array_map(fn (string $url) => $this->create($ig, $token, ['image_url' => $url, 'is_carousel_item' => 'true']), array_slice($urls, 0, 10))),
            ]),
            default => $this->create($ig, $token, ['image_url' => $urls[0], 'caption' => $caption]),
        };

        $this->waitUntilReady($container, $token);
        $published = $this->ok($this->graph()->asForm()->post("{$ig}/media_publish", ['creation_id' => $container, 'access_token' => $token]), 'publish');
        $id = (string) $published->json('id');
        $permalink = $this->graph()->get($id, ['fields' => 'permalink', 'access_token' => $token])->json('permalink');

        return ['id' => $id, 'permalink' => is_string($permalink) ? $permalink : null];
    }

    /** @param  array<string, string>  $params */
    private function create(string $ig, string $token, array $params): string
    {
        $response = $this->ok($this->graph()->asForm()->post("{$ig}/media", $params + ['access_token' => $token]), 'container');

        return (string) $response->json('id');
    }

    private function waitUntilReady(string $container, string $token): void
    {
        for ($i = 0; $i < self::READY_POLLS; $i++) {
            $status = $this->graph()->get($container, ['fields' => 'status_code', 'access_token' => $token])->json('status_code');
            if ($status === 'FINISHED' || $status === null) {
                return;
            }
            if ($status === 'ERROR' || $status === 'EXPIRED') {
                throw new PublishFailed('container_'.strtolower((string) $status));
            }
            sleep(min(2 * ($i + 1), 8));
        }

        throw new PublishFailed('container_timeout');
    }

    private function ok(Response $response, string $step): Response
    {
        if (! $response->successful() || $response->json('id') === null) {
            $code = $response->json('error.code');
            throw new PublishFailed("{$step}_http_{$response->status()}".(is_numeric($code) ? "_{$code}" : ''));
        }

        return $response;
    }

    private function graph(): PendingRequest
    {
        return $this->meta->graph();
    }

    private function connection(ContentItem $item): ?OAuthConnection
    {
        return OAuthConnection::query()->where(['client_id' => $item->client_id, 'platform' => 'instagram'])->first();
    }
}
