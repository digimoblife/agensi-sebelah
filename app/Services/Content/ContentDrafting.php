<?php

namespace App\Services\Content;

use App\Enums\ArtifactType;
use App\Enums\ContentStatus;
use App\Models\Client;
use App\Models\ContentItem;
use App\Services\Artifacts\Generators\ClientContext;
use App\Services\Artifacts\PrerequisiteMissing;
use App\Services\Knowledge\KnowledgeBase;
use App\Services\Llm\LlmGateway;
use App\Services\Llm\RoleRequest;
use App\Services\Workflow\ContentWorkflow;
use Illuminate\Support\Collection;
use Throwable;

/**
 * F3.1 + F3.4: batched copywriting (one LLM call per batch, PRD principle 5) and partial revision of single items
 * (principle 9), followed by the Brand Guardian and at most one automatic revision.
 */
class ContentDrafting
{
    public const BATCH_SIZE = 10;

    /** Statuses a draft can be (re)written from, with the status to return to if the call fails. */
    private const DRAFTABLE = [ContentStatus::Planned, ContentStatus::RevisionRequested, ContentStatus::Rejected, ContentStatus::GuardianFailed];

    public function __construct(
        private readonly LlmGateway $gateway,
        private readonly ContentWorkflow $workflow,
        private readonly BrandGuardian $guardian,
        private readonly ClientContext $context,
        private readonly KnowledgeBase $knowledge,
    ) {}

    /**
     * @param  Collection<int, ContentItem>  $items
     * @param  array<int, string>  $feedback  item id => extra revision instruction (Guardian feedback)
     * @return array{awaiting_approval: int, guardian_failed: int}
     */
    public function draft(Client $client, Collection $items, string $actor, array $feedback = []): array
    {
        $brief = $this->context->approved($client, ArtifactType::BrandBrief)
            ?? throw new PrerequisiteMissing('Setujui Brand Brief terlebih dahulu.');
        $visual = $this->context->approved($client, ArtifactType::VisualRules);

        $items = $items->filter(fn (ContentItem $i) => $i->client_id === $client->id && in_array($i->status, self::DRAFTABLE, true))->values();
        $summary = ['awaiting_approval' => 0, 'guardian_failed' => 0];

        foreach ($items->chunk(self::BATCH_SIZE) as $batch) {
            $drafted = $this->writeBatch($client, $batch->values(), $brief, $visual, $actor, $feedback);
            $retry = $this->guardian->review($client, $drafted);

            if ($retry->isNotEmpty()) {
                $again = [];
                foreach ($retry as $item) {
                    $item->increment('auto_revision_count');
                    $again[$item->id] = $this->guardian->feedback($item);
                }
                $this->writeBatch($client, $retry, $brief, $visual, 'brand_guardian', $again)
                    ->pipe(fn (Collection $redrafted) => $this->guardian->review($client, $redrafted));
            }
        }

        foreach ($items as $item) {
            $status = $item->fresh()?->status;
            if ($status === ContentStatus::AwaitingApproval) {
                $summary['awaiting_approval']++;
            } elseif ($status === ContentStatus::GuardianFailed) {
                $summary['guardian_failed']++;
            }
        }

        return $summary;
    }

    /**
     * @param  Collection<int, ContentItem>  $batch
     * @param  array<string, mixed>  $brief
     * @param  array<string, mixed>|null  $visual
     * @param  array<int, string>  $feedback
     * @return Collection<int, ContentItem> drafted items
     */
    private function writeBatch(Client $client, Collection $batch, array $brief, ?array $visual, string $actor, array $feedback): Collection
    {
        $previous = [];
        foreach ($batch as $item) {
            $previous[$item->id] = $item->status;
            $this->workflow->transition($item, ContentStatus::Drafting, $actor);
        }

        $lines = $batch->map(function (ContentItem $item) use ($previous, $feedback) {
            $line = $item->planLine();
            $comment = $feedback[$item->id] ?? ($previous[$item->id] !== ContentStatus::Planned ? $item->revision_comment : null);
            if ($comment !== null && $comment !== '') {
                $line['revisi'] = ['komentar' => $comment, 'draf_sebelumnya' => $item->draft];
            }

            return $line;
        })->values()->all();

        try {
            $result = $this->gateway->run(new RoleRequest(
                clientId: $client->id,
                roleKey: 'copywriter',
                taskRef: 'copy:'.$batch->pluck('id')->implode(','),
                artifacts: array_filter(['brand_brief' => $brief, 'visual_rules' => $visual]),
                facts: $this->knowledge->facts($client),
                task: [
                    'instruksi' => 'Tulis draf untuk setiap item. Item dengan "revisi" diperbaiki sesuai komentar; ubah seperlunya saja.',
                    'plan_item_keys' => $batch->pluck('plan_item_key')->all(),
                    'items' => $lines,
                    'penawaran' => $this->context->offerings($client),
                ],
            ));
        } catch (Throwable $e) {
            foreach ($batch as $item) {
                $this->workflow->transition($item, $previous[$item->id] === ContentStatus::Planned ? ContentStatus::Planned : ContentStatus::RevisionRequested, 'system', 'Penulisan gagal: '.class_basename($e));
            }
            throw $e;
        }

        $drafts = collect((array) $result->payload['items'])->keyBy('plan_item_key');

        return $batch->map(function (ContentItem $item) use ($drafts, $previous, $result, $actor) {
            $wasRevision = $previous[$item->id] !== ContentStatus::Planned;
            $humanRevision = $wasRevision && $actor !== 'brand_guardian';
            $revisions = $item->revision_count + ($humanRevision ? 1 : 0);

            return $this->workflow->transition($item, ContentStatus::Drafted, $actor, $wasRevision ? 'Direvisi' : 'Draf pertama', [
                'draft' => $drafts[$item->plan_item_key],
                'revision_count' => $revisions,
                // K5: regenerated more than once on human request counts as a major revision.
                'major_revision' => $item->major_revision || $revisions > 1,
                'last_llm_call_id' => $result->llmCallId,
            ]);
        });
    }
}
