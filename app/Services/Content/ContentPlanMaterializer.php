<?php

namespace App\Services\Content;

use App\Enums\ArtifactType;
use App\Enums\ContentStatus;
use App\Models\ArtifactVersion;
use App\Models\Client;
use App\Models\ContentItem;
use App\Services\Artifacts\ArtifactApproved;
use App\Services\Workflow\ContentWorkflow;
use Illuminate\Support\Facades\DB;

/**
 * Approved Content Plan → content_items (docs/WORKFLOWS.md §Content Plan). Keyed by client + month + plan item
 * key, so re-approving a revised plan updates instead of duplicating, and finished work is never touched.
 */
class ContentPlanMaterializer
{
    /** Items that may still follow the plan; anything later belongs to the content, not the plan. */
    private const EDITABLE = [ContentStatus::Planned, ContentStatus::Cancelled];

    private const CANCELLABLE = [ContentStatus::Planned, ContentStatus::Drafted, ContentStatus::GuardianFailed, ContentStatus::AwaitingApproval, ContentStatus::RevisionRequested, ContentStatus::Rejected];

    public function __construct(private readonly ContentWorkflow $workflow) {}

    public function handle(ArtifactApproved $event): void
    {
        if ($event->version->artifact->type === ArtifactType::ContentPlan) {
            $this->materialize($event->version);
        }
    }

    /** @return array{created: int, updated: int, cancelled: int} */
    public function materialize(ArtifactVersion $version): array
    {
        /** @var Client $client */
        $client = Client::query()->findOrFail($version->client_id);
        $month = (string) $version->payload['month'];
        $counts = ['created' => 0, 'updated' => 0, 'cancelled' => 0];

        DB::transaction(function () use ($client, $version, $month, &$counts) {
            $existing = ContentItem::query()->where(['client_id' => $client->id, 'month' => $month])->get()->keyBy('plan_item_key');
            $keys = [];

            foreach ((array) $version->payload['items'] as $line) {
                $keys[] = $line['key'];
                $attributes = [
                    'content_plan_version_id' => $version->id,
                    'offering_id' => $line['offering_id'] ?? null,
                    'platform' => $line['platform'],
                    'format' => $line['format'],
                    'pillar' => $line['pillar'],
                    'moment_key' => $line['moment_key'] ?? null,
                    'publish_date' => $line['date'],
                    'publish_time' => $line['time'] ?? null,
                    'angle' => $line['angle'],
                    'cta' => $line['cta'],
                    'asset_hint' => $line['asset_hint'] ?? null,
                ];

                $item = $existing->get($line['key']);
                if ($item === null) {
                    ContentItem::query()->create($attributes + [
                        'workspace_id' => $client->workspace_id, 'client_id' => $client->id, 'month' => $month,
                        'plan_item_key' => $line['key'], 'status' => ContentStatus::Planned,
                    ]);
                    $counts['created']++;
                } elseif (in_array($item->status, self::EDITABLE, true)) {
                    $item->update($attributes);
                    if ($item->status === ContentStatus::Cancelled) {
                        $this->workflow->transition($item, ContentStatus::Planned, 'system', 'Kembali ada di Content Plan v'.$version->version);
                    }
                    $counts['updated']++;
                }
            }

            foreach ($existing as $key => $item) {
                if (! in_array($key, $keys, true) && in_array($item->status, self::CANCELLABLE, true)) {
                    $this->workflow->transition($item, ContentStatus::Cancelled, 'system', 'Tidak ada di Content Plan v'.$version->version);
                    $counts['cancelled']++;
                }
            }
        });

        return $counts;
    }
}
