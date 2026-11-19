<?php

namespace App\Services\Analytics;

use App\Models\Client;
use App\Services\Analytics\Sources\Ga4Reports;
use App\Services\Analytics\Sources\InstagramInsights;
use App\Services\Analytics\Sources\MetricSource;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * F7 (MVP) T0 data pull. Catches up from the last complete day (ADR 0003): if the Mac was off for days, the next
 * run fetches the whole gap (max 30 days). Upserts make re-runs safe.
 */
class MetricsPuller
{
    private const MAX_DAYS = 30;

    /** @return array<string, string> source => outcome */
    public function pull(Client $client): array
    {
        $outcomes = [];
        foreach ($this->sources() as $source) {
            $outcomes[$source->key()] = $this->pullSource($client, $source);
        }

        return $outcomes;
    }

    public function pullSource(Client $client, MetricSource $source): string
    {
        if (! $source->isConnected($client)) {
            return 'not_connected';
        }

        $yesterday = CarbonImmutable::now($client->timezone)->subDay()->startOfDay();
        $state = DB::table('metric_pulls')->where(['client_id' => $client->id, 'source' => $source->key()])->first();
        $last = $state?->last_complete_date !== null ? CarbonImmutable::parse((string) $state->last_complete_date) : null;
        $from = $last !== null ? $last->addDay() : $yesterday->subDays(self::MAX_DAYS - 1);
        $from = $from->max($yesterday->subDays(self::MAX_DAYS - 1));
        if ($from->gt($yesterday)) {
            return 'up_to_date';
        }

        try {
            $rows = $source->pull($client, $from, $yesterday);
        } catch (Throwable $e) {
            $this->state($client, $source->key(), $last, 'error_'.mb_substr($e->getMessage() !== '' && strlen($e->getMessage()) < 60 ? $e->getMessage() : class_basename($e), 0, 70));

            return 'error';
        }

        $now = now();
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('metrics_daily')->upsert(array_map(fn (array $r) => [
                'client_id' => $client->id, 'source' => $source->key(), 'entity_type' => $r['entity_type'], 'entity_ref' => $r['entity_ref'],
                'date' => $r['date'], 'metric_key' => $r['metric_key'], 'value' => $r['value'], 'content_item_id' => $r['content_item_id'] ?? null,
                'pulled_at' => $now,
            ], $chunk), ['client_id', 'source', 'entity_type', 'entity_ref', 'date', 'metric_key'], ['value', 'content_item_id', 'pulled_at']);
        }

        $this->state($client, $source->key(), $yesterday, null);

        return 'ok:'.count($rows);
    }

    /** @return list<MetricSource> */
    public function sources(): array
    {
        return [app(InstagramInsights::class), app(Ga4Reports::class)];
    }

    private function state(Client $client, string $source, ?CarbonImmutable $lastComplete, ?string $error): void
    {
        DB::table('metric_pulls')->updateOrInsert(['client_id' => $client->id, 'source' => $source], [
            'last_complete_date' => $lastComplete?->toDateString(), 'last_error_code' => $error, 'last_run_at' => now(),
            'updated_at' => now(), 'created_at' => now(),
        ]);
    }
}
