<?php

namespace App\Console\Commands;

use App\Services\Ops\BackupService;
use App\Services\Ops\Heartbeat;
use App\Services\Telegram\AdminNotifier;
use Illuminate\Console\Command;
use Throwable;

class BackupRun extends Command
{
    protected $signature = 'backup:run {--verify : Also check the new backup can be read}';

    protected $description = 'Encrypted daily database backup (F9.9)';

    public function handle(BackupService $backups, AdminNotifier $admins, Heartbeat $heartbeat): int
    {
        try {
            $file = $backups->run();
            $tables = $this->option('verify') ? $backups->verify($file) : null;
        } catch (Throwable $e) {
            report($e);
            $admins->send('❗ Backup database gagal: '.e($e->getMessage()));

            return self::FAILURE;
        }

        $heartbeat->beat('backup');
        $this->components->info('Backup: '.basename($file).($tables !== null ? " ({$tables} tabel terbaca)" : ''));

        return self::SUCCESS;
    }
}
