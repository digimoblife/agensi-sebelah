<?php

namespace App\Services\Content;

use App\Enums\ArtifactType;
use App\Enums\ContentStatus;
use App\Models\Client;
use App\Models\ContentItem;
use App\Models\OfferingType;
use App\Services\Artifacts\Generators\ClientContext;
use App\Services\Knowledge\KnowledgeBase;
use App\Services\Llm\LlmGateway;
use App\Services\Llm\RoleRequest;
use App\Services\Workflow\ContentWorkflow;
use Illuminate\Support\Collection;

/**
 * F3.3: rules (T0) first, then one batched LLM review (T1) of the drafts that passed the rules. Returns the items
 * that failed and may still get their one automatic revision.
 */
class BrandGuardian
{
    public const MAX_AUTO_REVISIONS = 1;

    public function __construct(
        private readonly GuardianRules $rules,
        private readonly LlmGateway $gateway,
        private readonly ClientContext $context,
        private readonly KnowledgeBase $knowledge,
        private readonly ContentWorkflow $workflow,
    ) {}

    /**
     * @param  Collection<int, ContentItem>  $items  drafted items of one client
     * @return Collection<int, ContentItem> failed items eligible for an automatic revision
     */
    public function review(Client $client, Collection $items): Collection
    {
        $brief = $this->context->approved($client, ArtifactType::BrandBrief) ?? [];
        $visual = $this->context->approved($client, ArtifactType::VisualRules);

        /** @var array<int, list<array<string, string>>> $ruleIssues */
        $ruleIssues = [];
        foreach ($items as $item) {
            $ruleIssues[$item->id] = $this->rules->check((array) $item->draft, $item->platform, $item->format, $brief, $visual);
        }

        $forLlm = $items->filter(fn (ContentItem $item) => ! $this->hasMajor($ruleIssues[$item->id]));
        $reviews = $forLlm->isEmpty() ? [] : $this->llmReview($client, $brief, $forLlm);

        $retry = collect();
        foreach ($items as $item) {
            $review = $reviews[$item->plan_item_key] ?? null;
            /** @var list<array<string, string>> $llmIssues */
            $llmIssues = array_map(fn (array $i) => $i + ['source' => 'llm'], (array) ($review['issues'] ?? []));
            $issues = [...$ruleIssues[$item->id], ...$llmIssues];
            // Items that skipped the LLM already have a major rule issue, so they fail on hasMajor().
            $passed = ! $this->hasMajor($issues) && ($review['verdict'] ?? 'fail') === 'pass';

            $guardian = ['verdict' => $passed ? 'pass' : 'fail', 'issues' => $issues, 'checked_at' => now()->toIso8601String()];

            if ($passed) {
                $this->workflow->transition($item, ContentStatus::AwaitingApproval, 'brand_guardian', 'Lolos Brand Guardian', ['guardian' => $guardian]);

                continue;
            }

            $this->workflow->transition($item, ContentStatus::GuardianFailed, 'brand_guardian', $this->summary($issues), ['guardian' => $guardian]);
            if ($item->auto_revision_count < self::MAX_AUTO_REVISIONS) {
                $retry->push($item);
            }
        }

        return $retry;
    }

    /** Feedback for the automatic revision, built from the Guardian's issues. */
    public function feedback(ContentItem $item): string
    {
        $lines = array_map(
            fn (array $i) => "- [{$i['severity']}] {$i['note']}".(isset($i['suggestion']) ? " Saran: {$i['suggestion']}" : ''),
            array_filter((array) ($item->guardian['issues'] ?? []), fn (array $i) => $i['severity'] === 'major'),
        );

        return "Perbaiki temuan Brand Guardian berikut:\n".implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $brief
     * @param  Collection<int, ContentItem>  $items
     * @return array<string, array{verdict: string, issues: list<array<string, string>>}>
     */
    private function llmReview(Client $client, array $brief, Collection $items): array
    {
        $compliance = OfferingType::query()->whereIn('key', array_filter([$client->primary_offering_type, ...$items->pluck('offering.offering_type')->all()]))
            ->get()->flatMap(fn (OfferingType $t) => $t->compliance_notes)->unique()->values()->all();

        $result = $this->gateway->run(new RoleRequest(
            clientId: $client->id,
            roleKey: 'brand_guardian',
            taskRef: 'guardian:'.$items->pluck('id')->implode(','),
            artifacts: ['brand_brief' => $brief],
            facts: $this->knowledge->facts($client),
            task: array_filter([
                'instruksi' => 'Periksa setiap draf.',
                'catatan_kepatuhan' => $compliance,
                'aturan_brand' => $brief['rules']['compliance'] ?? null,
                'drafts' => $items->map(fn (ContentItem $i) => ['plan_item_key' => $i->plan_item_key, 'platform' => $i->platform, 'format' => $i->format] + (array) $i->draft)->values()->all(),
            ]),
        ));

        return collect((array) $result->payload['items'])->keyBy('plan_item_key')->all();
    }

    /** @param  list<array<string, string>>  $issues */
    private function hasMajor(array $issues): bool
    {
        return in_array('major', array_column($issues, 'severity'), true);
    }

    /** @param  list<array<string, string>>  $issues */
    private function summary(array $issues): string
    {
        $major = array_filter($issues, fn (array $i) => $i['severity'] === 'major');

        return 'Gagal Brand Guardian: '.implode('; ', array_slice(array_column($major, 'note'), 0, 3));
    }
}
