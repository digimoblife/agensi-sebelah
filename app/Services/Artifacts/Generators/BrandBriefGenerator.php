<?php

namespace App\Services\Artifacts\Generators;

use App\Enums\ArtifactType;
use App\Models\Client;
use App\Services\Artifacts\PrerequisiteMissing;
use App\Services\Knowledge\KnowledgeBase;
use App\Services\Llm\RoleRequest;

/** F1.3: Brand Strategist (T3) turns onboarding + profile + facts (+ research) into a Brand Brief. */
final class BrandBriefGenerator implements ArtifactGenerator
{
    public function __construct(private readonly ClientContext $context, private readonly KnowledgeBase $knowledge) {}

    public function type(): ArtifactType
    {
        return ArtifactType::BrandBrief;
    }

    public function request(Client $client): RoleRequest
    {
        $onboarding = $this->context->onboarding($client);
        if (($onboarding['business_description'] ?? '') === '') {
            throw new PrerequisiteMissing('Isi dulu deskripsi bisnis di tab Onboarding.');
        }

        $facts = $this->knowledge->facts($client);
        if ($facts === []) {
            throw new PrerequisiteMissing('Setujui minimal satu item knowledge base. Brand Brief hanya boleh memakai fakta yang disetujui.');
        }

        $research = $this->context->approved($client, ArtifactType::ResearchPack);

        return new RoleRequest(
            clientId: $client->id,
            roleKey: 'brand_strategist',
            taskRef: 'brand_brief:'.$client->ulid,
            artifacts: $research !== null ? ['research_pack' => $research] : [],
            facts: $facts,
            task: array_filter([
                'instruksi' => 'Susun Brand Brief untuk klien ini.',
                'nama_bisnis' => $client->name,
                'onboarding' => $onboarding,
                'profil_jenis_penawaran' => $this->context->offeringProfiles($client),
                'penawaran' => $this->context->offerings($client),
                'goal_kpi' => $this->context->goals($client),
            ]),
        );
    }

    public function finalize(array $payload, Client $client, RoleRequest $request): array
    {
        return $payload;
    }
}
