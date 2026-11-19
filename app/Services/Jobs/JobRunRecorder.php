<?php

namespace App\Services\Jobs;

use App\Enums\JobRunStatus;
use App\Models\JobRun;
use App\Models\User;

/** ADR 0003 / F9.6: the Postgres record of a job, independent of Redis. */
class JobRunRecorder
{
    /** Creates the run when the job is requested. Returns null if the same key is already queued or running. */
    /** @param  array<string, mixed>  $options */
    public function queue(string $key, string $job, ?int $workspaceId, ?int $clientId, ?User $requestedBy, array $options = []): ?JobRun
    {
        $existing = JobRun::query()->where('idempotency_key', $key)->first();
        if ($existing !== null && in_array($existing->status, [JobRunStatus::Queued, JobRunStatus::Running], true)) {
            return null;
        }

        return JobRun::query()->updateOrCreate(['idempotency_key' => $key], [
            'job' => $job,
            'options' => $options === [] ? null : $options,
            'workspace_id' => $workspaceId,
            'client_id' => $clientId,
            'status' => JobRunStatus::Queued,
            'error_code' => null,
            'result_ref' => null,
            'requested_by' => $requestedBy?->id,
            'started_at' => null,
            'finished_at' => null,
        ]);
    }

    public function start(JobRun $run): void
    {
        $run->update(['status' => JobRunStatus::Running, 'attempts' => $run->attempts + 1, 'started_at' => now()]);
    }

    public function succeed(JobRun $run, ?string $resultRef = null): void
    {
        $run->update(['status' => JobRunStatus::Succeeded, 'result_ref' => $resultRef, 'finished_at' => now()]);
    }

    public function fail(JobRun $run, string $errorCode, bool $needsManual = false): void
    {
        $run->update([
            'status' => $needsManual ? JobRunStatus::NeedsManual : JobRunStatus::Failed,
            'error_code' => mb_substr($errorCode, 0, 120),
            'finished_at' => now(),
        ]);
    }
}
