<?php

namespace App\Services\Publish;

use App\Enums\ContentStatus;
use App\Enums\PublishStatus;
use App\Jobs\PublishToPlatform;
use App\Models\ContentItem;
use App\Models\PublishJob;
use App\Services\Render\ContentRenderer;
use App\Services\Telegram\AdminNotifier;
use App\Services\Workflow\ContentWorkflow;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

/**
 * F4.2 + F9.5 + ADR 0003. Scheduling, the per-minute due run (API publish or package + reminder), catch-up for
 * missed slots, and manual upload confirmation. A failed publish alerts the admin and is never retried silently.
 */
class Publishing
{
    public function __construct(
        private readonly ContentWorkflow $workflow,
        private readonly QaPublisher $qa,
        private readonly Publisher $publisher,
        private readonly PackageBuilder $packages,
        private readonly PublishReminder $reminders,
        private readonly AdminNotifier $admins,
        private readonly ContentRenderer $renderer,
    ) {}

    public function defaultTime(ContentItem $item): CarbonImmutable
    {
        $time = $item->publish_time ?? (string) config('services.publish.default_time');

        return CarbonImmutable::parse($item->publish_date->toDateString().' '.$time, (string) ($item->client->timezone ?? 'Asia/Jakarta'))->utc();
    }

    /**
     * @return array{job: ?PublishJob, issues: list<array{severity: string, note: string}>}
     */
    public function schedule(ContentItem $item, ?CarbonImmutable $at, string $actor, bool $acceptWarnings = true): array
    {
        $ready = $item->status === ContentStatus::Rendered || ($item->status === ContentStatus::Approved && ! $this->renderer->supports($item));
        if (! $ready) {
            throw new InvalidArgumentException('Only approved content with a final visual can be scheduled.');
        }

        // Normalize at the boundary: timestamptz bindings carry no offset (see DECISIONS, ReportFlow note).
        $at = ($at ?? $this->defaultTime($item))->utc();
        $issues = $this->qa->check($item, $at);
        if (QaPublisher::blocks($issues) || (! $acceptWarnings && $issues !== [])) {
            return ['job' => null, 'issues' => $issues];
        }

        $job = DB::transaction(function () use ($item, $at, $issues, $actor) {
            $job = PublishJob::query()->create([
                'workspace_id' => $item->workspace_id,
                'client_id' => $item->client_id,
                'content_item_id' => $item->id,
                'platform' => $item->platform,
                'scheduled_at' => $at,
                'mode' => 'manual_package',
                'status' => PublishStatus::Scheduled,
                'qa' => ['issues' => $issues, 'checked_at' => now()->toIso8601String()],
            ]);
            $job->setRelation('contentItem', $item);
            $job->update(['mode' => $this->publisher->canPublish($job) ? 'api' : 'manual_package']);

            if ($item->status === ContentStatus::Approved) {
                $this->workflow->transition($item, ContentStatus::Rendered, 'system', 'Format tanpa visual');
            }
            $this->workflow->transition($item, ContentStatus::Scheduled, $actor, 'Dijadwalkan '.$at->toIso8601String());

            return $job;
        });

        return ['job' => $job, 'issues' => $issues];
    }

    /** Called every minute by the scheduler. Late jobs (Mac was off) become packages, never surprise posts. */
    public function runDue(?CarbonImmutable $now = null): int
    {
        $now = ($now ?? CarbonImmutable::now())->utc();
        $late = $now->subMinutes((int) config('services.publish.late_after_minutes'));
        $count = 0;

        $due = PublishJob::query()->with('contentItem.client')->where('status', PublishStatus::Scheduled)->where('scheduled_at', '<=', $now)->orderBy('scheduled_at')->get();
        foreach ($due as $job) {
            $count++;
            if ($job->scheduled_at->lt($late)) {
                $this->package($job, missed: true);
            } elseif ($job->mode === 'api' && $this->publisher->canPublish($job)) {
                $job->update(['status' => PublishStatus::Publishing]);
                dispatch(new PublishToPlatform($job->id));
            } else {
                $this->package($job, missed: false);
            }
        }

        return $count;
    }

