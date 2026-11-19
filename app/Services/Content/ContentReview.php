<?php

namespace App\Services\Content;

use App\Enums\ApprovalDecision;
use App\Enums\ContentStatus;
use App\Jobs\RenderAndSend;
use App\Models\Approval;
use App\Models\ContentItem;
use App\Services\Render\ContentRenderer;
use App\Services\Workflow\ContentWorkflow;
use Illuminate\Support\Facades\DB;

/**
 * F4.1 decisions on a content item. One service for every channel (dashboard now, Telegram in week 4).
 * Reasons are kept for F9.4 (learning from revisions).
 */
class ContentReview
{
    public function __construct(private readonly ContentWorkflow $workflow, private readonly ContentRenderer $renderer) {}

    public function approve(ContentItem $item, string $actor, string $channel = 'dashboard'): ContentItem
    {
        return DB::transaction(function () use ($item, $actor, $channel) {
            $this->record($item, ApprovalDecision::Approved, $actor, $channel);
            $this->workflow->transition($item, ContentStatus::Approved, $actor, 'Disetujui');

            // The visual the client approved is the final one: ready for scheduling (week 5).
            if ($this->renderer->isCurrent($item)) {
                $this->workflow->transition($item, ContentStatus::Rendered, 'system', 'Visual yang disetujui');
            }

            return $item;
        });
    }

    /** @param  list<string>  $reasonTags */
    public function requestRevision(ContentItem $item, string $comment, bool $major, string $actor, string $channel = 'dashboard', array $reasonTags = []): ContentItem
    {
        return DB::transaction(function () use ($item, $comment, $major, $actor, $channel, $reasonTags) {
            $this->record($item, ApprovalDecision::Revise, $actor, $channel, $comment, $major, $reasonTags);

            return $this->workflow->transition($item, ContentStatus::RevisionRequested, $actor, mb_substr($comment, 0, 300), [
                'revision_comment' => $comment,
                'major_revision' => $item->major_revision || $major,
            ]);
        });
    }

    /** @param  list<string>  $reasonTags */
    public function reject(ContentItem $item, ?string $comment, string $actor, string $channel = 'dashboard', array $reasonTags = []): ContentItem
    {
        return DB::transaction(function () use ($item, $comment, $actor, $channel, $reasonTags) {
            $this->record($item, ApprovalDecision::Rejected, $actor, $channel, $comment, true, $reasonTags);

            return $this->workflow->transition($item, ContentStatus::Rejected, $actor, $comment, [
                'revision_comment' => $comment,
                'major_revision' => true, // K5: a rejected item always counts as a major revision
            ]);
        });
    }

    /** The admin releases an item the Guardian failed (e.g. a false positive). */
    public function overrideGuardian(ContentItem $item, string $reason, string $actor): ContentItem
    {
        $this->workflow->transition($item, ContentStatus::AwaitingApproval, $actor, 'Diloloskan admin: '.$reason);
        dispatch(new RenderAndSend([$item->id]));

        return $item;
    }

    /** @param  list<string>  $reasonTags */
    private function record(ContentItem $item, ApprovalDecision $decision, string $actor, string $channel, ?string $comment = null, bool $major = false, array $reasonTags = []): void
    {
        Approval::query()->create([
            'workspace_id' => $item->workspace_id,
            'client_id' => $item->client_id,
            'content_item_id' => $item->id,
            'channel' => $channel,
            'decision' => $decision,
            'comment' => $comment,
            'reason_tags' => $reasonTags === [] ? null : $reasonTags,
            'is_major' => $major,
            'decided_by' => mb_substr($actor, 0, 60),
            'decided_at' => now(),
        ]);
    }
}
