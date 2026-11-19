<?php

namespace App\Console\Commands;

use App\Enums\ClientStatus;
use App\Models\Client;
use App\Services\Ops\Check;
use App\Services\Ops\PilotReadiness;
use Illuminate\Console\Command;

/** Week 6: is everything in place for the pilot? Exit code 1 when something blocks it. */
class PilotCheck extends Command
{
    protected $signature = 'pilot:check {client? : client id or slug (default: all non-archived)}';

    protected $description = 'Check system and client readiness for the pilot';

    public function handle(PilotReadiness $readiness): int
    {
        $checks = $readiness->system();
        $clients = Client::query()->where('status', '!=', ClientStatus::Archived)
            ->when($this->argument('client'), fn ($q, $c) => $q->where(fn ($q) => $q->where('slug', $c)->orWhere('id', is_numeric($c) ? (int) $c : 0)))->get();
        foreach ($clients as $client) {
            $checks = [...$checks, ...$readiness->client($client)];
        }

        $this->table(['', 'Kelompok', 'Cek', 'Detail', 'Langkah'], array_map(fn (Check $c) => [
            ['ok' => '✓', 'warn' => '!', 'fail' => '✗'][$c->status], $c->group, $c->label, $c->detail, $c->hint,
        ], $checks));

        $blocking = PilotReadiness::blocking($checks);
        $blocking ? $this->components->error('Belum siap: perbaiki baris ✗.') : $this->components->info('Siap untuk pilot (baris ! boleh menyusul).');

        return $blocking ? self::FAILURE : self::SUCCESS;
    }
}