    public function publishNow(PublishJob $job): void
    {
        /** @var ContentItem $item */
        $item = $job->contentItem()->with('client')->firstOrFail();

        try {
            $result = $this->publisher->publish($job);
        } catch (Throwable $e) {
            $code = $e instanceof PublishFailed ? $e->getMessage() : 'error_'.class_basename($e);
            $job->update(['status' => PublishStatus::Failed, 'error_code' => mb_substr($code, 0, 80)]);
            $this->workflow->transition($item, ContentStatus::PublishFailed, 'system', 'Gagal terbit: '.$code);
            $this->admins->send(__('bot.publish_failed', ['client' => e((string) $item->client?->name), 'angle' => e(mb_strimwidth($item->angle, 0, 60, '…')), 'code' => e($code)]));

            return;
        }

        $job->update(['status' => PublishStatus::Published, 'external_post_id' => $result['id'], 'permalink' => $result['permalink'], 'published_at' => now()]);
        $this->workflow->transition($item, ContentStatus::Published, 'system', 'Terbit di '.$job->platform);
    }

    /** Package + phone reminder. Also the manual fallback the admin can choose for a failed or scheduled post. */
    public function package(PublishJob $job, bool $missed, string $actor = 'system'): void
    {
        /** @var ContentItem $item */
        $item = $job->contentItem()->with('client')->firstOrFail();
        $local = $job->scheduled_at->setTimezone((string) ($item->client->timezone ?? 'Asia/Jakarta'))->format('d M Y H:i');

        $asset = $this->packages->build($item, $local);
        $job->update(['status' => $missed ? PublishStatus::Missed : PublishStatus::Packaged, 'package_asset_id' => $asset->id]);
        if ($item->status !== ContentStatus::Packaged) {
            $this->workflow->transition($item, ContentStatus::Packaged, $actor, $missed ? 'Jadwal terlewat: paket siap upload' : 'Paket siap upload');
        }
        $this->reminders->send($job, $missed);
    }

    public function markUploaded(PublishJob $job, string $actor): void
    {
        if (! in_array($job->status, [PublishStatus::Packaged, PublishStatus::Missed], true)) {
            throw new InvalidArgumentException('Only packaged posts can be marked as uploaded.');
        }

        DB::transaction(function () use ($job, $actor) {
            $job->update(['status' => PublishStatus::ManuallyUploaded, 'published_at' => now()]);
            /** @var ContentItem $item */
            $item = $job->contentItem()->firstOrFail();
            $this->workflow->transition($item, ContentStatus::ManuallyUploaded, $actor, 'Diupload manual');
        });
    }

    /** Admin decision after a failure: try again now (explicit, never automatic). */
    public function retry(PublishJob $job, string $actor): ?PublishJob
    {
        if ($job->status !== PublishStatus::Failed) {
            throw new InvalidArgumentException('Only failed posts can be retried.');
        }
        /** @var ContentItem $item */
        $item = $job->contentItem()->firstOrFail();
        $job->update(['status' => PublishStatus::Cancelled]);
        $this->workflow->transition($item, ContentStatus::Scheduled, $actor, 'Coba terbit ulang');

        $new = PublishJob::query()->create($job->only(['workspace_id', 'client_id', 'content_item_id', 'platform', 'mode']) + [
            'scheduled_at' => now(), 'status' => PublishStatus::Scheduled, 'qa' => $job->qa,
        ]);
        $this->runDue();

        return $new->fresh();
    }

    public function cancel(PublishJob $job, string $actor): void
    {
        if ($job->status !== PublishStatus::Scheduled) {
            throw new InvalidArgumentException('Only scheduled posts can be cancelled.');
        }
        DB::transaction(function () use ($job, $actor) {
            $job->update(['status' => PublishStatus::Cancelled]);
            /** @var ContentItem $item */
            $item = $job->contentItem()->firstOrFail();
            $this->workflow->transition($item, ContentStatus::Cancelled, $actor, 'Jadwal dibatalkan');
        });
    }
}
