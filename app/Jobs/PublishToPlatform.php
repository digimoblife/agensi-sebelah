<?php

namespace App\Jobs;

use App\Enums\PublishStatus;
use App\Models\PublishJob;
use App\Services\Publish\Publishing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** One attempt only: a failed publish alerts the admin instead of retrying silently (PRD NFR). */
class PublishToPlatform implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 180;

    public function __construct(public readonly int $publishJobId)
    {
        $this->onQueue('publish');
    }

    public function handle(Publishing $publishing): void
    {
        $job = PublishJob::query()->find($this->publishJobId);
        if ($job !== null && $job->status === PublishStatus::Publishing) {
            $publishing->publishNow($job);
        }
    }
}
