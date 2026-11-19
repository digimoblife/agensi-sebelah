<?php

namespace App\Console\Commands;

use App\Enums\ClientStatus;
use App\Models\Client;
use App\Services\Analytics\MetricsPuller;
use Illuminate\Console\Command;

class MetricsPull extends Command
{
    protected $signature = 'metrics:pull {client? : client id}';

    protected $description = 'Pull Instagram and GA4 metrics up to yesterday (catches up on missed days)';

    public function handle(MetricsPuller $puller): int
    {
        $clients = Client::query()->where('status', ClientStatus::Active)
            ->when($this->argument('client'), fn ($q, $id) => $q->whereKey($id))->get();
        foreach ($clients as $client) {
            foreach ($puller->pull($client) as $source => $outcome) {
                $this->line("{$client->name} · {$source}: {$outcome}");
            }
        }

        return self::SUCCESS;
    }
}
