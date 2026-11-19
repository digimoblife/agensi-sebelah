<?php

namespace App\Console\Commands;

use App\Enums\ClientStatus;
use App\Jobs\GenerateWeeklyReport;
use App\Models\Client;
use App\Services\Analytics\MetricsPuller;
use App\Services\Reports\WeeklyReports;
use Illuminate\Console\Command;

/** Runs hourly: any active client without last week's report gets one (catch-up after downtime). */
class ReportsWeekly extends Command
{
    protected $signature = 'reports:weekly';

    protected $description = 'Queue last week\'s report for every active client that does not have one yet';

    public function handle(WeeklyReports $reports, MetricsPuller $puller): int
    {
        foreach (Client::query()->where('status', ClientStatus::Active)->get() as $client) {
            // No connected data source = nothing to report; do not spend on an empty report.
            if (collect($puller->sources())->doesntContain(fn ($source) => $source->isConnected($client))) {
                continue;
            }
            $week = $reports->lastWeekStart($client);
            if (! $reports->exists($client, $week) && GenerateWeeklyReport::request($client, $week)) {
                $this->line("Queued {$client->name} {$week->toDateString()}");
            }
        }

        return self::SUCCESS;
    }
}
