<?php

namespace App\Services\Artifacts\Generators;

use App\Enums\ArtifactType;
use App\Models\Client;
use App\Services\Artifacts\PrerequisiteMissing;
use App\Services\Llm\RoleRequest;

/** F1.3: Art Director (T3, once) derives Visual Rules from the approved Brand Brief. */
final class VisualRulesGenerator implements ArtifactGenerator
{
    public const LOGO_PENDING = 'pending';

    public function __construct(private readonly ClientContext $context) {}

    public function type(): ArtifactType
    {
        return ArtifactType::VisualRules;
    }

    public function request(Client $client, array $options = []): RoleRequest
    {
        $brief = $this->context->approved($client, ArtifactType::BrandBrief)
            ?? throw new PrerequisiteMissing('Setujui Brand Brief terlebih dahulu.');

        $onboarding = $client->onboarding ?? [];

        return new RoleRequest(
            clientId: $client->id,
            roleKey: 'art_director',
            taskRef: 'visual_rules:'.$client->ulid,
            artifacts: ['brand_brief' => $brief],
            task: array_filter([
                'instruksi' => 'Tetapkan Visual Rules. Isi logo.asset_id dengan "'.self::LOGO_PENDING.'"; logo dipilih admin dari pustaka aset.',
                'preferensi_visual' => $onboarding['visual_preferences'] ?? null,
                'aset_yang_dibutuhkan' => collect($this->context->offeringProfiles($client))->pluck('jenis')->all(),
            ]),
        );
    }

    public function scopeRef(array $options): ?string
    {
        return null;
    }

    public function finalize(array $payload, Client $client, RoleRequest $request): array
    {
        $payload['logo']['asset_id'] = self::LOGO_PENDING; // assets are chosen by the admin, never by the model

        return $payload;
    }
}
