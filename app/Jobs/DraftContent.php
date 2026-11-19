<?php

namespace App\Jobs;

use App\Models\Client;
use App\Models\ContentItem;
use App\Models\JobRun;
use App\Models\User;
use App\Services\Artifacts\PrerequisiteMissing;
use App\Services\Content\ContentDrafting;
use App\Services\Jobs\JobRunRecorder;
use App\Services\Llm\Exceptions\LlmGatewayException;
use App\Services\Llm\Exceptions\OutputInvalid;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** F3.1/F3.4: write (or revise) a set of content items, then run the Brand Guardian. Carries only ids. */
class DraftContent implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 900;

    /** @param  list<int>  $itemIds */
    public function __construct(public readonly int $jobRunId, public readonly int $clientId, public readonly array $itemIds, public readonly string $actor)
    {
        $this->onQueue('llm');
    }

    /** @param  list<int>  $itemIds */
    public static function request(Client $client, array $itemIds, ?User $user, string $actor): bool
    {
        sort($itemIds);
        $run = app(JobRunRecorder::class)->queue(
            'draft_content:'.$client->id.':'.substr(hash('sha256', implode(',', $itemIds)), 0, 16),
            'draft_content',
            $client->workspace_id,
            $client->id,
            $user,
        );
        if ($run === null || $itemIds === []) {
            return false;
        }

        dispatch(new self($run->id, $client->id, $itemIds, $actor));

        return true;
    }

    public function handle(ContentDrafting $drafting, JobRunRecorder $recorder): void
    {
        $run = JobRun::query()->find($this->jobRunId);
        $client = Client::query()->find($this->clientId);
        if ($run === null || $client === null) {
            return;
        }

        $recorder->start($run);
        $items = ContentItem::query()->whereIn('id', $this->itemIds)->where('client_id', $client->id)->orderBy('publish_date')->get();

        try {
            $summary = $drafting->draft($client, $items, $this->actor);
        } catch (PrerequisiteMissing $e) {
            $recorder->fail($run, 'prerequisite_missing');
            $this->notify($run, 'Draf konten belum bisa dibuat', $e->getMessage(), 'warning');

            return;
        } catch (LlmGatewayException $e) {
            $recorder->fail($run, class_basename($e), needsManual: $e instanceof OutputInvalid);
            $this->notify($run, 'Draf konten gagal dibuat', 'Lihat Konsol job dan Log panggilan AI.', 'danger');

            return;
        }

        $recorder->succeed($run, "awaiting:{$summary['awaiting_approval']},failed:{$summary['guardian_failed']}");
        $this->notify($run, 'Draf konten selesai',
            "{$summary['awaiting_approval']} menunggu persetujuan, {$summary['guardian_failed']} gagal cek Brand Guardian. Klien: {$client->name}",
            $summary['guardian_failed'] > 0 ? 'warning' : 'success');
    }

    private function notify(JobRun $run, string $title, string $body, string $status): void
    {
        $user = $run->requested_by !== null ? User::query()->find($run->requested_by) : null;
        if ($user !== null) {
            Notification::make()->title($title)->body($body)->status($status)->sendToDatabase($user);
        }
    }
}
