<?php

namespace App\Services\Artifacts\Generators;

use App\Enums\ArtifactType;
use App\Enums\OfferingStatus;
use App\Models\Client;
use App\Models\KpiGoal;
use App\Models\Offering;
use App\Models\OfferingType;
use App\Services\Artifacts\ArtifactService;
use Illuminate\Support\Collection;

/** Shared, compact client inputs for generators. Never includes raw account credentials. */
final class ClientContext
{
    public function __construct(private readonly ArtifactService $artifacts) {}

    /** @return array<string, mixed>|null approved payload without meta */
    public function approved(Client $client, ArtifactType $type): ?array
    {
        $payload = $this->artifacts->approved($client, $type)?->payload;
        if ($payload === null) {
            return null;
        }
        unset($payload['meta']);

        return $payload;
    }

    /** @return list<array<string, mixed>> */
    public function offerings(Client $client): array
    {
        return array_values($client->offerings()->where('status', '!=', OfferingStatus::Archived)->with('type')->orderBy('id')->get()
            ->map(fn (Offering $o) => array_filter([
                'id' => $o->id,
                'nama' => $o->name,
                'jenis' => $o->offering_type,
                'deskripsi' => $o->description,
                'harga' => $o->price !== null ? $o->currency.' '.number_format((float) $o->price, 0, ',', '.') : null,
                'jadwal' => $o->schedule,
                'url' => $o->url,
            ], fn ($v) => ! in_array($v, [null, '', []], true)))
            ->values()->all());
    }

    /** @return list<array<string, mixed>> */
    public function offeringProfiles(Client $client): array
    {
        /** @var Collection<int, OfferingType> $types */
        $types = $client->offerings()->with('type')->get()->pluck('type')
            ->push($client->offeringType)->filter()->unique('key')->values();

        return array_values($types->map(fn (OfferingType $type) => $type->profile())->all());
    }

    /** @return list<array<string, mixed>> */
    public function goals(Client $client): array
    {
        return array_values($client->kpiGoals()->orderBy('priority')->get()
            ->map(fn (KpiGoal $g) => ['metric' => $g->metric_key, 'label' => $g->label, 'target' => (float) $g->target_value, 'periode' => $g->period->value, 'prioritas' => $g->priority])
            ->all());
    }

    /** @return array<string, mixed> onboarding answers without the account-access checklist */
    public function onboarding(Client $client): array
    {
        $answers = $client->onboarding ?? [];
        unset($answers['account_access']);

        return array_filter($answers, fn ($v) => $v !== null && $v !== '' && $v !== []);
    }
}
