<?php

namespace App\Services\Analytics\Sources;

use App\Models\Client;
use App\Models\OAuthConnection;
use App\Models\OfferingType;
use App\Services\Connections\GoogleConnection;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;

/** F7.3 (MVP): GA4 Data API — sessions and users per day, and counts of the client's conversion events. */
class Ga4Reports implements MetricSource
{
    public function __construct(private readonly GoogleConnection $google) {}

    public function key(): string
    {
        return 'ga4';
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
        $token = $this->google->accessToken($connection);
        $property = (string) $connection->account_ref;
        $range = [['startDate' => $from->toDateString(), 'endDate' => $to->toDateString()]];
        $rows = [];

        $traffic = $this->report($token, $property, ['dateRanges' => $range, 'dimensions' => [['name' => 'date']], 'metrics' => [['name' => 'sessions'], ['name' => 'totalUsers']]]);
        foreach ($traffic as $row) {
            $date = $this->date((string) $row['dimensionValues'][0]['value']);
            $rows[] = ['entity_type' => 'property', 'entity_ref' => $property, 'date' => $date, 'metric_key' => 'sessions', 'value' => (float) $row['metricValues'][0]['value']];
            $rows[] = ['entity_type' => 'property', 'entity_ref' => $property, 'date' => $date, 'metric_key' => 'users', 'value' => (float) $row['metricValues'][1]['value']];
        }

        $events = $this->conversionEvents($client);
        if ($events !== []) {
            $conversions = $this->report($token, $property, [
                'dateRanges' => $range,
                'dimensions' => [['name' => 'date'], ['name' => 'eventName']],
                'metrics' => [['name' => 'eventCount']],
                'dimensionFilter' => ['filter' => ['fieldName' => 'eventName', 'inListFilter' => ['values' => $events]]],
            ]);
            foreach ($conversions as $row) {
                $event = (string) $row['dimensionValues'][1]['value'];
                $rows[] = ['entity_type' => 'event', 'entity_ref' => $event, 'date' => $this->date((string) $row['dimensionValues'][0]['value']),
                    'metric_key' => $this->metricKey($event), 'value' => (float) $row['metricValues'][0]['value']];
            }
        }

        return $rows;
    }

    /** @return list<string> event names from the offering profiles and the client's KPI goals */
    public function conversionEvents(Client $client): array
    {
        $types = array_filter([$client->primary_offering_type, ...$client->offerings()->pluck('offering_type')->all()]);
        $events = OfferingType::query()->whereIn('key', $types)->get()->flatMap(fn (OfferingType $t) => $t->conversion_events);

        return array_values($events->merge($client->kpiGoals()->pluck('metric_key'))->unique()->map(fn ($e) => (string) $e)->values()->all());
    }

    /**
     * @param  array<string, mixed>  $body
     * @return list<array<string, mixed>>
     */
    private function report(string $token, string $property, array $body): array
    {
        $response = Http::timeout(30)->withToken($token)
            ->post("https://analyticsdata.googleapis.com/v1beta/properties/{$property}:runReport", $body + ['limit' => 10000]);
        if (! $response->successful()) {
            throw new \RuntimeException('ga4_http_'.$response->status());
        }

        return array_values(array_filter((array) $response->json('rows', []), 'is_array'));
    }

    private function date(string $yyyymmdd): string
    {
        return substr($yyyymmdd, 0, 4).'-'.substr($yyyymmdd, 4, 2).'-'.substr($yyyymmdd, 6, 2);
    }

    private function metricKey(string $event): string
    {
        $key = trim((string) preg_replace('/[^a-z0-9_]+/', '_', strtolower($event)), '_');

        return preg_match('/^[a-z]/', $key) === 1 ? mb_substr($key, 0, 50) : 'e_'.mb_substr($key, 0, 48);
    }

    private function connection(Client $client): ?OAuthConnection
    {
        return OAuthConnection::query()->where(['client_id' => $client->id, 'platform' => 'ga4'])->first();
    }
}
