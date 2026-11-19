<?php

namespace App\Services\Telegram;

use App\Models\ClientContact;
use App\Models\User;
use Illuminate\Support\Str;

/** One-time /start codes (ADR 0004). Only the hash is stored. */
class Invites
{
    public const ADMIN_PREFIX = 'adm';

    public function __construct(private readonly TelegramClient $telegram) {}

    public function forContact(ClientContact $contact): string
    {
        $code = Str::random(24);
        $contact->update([
            'invite_code_hash' => hash('sha256', $code),
            'invite_expires_at' => now()->addDays((int) config('services.telegram.invite_ttl_days')),
        ]);

        return $this->link($code);
    }

    public function forAdmin(User $user): string
    {
        $code = self::ADMIN_PREFIX.Str::random(24);
        $user->forceFill([
            'telegram_invite_hash' => hash('sha256', $code),
            'telegram_invite_expires_at' => now()->addDays((int) config('services.telegram.invite_ttl_days')),
        ])->save();

        return $this->link($code);
    }

    private function link(string $code): string
    {
        $username = $this->telegram->me()['username'] ?? 'bot';

        return "https://t.me/{$username}?start={$code}";
    }
}
