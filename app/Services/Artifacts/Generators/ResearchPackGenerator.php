<?php

namespace App\Services\Artifacts\Generators;

use App\Enums\ArtifactType;
use App\Enums\ResearchInputKind;
use App\Models\Client;
use App\Models\ResearchInput;
use App\Services\Artifacts\PrerequisiteMissing;
use App\Services\Knowledge\KnowledgeBase;
use App\Services\Llm\RoleRequest;
use Carbon\CarbonImmutable;

/**
 * F2.1 (MVP): research material is collected without an LLM (admin notes, public sources); the Researcher (T2)
 * only summarizes. Sources and keyword volumes are written back by the backend, never taken from the model.
 */
final class ResearchPackGenerator implements ArtifactGenerator
{
    private const MAX_NOTE_CHARS = 2000;

    private const LOOKBACK_DAYS = 30;

    public function __construct(private readonly ClientContext $context, private readonly KnowledgeBase $knowledge) {}

    public function type(): ArtifactType
    {
        return ArtifactType::ResearchPack;
    }

    public function request(Client $client): RoleRequest
    {
        $inputs = $client->researchInputs()->orderBy('id')->limit(30)->get();
        if ($inputs->isEmpty()) {
            throw new PrerequisiteMissing('Tambahkan minimal satu bahan riset (catatan atau sumber publik).');
        }

        $sources = $inputs->values()->map(fn (ResearchInput $input, int $i) => array_filter([
            'id' => 's'.($i + 1),
            'kind' => $input->kind->value,
            'ref' => $input->ref,
            'catatan' => mb_strimwidth($input->note, 0, self::MAX_NOTE_CHARS, '…'),
            'volume' => $input->keyword_volume,
        ], fn ($v) => $v !== null))->all();

        $today = CarbonImmutable::now($client->timezone);
        $brief = $this->context->approved($client, ArtifactType::BrandBrief);

        return new RoleRequest(
            clientId: $client->id,
            roleKey: 'researcher',
            taskRef: 'research_pack:'.$client->ulid.':'.$today->format('Y-m'),
            artifacts: $brief !== null ? ['brand_brief' => $brief] : [],
            facts: $this->knowledge->facts($client),
            task: array_filter([
                'instruksi' => 'Rangkum bahan riset menjadi Research Pack. Salin daftar sumber apa adanya.',
                'periode' => ['from' => $today->subDays(self::LOOKBACK_DAYS)->toDateString(), 'to' => $today->toDateString()],
                'nama_bisnis' => $client->name,
                'kompetitor_dari_onboarding' => $client->onboarding['competitors'] ?? null,
                'sources' => $sources,
            ]),
        );
    }

    public function finalize(array $payload, Client $client, RoleRequest $request): array
    {
        $sources = (array) $request->task['sources'];
        $payload['period'] = $request->task['periode'];
        $payload['sources'] = array_map(fn (array $s) => ['id' => $s['id'], 'kind' => $s['kind'], 'ref' => mb_substr($s['ref'], 0, 300)], $sources);

        // Keyword volumes only from SERP inputs the admin entered; anything else the model wrote becomes null.
        $volumes = [];
        foreach ($sources as $source) {
            if (is_array($source) && $source['kind'] === ResearchInputKind::Serp->value && isset($source['volume'])) {
                $volumes[mb_strtolower(trim((string) $source['ref']))] = (int) $source['volume'];
            }
        }
        $keywords = [];
        foreach ((array) ($payload['keywords'] ?? []) as $keyword) {
            $keyword['volume'] = $volumes[mb_strtolower(trim((string) $keyword['term']))] ?? null;
            $keywords[] = $keyword;
        }
        $payload['keywords'] = $keywords;

        return $payload;
    }
}
