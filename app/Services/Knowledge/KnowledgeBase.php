<?php

namespace App\Services\Knowledge;

use App\Models\Client;
use App\Models\KnowledgeItem;

/** F9.2: turns approved, unexpired knowledge items into the fact list a role receives. */
class KnowledgeBase
{
    private const MAX_FACT_CHARS = 500;

    /**
     * @param  list<int>|null  $offeringIds  limit to these offerings (plus client-wide items); null = all
     * @return list<array{id: int, text: string}>
     */
    public function facts(Client $client, ?array $offeringIds = null): array
    {
        return array_values(KnowledgeItem::query()
            ->where('client_id', $client->id)
            ->usable()
            ->when($offeringIds !== null, fn ($q) => $q->where(fn ($q) => $q->whereNull('offering_id')->orWhereIn('offering_id', $offeringIds)))
            ->orderBy('id')
            ->get()
            ->map(fn (KnowledgeItem $item) => [
                'id' => $item->id,
                'text' => mb_strimwidth("{$item->kind->label()} · {$item->title}: ".preg_replace('/\s+/', ' ', $item->body), 0, self::MAX_FACT_CHARS, '…'),
            ])
            ->values()
            ->all());
    }
}
