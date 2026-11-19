<?php

namespace App\Services\Workflow;

use App\Enums\ContentStatus as S;
use App\Models\ContentItem;
use App\Models\WorkflowEvent;
use Illuminate\Support\Facades\DB;

/** The only place a content item's status changes (docs/WORKFLOWS.md). Every transition is recorded. */
class ContentWorkflow
{
    /** @var array<string, list<S>> from => allowed targets */
    public const MATRIX = [
        'planned' => [S::Drafting, S::Cancelled],
        'drafting' => [S::Drafted, S::Planned, S::RevisionRequested],   // back on failure
        'drafted' => [S::AwaitingApproval, S::GuardianFailed, S::Drafting, S::Cancelled],
        'guardian_failed' => [S::Drafting, S::AwaitingApproval, S::Cancelled], // admin may override the Guardian
        'awaiting_approval' => [S::Approved, S::RevisionRequested, S::Rejected, S::Cancelled],
        'revision_requested' => [S::Drafting, S::Cancelled],
        'approved' => [S::Rendered, S::RevisionRequested, S::Cancelled],
        'rejected' => [S::Drafting, S::Cancelled],
        'cancelled' => [S::Planned],
        'rendered' => [S::Scheduled, S::Packaged, S::RevisionRequested, S::Cancelled],
        'scheduled' => [S::Published, S::PublishFailed, S::Packaged, S::Cancelled],
        'publish_failed' => [S::Scheduled, S::Packaged],
        'packaged' => [S::ManuallyUploaded, S::Scheduled],
        'published' => [],
        'manually_uploaded' => [],
    ];

    /** @return list<S> */
    private function allowed(S $from): array
    {
        return self::MATRIX[$from->value];
    }

    public function can(ContentItem $item, S $to): bool
    {
        return in_array($to, $this->allowed($item->status), true);
    }

    /**
     * @param  array<string, mixed>  $changes  attributes saved together with the status
     */
    public function transition(ContentItem $item, S $to, string $actor, ?string $reason = null, array $changes = []): ContentItem
    {
        return DB::transaction(function () use ($item, $to, $actor, $reason, $changes) {
            /** @var ContentItem $locked */
            $locked = ContentItem::query()->lockForUpdate()->findOrFail($item->id);
            $from = $locked->status;

            if (! in_array($to, $this->allowed($from), true)) {
                throw new InvalidTransition("Content item {$locked->id}: {$from->value} → {$to->value} is not allowed.");
            }

            $locked->fill($changes + ['status' => $to])->save();

            WorkflowEvent::query()->create([
                'client_id' => $locked->client_id,
                'subject_type' => 'content_item',
                'subject_id' => $locked->id,
                'from_status' => $from->value,
                'to_status' => $to->value,
                'actor' => mb_substr($actor, 0, 60),
                'reason' => $reason !== null ? mb_substr($reason, 0, 300) : null,
            ]);

            $item->setRawAttributes($locked->getAttributes(), true);

            return $item;
        });
    }
}
