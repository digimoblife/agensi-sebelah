<?php

namespace App\Services\Telegram;

use App\Models\User;
use Throwable;

/** Telegram messages to admins who linked their account. Best effort: never breaks the caller. */
class AdminNotifier
{
    public function __construct(private readonly TelegramClient $telegram) {}

    public function send(string $html): void
    {
        foreach (User::query()->whereNotNull('telegram_chat_id')->pluck('telegram_chat_id') as $chatId) {
            try {
                $this->telegram->sendMessage((int) $chatId, $html);
            } catch (Throwable $e) {
                report($e);
            }
        }
    }
}
