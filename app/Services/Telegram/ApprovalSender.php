<?php

namespace App\Services\Telegram;

use App\Enums\ContentStatus;
use App\Models\ApprovalRequest;
use App\Models\Asset;
use App\Models\ClientContact;
use App\Models\ContentItem;
use App\Services\Assets\AssetLibrary;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/** F4.1: sends a content item (visual + caption + buttons) to every approving contact of its client. */
class ApprovalSender
{
    public function __construct(private readonly TelegramClient $telegram, private readonly AssetLibrary $assets) {}

    /** @return int number of contacts reached */
    public function send(ContentItem $item): int
    {
        if ($item->status !== ContentStatus::AwaitingApproval || $item->draft === null) {
            return 0;
        }

        $contacts = ClientContact::query()->where(['client_id' => $item->client_id, 'can_approve' => true])->whereNotNull('telegram_chat_id')->get();
        if ($contacts->isEmpty()) {
            return 0;
        }

        $photos = Asset::query()->whereIn('ulid', $item->render['assets'] ?? [])->get()
            ->sortBy(fn (Asset $a) => array_search($a->ulid, $item->render['assets'] ?? [], true))
            ->map(fn (Asset $a) => ['name' => $a->ulid.'.png', 'contents' => $this->assets->contents($a)])->values()->all();
        $photos = array_values($photos);

        $text = $this->text($item);
        $sent = 0;
        foreach ($contacts as $contact) {
            // A newer version replaces older pending requests for the same item and contact.
            ApprovalRequest::query()->where(['content_item_id' => $item->id, 'client_contact_id' => $contact->id])
                ->whereNull('decided_at')->update(['decision' => 'superseded', 'decided_at' => now()]);

            $request = ApprovalRequest::query()->create([
                'token' => Str::random(24),
                'content_item_id' => $item->id,
                'client_contact_id' => $contact->id,
                'chat_id' => (int) $contact->telegram_chat_id,
                'expires_at' => now()->addDays((int) config('services.telegram.approval_ttl_days')),
            ]);

            if ($photos !== []) {
                $this->telegram->sendPhotos((int) $contact->telegram_chat_id, $photos);
            }
            $messageId = $this->telegram->sendMessage((int) $contact->telegram_chat_id, $text, ['inline_keyboard' => [
                [['text' => __('bot.btn_approve'), 'callback_data' => "a:{$request->token}"], ['text' => __('bot.btn_revise'), 'callback_data' => "r:{$request->token}"]],
                [['text' => __('bot.btn_reject'), 'callback_data' => "x:{$request->token}"]],
            ]]);
            $request->update(['message_id' => $messageId]);
            $sent++;
        }

        $item->forceFill(['sent_for_approval_at' => now()])->save();

        return $sent;
    }

    private function date(ContentItem $item): string
    {
        /** @var CarbonImmutable $local */
        $local = $item->publish_date->locale('id');

        return $local->translatedFormat('l, j F Y').($item->publish_time ? ' '.$item->publish_time : '');
    }

    private function text(ContentItem $item): string
    {
        $draft = (array) $item->draft;
        $text = __('bot.approval_header', [
            'client' => e((string) $item->client?->name),
            'date' => $this->date($item),
            'platform' => e(Str::headline($item->platform)),
            'format' => e(str_replace('_', ' ', $item->format)),
        ]);
        $text .= __('bot.approval_caption', ['caption' => e((string) ($draft['caption'] ?? ''))]);
        if (! empty($draft['hashtags'])) {
            $text .= __('bot.approval_hashtags', ['hashtags' => e(implode(' ', (array) $draft['hashtags']))]);
        }
        if ($item->render['needs_video'] ?? false) {
            $text .= __('bot.approval_video_note');
        }

        return mb_substr($text, 0, 4000);
    }
}
