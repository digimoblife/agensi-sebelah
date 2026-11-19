<?php

namespace App\Services\Publish;

use App\Models\Asset;
use App\Models\PublishJob;
use App\Models\User;
use App\Services\Assets\AssetLibrary;
use App\Services\Telegram\TelegramClient;
use Illuminate\Support\Str;
use Throwable;

/**
 * F9.5 reminder on the admin's phone: the images, the caption as its own message (easy to copy), and a
 * "Sudah diupload" button. Best effort per admin chat.
 */
class PublishReminder
{
    public function __construct(private readonly TelegramClient $telegram, private readonly AssetLibrary $assets, private readonly QaPublisher $qa) {}

    public function send(PublishJob $job, bool $missed): int
    {
        $item = $job->contentItem;
        if ($item === null) {
            return 0;
        }

        $token = $job->reminder_token ?? Str::random(24);
        $job->update(['reminder_token' => $token, 'reminded_at' => now()]);

        $photos = array_values(Asset::query()->whereIn('ulid', $item->render['assets'] ?? [])->get()
            ->sortBy(fn (Asset $a) => array_search($a->ulid, $item->render['assets'] ?? [], true))
            ->map(fn (Asset $a) => ['name' => $a->ulid.'.png', 'contents' => $this->assets->contents($a)])->values()->all());

        $header = __($missed ? 'bot.reminder_missed' : 'bot.reminder_due', [
            'client' => e((string) $item->client?->name),
            'platform' => e(Str::headline($job->platform)),
            'format' => e(str_replace('_', ' ', $item->format)),
            'time' => $job->scheduled_at->setTimezone((string) ($item->client->timezone ?? 'Asia/Jakarta'))->format('d/m H:i'),
        ]);

        $sent = 0;
        foreach (User::query()->whereNotNull('telegram_chat_id')->pluck('telegram_chat_id') as $chatId) {
            try {
                if ($photos !== []) {
                    $this->telegram->sendPhotos((int) $chatId, $photos);
                }
                $this->telegram->sendMessage((int) $chatId, $header);
                $this->telegram->sendMessage((int) $chatId, e($this->qa->caption($item)), ['inline_keyboard' => [
                    [['text' => __('bot.btn_uploaded'), 'callback_data' => "u:{$token}"]],
                ]]);
                $sent++;
            } catch (Throwable $e) {
                report($e);
            }
        }

        return $sent;
    }
}
