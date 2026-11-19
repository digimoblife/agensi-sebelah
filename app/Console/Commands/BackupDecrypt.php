<?php

namespace App\Console\Commands;

use App\Services\Ops\BackupService;
use Illuminate\Console\Command;

/** Manual restore helper (docs/RUNBOOK-OPS.md). Writes a plain pg_dump file; the restore itself is manual. */
class BackupDecrypt extends Command
{
    protected $signature = 'backup:decrypt {file? : backup file (default: newest)} {--out=/tmp/agensi-restore.dump}';

    protected $description = 'Decrypt a backup into a pg_dump file for pg_restore';

    public function handle(BackupService $backups): int
    {
        $file = $this->argument('file') ?? $backups->latest();
        if (! is_string($file)) {
            $this->components->error('No backup found.');

            return self::FAILURE;
        }
        $backups->decrypt($file, (string) $this->option('out'));
        $this->components->info('Ditulis ke '.$this->option('out'));

        return self::SUCCESS;
    }
}
