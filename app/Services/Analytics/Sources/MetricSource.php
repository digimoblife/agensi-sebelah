<?php

namespace App\Services\Analytics\Sources;

use App\Models\Client;
use Carbon\CarbonImmutable;

interface MetricSource
{
    public function key(): string;

    public function isConnected(Client $client): bool;

    /**
     * Daily rows for [from, to] (client calendar dates).
     *
     * @return list<array{entity_type: string, entity_ref: string, date: string, metric_key: string, value: float, content_item_id?: int|null}>
     */
    public function pull(Client $client, CarbonImmutable $from, CarbonImmutable $to): array;
}
