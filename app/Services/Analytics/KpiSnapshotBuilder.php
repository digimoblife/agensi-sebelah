<?php

namespace App\Services\Analytics;

use App\Enums\ArtifactStatus;
use App\Enums\ArtifactType;
use App\Enums\KpiPeriod;
use App\Models\ArtifactVersion;
use App\Models\Client;
use App\Models\ContentItem;
use App\Models\KpiGoal;
use App\Services\Analytics\Sources\MetricSource;
use App\Services\Artifacts\ArtifactService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * F7/F8 Data Analyst (T0): every number in a report comes from here, computed with SQL. The Report Writer only
 * comments on these rows (PRD principle 7).
 */
class KpiSnapshotBuilder
{
    /** Metrics summed over the period; anything else uses the last value (e.g. follower count). */
    private const LAST_VALUE = ['ig_follower_count'];

    private const LABELS = [
        'ig_reach' => 'Jangkauan Instagram', 'ig_follower_count' => 'Pengikut Instagram', 'sessions' => 'Sesi website',
        'users' => 'Pengunjung website',
    ];

    public function __construct(private readonly ArtifactService $artifacts, private readonly MetricsPuller $puller) {}

    public function build(Client $client, CarbonImmutable $from, CarbonImmutable $to, string $scopeRef): ArtifactVersion
    {
        $days = (int) $from->diffInDays($to) + 1;
        $prevTo = $from->subDay();
        $prevFrom = $prevTo->subDays($days - 1);

        $current = $this->totals($client, $from, $to);
        $previous = $this->totals($client, $prevFrom, $prevTo);
        $goals = KpiGoal::query()->where('client_id', $client->id)->orderBy('priority')->get()->keyBy('metric_key');
        $primary = $goals->first()?->metric_key;

        $rows = [];
        foreach ($current as $key => $row) {
            $prev = $previous[$key]['value'] ?? null;
            $goal = $goals->get($key);
            $rows[] = [
                'metric_key' => $key,
                'label' => $goal->label ?? (self::LABELS[$key] ?? str_replace('_', ' ', ucfirst($key))),
                'source' => $row['source'],
                'value' => round($row['value'], 2),
                'previous' => $prev !== null ? round($prev, 2) : null,
                'change_pct' => $prev !== null && $prev > 0 ? round(($row['value'] - $prev) / $prev * 100, 2) : null,
                'target' => $goal !== null ? round($this->scaledTarget($goal, $days), 2) : null,
                'is_primary_goal' => $key === $primary,
            ];
        }
        usort($rows, fn ($a, $b) => [$b['is_primary_goal'], $b['target'] !== null] <=> [$a['is_primary_goal'], $a['target'] !== null]);

        return $this->artifacts->createVersion($client, ArtifactType::KpiSnapshot, [
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'compare_from' => $prevFrom->toDateString(), 'compare_to' => $prevTo->toDateString()],
            'rows' => array_slice($rows, 0, 30) ?: [['metric_key' => 'no_data', 'label' => 'Belum ada data', 'source' => 'manual', 'value' => 0, 'previous' => null, 'change_pct' => null]],
            'top_content' => $this->topContent($client, $from, $to),
            'data_quality' => $this->quality($client, $from, $to),
        ], $scopeRef, changeNote: 'Dihitung otomatis', status: ArtifactStatus::Approved);
    }

    /** @return array<string, array{source: string, value: float}> */
    private function totals(Client $client, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $base = DB::table('metrics_daily')->where('client_id', $client->id)->whereIn('entity_type', ['account', 'property', 'event'])
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()]);

        $sums = (clone $base)->whereNotIn('metric_key', self::LAST_VALUE)->groupBy('metric_key', 'source')
            ->selectRaw('metric_key, source, SUM(value) AS total')->get();
        $last = (clone $base)->whereIn('metric_key', self::LAST_VALUE)->orderBy('date')->get(['metric_key', 'source', 'value']);

        $totals = [];
        foreach ($sums as $row) {
            $totals[(string) $row->metric_key] = ['source' => (string) $row->source, 'value' => (float) $row->total];
        }
        foreach ($last as $row) {
            $totals[(string) $row->metric_key] = ['source' => (string) $row->source, 'value' => (float) $row->value];
        }

        return $totals;
    }

    /** Weekly reports compare against goals pro rata (a monthly target of 120 is 28 for a 7-day week). */
    private function scaledTarget(KpiGoal $goal, int $days): float
    {
        $periodDays = match ($goal->period) {
            KpiPeriod::Week => 7, KpiPeriod::Month => 30, KpiPeriod::Quarter => 91,
        };

        return (float) $goal->target_value * $days / $periodDays;
    }

    /** @return list<array{content_item_id: int, metric_key: string, value: float}> */
    private function topContent(Client $client, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $published = ContentItem::query()->where('client_id', $client->id)->whereBetween('publish_date', [$from->toDateString(), $to->toDateString()])->pluck('id');

        $top = DB::table('metrics_daily')->where('client_id', $client->id)->where('metric_key', 'ig_post_reach')
            ->whereIn('content_item_id', $published)->groupBy('content_item_id')
            ->selectRaw('content_item_id, MAX(value) AS value')->orderByDesc('value')->limit(5)->get()
            ->map(fn ($r) => ['content_item_id' => (int) $r->content_item_id, 'metric_key' => 'ig_post_reach', 'value' => (float) $r->value])->values()->all();

        return array_values($top);
    }

    /** @return list<string> */
    private function quality(Client $client, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $notes = [];
        $days = (int) $from->diffInDays($to) + 1;
        /** @var MetricSource $source */
        foreach ($this->puller->sources() as $source) {
            if (! $source->isConnected($client)) {
                $notes[] = "Sumber {$source->key()} belum terhubung.";

                continue;
            }
            $covered = DB::table('metrics_daily')->where(['client_id' => $client->id, 'source' => $source->key()])
                ->whereBetween('date', [$from->toDateString(), $to->toDateString()])->distinct()->count('date');
            if ($covered < $days) {
                $notes[] = "Data {$source->key()} hanya ada untuk {$covered} dari {$days} hari.";
            }
        }

        return $notes;
    }
}
