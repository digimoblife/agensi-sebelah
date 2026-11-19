<?php

namespace App\Console\Commands;

use App\Services\Publish\Publishing;
use Illuminate\Console\Command;

class PublishDue extends Command
{
    protected $signature = 'publish:due';

    protected $description = 'Publish or package posts whose time has come (catches up after the Mac was asleep)';

    public function handle(Publishing $publishing): int
    {
        $count = $publishing->runDue();
        if ($count > 0) {
            $this->components->info("{$count} jadwal diproses.");
        }

        return self::SUCCESS;
    }
}
