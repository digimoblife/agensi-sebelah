<?php

namespace App\Services\Ops;

use App\Enums\ContentStatus;
use App\Enums\PublishStatus;
use App\Models\Budget;
use App\Models\Client;
use App\Models\ContentItem;
use App\Models\LlmCall;
use App\Models\PublishJob;
use App\Models\Workspace;
use Carbon\CarbonImmutable;

/**
 * PRD roadmap gate for the MVP: the pilot client runs 4 weeks, monthly token cost is measured, and at least 80 %
 * of content is approved without a major revision (K5). Also compares cost with the Paperclip baseline.
 */
class MvpGate
{
    public const WEEKS_REQUIRED = 4;

    public const APPROVAL_TARGET = 80.0;

    /** Statuses that mean someone made an approval decision. */
    private const DECIDED = [
        ContentStatus::Approved, ContentStatus::Rendered, ContentStatus::Scheduled, ContentStatus::Published, ContentStatus::PublishFailed,
        ContentStatus::Packaged, ContentStatus::ManuallyUploaded, ContentStatus::Rejected,
    ];

    /**
     * @return array{weeks: int, first_live: ?string, decided: int, clean: int, clean_pct: ?float, llm_cost_month: float,
     *     media_cost_month: float, calls_month: int, live_month: int, cost_per_content: ?float, baseline: ?float, savings_pct: ?float, passed: bool}
     */
    public function metrics(Client $client, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $monthStart = $now->setTimezone($client->timezone)->startOfMonth()->utc();

        $firstLive = PublishJob::query()->where('client_id', $client->id)
            ->whereIn('status', [PublishStatus::Published, PublishStatus::ManuallyUploaded])->min('published_at');
        $weeks = $firstLive !== null ? (int) floor(CarbonImmutable::parse((string) $firstLive)->diffInDays($now) / 7) : 0;

        $decided = ContentItem::query()->where('client_id', $client->id)->whereIn('status', self::DECIDED);
        $decidedCount = (clone $decided)->count();
        $clean = (clone $decided)->where('status', '!=', ContentStatus::Rejected)->where('major_revision', false)->count();
        $cleanPct = $decidedCount > 0 ? round($clean / $decidedCount * 100, 1) : null;

        $calls = LlmCall::query()->where('client_id', $client->id)->where('created_at', '>=', $monthStart);
        $llmCost = (float) (clone $calls)->sum('cost_usd');
        $media = (float) Budget::query()->where('client_id', $client->id)->where('month', $monthStart->setTimezone($client->timezone)->toDateString())->value('media_spent_usd');
        $live = PublishJob::query()->where('client_id', $client->id)->whereIn('status', [PublishStatus::Published, PublishStatus::ManuallyUploaded])
            ->where('published_at', '>=', $monthStart)->count();

        $baseline = Workspace::query()->value('settings')['paperclip_baseline_usd'] ?? null;
        $baseline = is_numeric($baseline) ? (float) $baseline : null;
        $total = $llmCost + $media;

        return [
            'weeks' => $weeks,
            'first_live' => $firstLive !== null ? CarbonImmutable::parse((string) $firstLive)->setTimezone($client->timezone)->toDateString() : null,
            'decided' => $decidedCount,
            'clean' => $clean,
            'clean_pct' => $cleanPct,
            'llm_cost_month' => round($llmCost, 4),
            'media_cost_month' => round($media, 4),
            'calls_month' => (clone $calls)->count(),
            'live_month' => $live,
            'cost_per_content' => $live > 0 ? round($total / $live, 4) : null,
            'baseline' => $baseline,
            'savings_pct' => $baseline !== null && $baseline > 0 ? round(($baseline - $total) / $baseline * 100, 1) : null,
            'passed' => $weeks >= self::WEEKS_REQUIRED && $cleanPct !== null && $cleanPct >= self::APPROVAL_TARGET && (clone $calls)->exists(),
        ];
    }
}
