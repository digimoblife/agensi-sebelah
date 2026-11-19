<?php

namespace App\Console\Commands;

use App\Enums\CredentialProvider;
use App\Services\Credentials\CredentialStore;
use App\Services\Telegram\TelegramClient;
use App\Services\Telegram\UpdateHandler;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/** ADR 0004: long polling, so the bot works from a laptop without a public address. */
class TelegramPoll extends Command
{
    protected $signature = 'telegram:poll {--once : Fetch one batch and exit}';

    protected $description = 'Receive Telegram updates (client approvals, invite links) by long polling';

    public function handle(TelegramClient $telegram, UpdateHandler $handler, CredentialStore $credentials): int
    {
        do {
            if (! $credentials->isComplete(CredentialProvider::Telegram)) {
                $this->components->warn('Telegram bot token is not configured; waiting.');
                if ($this->option('once')) {
                    return self::SUCCESS;
                }
                sleep(30);

                continue;
            }

            $offset = (int) DB::table('telegram_updates')->max('update_id') + 1;
            try {
                $updates = $telegram->getUpdates($offset, (int) config('services.telegram.poll_timeout'));
            } catch (Throwable $e) {
                report($e);
                if ($this->option('once')) {
                    return self::FAILURE;
                }
                sleep(5);

                continue;
            }

            foreach ($updates as $update) {
                try {
                    $handler->handle($update);
                } catch (Throwable $e) {
                    report($e); // one bad update must not stop the bot
                }
            }
        } while (! $this->option('once'));

        return self::SUCCESS;
    }
}
