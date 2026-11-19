<?php

namespace App\Jobs;

use App\Models\Client;
use App\Models\JobRun;
use App\Models\User;
use App\Services\Jobs\JobRunRecorder;
use App\Services\Reports\WeeklyReports;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class GenerateWeeklyReport implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(public readonly int $jobRunId, public readonly int $clientId, public readonly string $weekStart)
    {
        $this->onQueue('analytics');
    }

    public static function request(Client $client, CarbonImmutable $weekStart, ?User $user = null): bool
    {
        $run = app(JobRunRecorder::class)->queue("weekly_report:{$client->id}:{$weekStart->toDateString()}", 'weekly_report', $client->workspace_id, $client->id, $user, ['week' => $weekStart->toDateString()]);
        if ($run === null) {
            return false;
        }
        dispatch(new self($run->id, $client->id, $weekStart->toDateString()));

        return true;
    }

    public function handle(WeeklyReports $reports, JobRunRecorder $recorder): void
    {
        $run = JobRun::query()->find($this->jobRunId);
        $client = Client::query()->find($this->clientId);
        if ($run === null || $client === null) {
            return;
        }
        $recorder->start($run);

        try {
            $report = $reports->generate($client, CarbonImmutable::parse($this->weekStart, $client->timezone));
        } catch (Throwable $e) {
            report($e);
            $recorder->fail($run, class_basename($e));

            return;
        }

        $recorder->succeed($run, 'report:'.$report->id);
    }
}
