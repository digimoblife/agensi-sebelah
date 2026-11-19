<?php

namespace App\Services\Artifacts\Generators;

use App\Enums\ArtifactType;
use App\Models\Client;
use App\Services\Artifacts\PrerequisiteMissing;
use App\Services\Calendar\MomentCalendar;
use App\Services\Knowledge\KnowledgeBase;
use App\Services\Llm\RoleRequest;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/** F2.3: Content Strategist turns the approved Brand Brief (+ research, moments, offerings) into a monthly plan. */
final class ContentPlanGenerator implements ArtifactGenerator
{
    /** Formats the MVP can produce per platform (render templates first, video in phase 2). */
    public const FORMATS = [
        'instagram' => ['feed_image', 'carousel', 'story', 'reel'],
        'facebook' => ['feed_image', 'carousel'],
        'tiktok' => ['short_video'],
        'google_business_profile' => ['gbp_post'],
    ];

    public function __construct(
        private readonly ClientContext $context,
        private readonly KnowledgeBase $knowledge,
        private readonly MomentCalendar $moments,
    ) {}

    public function type(): ArtifactType
    {
        return ArtifactType::ContentPlan;
    }

    public function request(Client $client, array $options = []): RoleRequest
    {
        $month = $this->month($options);
        $brief = $this->context->approved($client, ArtifactType::BrandBrief)
            ?? throw new PrerequisiteMissing('Setujui Brand Brief terlebih dahulu.');
        $research = $this->context->approved($client, ArtifactType::ResearchPack);

        $platforms = array_values(array_intersect((array) ($options['platforms'] ?? ['instagram']), array_keys(self::FORMATS)));
        if ($platforms === []) {
            throw new PrerequisiteMissing('Pilih minimal satu platform.');
        }

        $perWeek = max(1, min(14, (int) ($options['posts_per_week'] ?? 3)));
        $start = CarbonImmutable::parse($month.'-01')->startOfMonth();
        $target = (int) round($perWeek * $start->daysInMonth / 7);

        return new RoleRequest(
            clientId: $client->id,
            roleKey: 'content_strategist',
            taskRef: "content_plan:{$client->ulid}:{$month}",
            artifacts: array_filter(['brand_brief' => $brief, 'research_pack' => $research]),
            facts: $this->knowledge->facts($client),
            task: array_filter([
                'instruksi' => "Susun Content Plan bulan {$month} dengan sekitar {$target} konten.",
                'bulan' => $month,
                'jumlah_konten' => $target,
                'platform_dan_format' => array_intersect_key(self::FORMATS, array_flip($platforms)),
                'momen' => $this->moments->forMonth($month),
                'penawaran' => $this->context->offerings($client),
                'goal_kpi' => $this->context->goals($client),
                'catatan_admin' => isset($options['notes']) ? mb_substr((string) $options['notes'], 0, 1000) : null,
            ]),
        );
    }

    public function scopeRef(array $options): string
    {
        return $this->month($options);
    }

    public function finalize(array $payload, Client $client, RoleRequest $request): array
    {
        $payload['month'] = $request->task['bulan'];
        $payload['campaign_ref'] = null;

        return $payload;
    }

    /** @param  array<string, mixed>  $options */
    private function month(array $options): string
    {
        $month = (string) ($options['month'] ?? '');
        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) !== 1) {
            throw new InvalidArgumentException('Content plan needs a month option (YYYY-MM).');
        }

        return $month;
    }
}
