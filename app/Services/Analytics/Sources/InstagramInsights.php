<?php

namespace App\Services\Analytics\Sources;

use App\Enums\PublishStatus;
use App\Models\Client;
use App\Models\OAuthConnection;
use App\Models\PublishJob;
use App\Services\Connections\MetaConnection;
use Carbon\CarbonImmutable;

/**
 * F7.3 (MVP): Instagram account insights per day + lifetime insights of posts published through the API.
 * Each metric is requested separately so one metric Meta changes or retires does not stop the others.
 */
class InstagramInsights implements MetricSource
{
    public const ACCOUNT_METRICS = ['reach', 'follower_count'];

    public const POST_METRICS = ['reach', 'saved', 'likes', 'comments', 'shares'];

    public function __construct(private readonly MetaConnection $meta) {}

    public function key(): string
    {
        return 'instagram';
    }

    public function isConnected(Client $client): bool
    {
        return $this->connection($client)?->isActive() ?? false;
    }

    public function pull(Client $client, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $connection = $this->connection($client);
        if ($connection === null || ! $connection->isActive()) {
            return [];
        }
        $ig = (string) $connection->account_ref;
        $token = (string) $connection->access_token;
        $rows = [];

        foreach (self::ACCOUNT_METRICS as $metric) {
            $response = $this->meta->graph()->get("{$ig}/insights", [
                'metric' => $metric, 'period' => 'day', 'access_token' => $token,
                'since' => $from->startOfDay()->timestamp, 'until' => $to->addDay()->startOfDay()->timestamp,
            ]);
            foreach ((array) $response->json('data.0.values', []) as $point) {
                if (! is_array($point) || ! is_numeric($point['value'] ?? null) || ! isset($point['end_time'])) {
                    continue;
                }
                // end_time is the end of the day the value belongs to.
                $date = CarbonImmutable::parse((string) $point['end_time'])->setTimezone($client->timezone)->subDay()->toDateString();
                $rows[] = ['entity_type' => 'account', 'entity_ref' => $ig, 'date' => $date, 'metric_key' => "ig_{$metric}", 'value' => (float) $point['value']];
            }
        }

        $today = CarbonImmutable::now($client->timezone)->toDateString();
        $posts = PublishJob::query()->where(['client_id' => $client->id, 'platform' => 'instagram', 'status' => PublishStatus::Published])
            ->whereNotNull('external_post_id')->where('published_at', '>=', now()->subDays(30))->get();
        foreach ($posts as $post) {
            foreach (self::POST_METRICS as $metric) {
                $value = $this->meta->graph()->get((string) $post->external_post_id.'/insights', ['metric' => $metric, 'access_token' => $token])
                    ->json('data.0.values.0.value');
                if (is_numeric($value)) {
                    $rows[] = ['entity_type' => 'post', 'entity_ref' => (string) $post->external_post_id, 'date' => $today,
                        'metric_key' => "ig_post_{$metric}", 'value' => (float) $value, 'content_item_id' => $post->content_item_id];
                }
            }
        }

        return $rows;
    }

    private function connection(Client $client): ?OAuthConnection
    {
        return OAuthConnection::query()->where(['client_id' => $client->id, 'platform' => 'instagram'])->first();
    }
}
