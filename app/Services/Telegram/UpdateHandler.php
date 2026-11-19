<?php

namespace App\Services\Telegram;

use App\Enums\ContentStatus;
use App\Enums\PublishStatus;
use App\Jobs\DraftContent;
use App\Models\ApprovalRequest;
use App\Models\ClientContact;
use App\Models\ContentItem;
use App\Models\PublishJob;
use App\Models\User;
use App\Services\Content\ContentReview;
use App\Services\Publish\Publishing;
use Illuminate\Support\Facades\DB;

/**
 * Handles one Telegram update exactly once (keyed by update_id). Only linked contacts can act, only on their own
 * client's items, only through unexpired single-use tokens. Message text is never logged.
 */
class UpdateHandler
{
    public function __construct(
        private readonly TelegramClient $telegram,
        private readonly ContentReview $review,
        private readonly AdminNotifier $admins,
    ) {}

    /** @param  array<string, mixed>  $update */
    public function handle(array $update): string
    {
        $id = (int) ($update['update_id'] ?? 0);
        $kind = isset($update['callback_query']) ? 'callback_query' : (isset($update['message']) ? 'message' : 'other');

        // ON CONFLICT DO NOTHING: a repeated update is skipped without aborting the surrounding transaction.
        if (DB::table('telegram_updates')->insertOrIgnore(['update_id' => $id, 'kind' => $kind, 'processed_at' => now()]) === 0) {
            return 'duplicate';
        }

        $outcome = match ($kind) {
            'callback_query' => $this->callback((array) $update['callback_query']),
            'message' => $this->message((array) $update['message']),
            default => 'ignored',
        };

        DB::table('telegram_updates')->where('update_id', $id)->update(['outcome' => $outcome]);

        return $outcome;
    }

    /** @param  array<string, mixed>  $message */
    private function message(array $message): string
    {
        $chatId = (int) ($message['chat']['id'] ?? 0);
        $text = trim((string) ($message['text'] ?? ''));
        if ($chatId === 0 || ($message['chat']['type'] ?? 'private') !== 'private') {
            return 'ignored';
        }

        if (str_starts_with($text, '/start')) {
            return $this->start($chatId, trim(substr($text, 6)));
        }

        $pending = ApprovalRequest::query()->where('chat_id', $chatId)->whereNotNull('awaiting_comment_at')->whereNull('decided_at')
            ->where('awaiting_comment_at', '>=', now()->subDay())->latest('awaiting_comment_at')->first();
        if ($pending !== null && $text !== '') {
            return $this->revise($pending, mb_substr($text, 0, 2000));
        }

        $known = ClientContact::query()->where('telegram_chat_id', $chatId)->exists() || User::query()->where('telegram_chat_id', $chatId)->exists();
        $this->telegram->sendMessage($chatId, $known ? __('bot.start_without_code') : __('bot.unknown_chat'));

        return 'unhandled_text';
    }

    private function start(int $chatId, string $code): string
    {
        if ($code === '') {
            $this->telegram->sendMessage($chatId, __('bot.start_without_code'));

            return 'start_no_code';
        }

        $hash = hash('sha256', $code);

        if (str_starts_with($code, Invites::ADMIN_PREFIX)) {
            $user = User::query()->where('telegram_invite_hash', $hash)->where('telegram_invite_expires_at', '>', now())->first();
            if ($user !== null) {
                User::query()->where('telegram_chat_id', $chatId)->where('id', '!=', $user->id)->update(['telegram_chat_id' => null]);
                $user->forceFill(['telegram_chat_id' => $chatId, 'telegram_invite_hash' => null, 'telegram_invite_expires_at' => null])->save();
                $this->telegram->sendMessage($chatId, __('bot.linked_admin'));

                return 'linked_admin';
            }
        }

        $contact = ClientContact::query()->with('client')->where('invite_code_hash', $hash)->where('invite_expires_at', '>', now())->first();
        if ($contact === null) {
            $this->telegram->sendMessage($chatId, __('bot.invite_invalid'));

            return 'invite_invalid';
        }

        $contact->update(['telegram_chat_id' => $chatId, 'linked_at' => now(), 'invite_code_hash' => null, 'invite_expires_at' => null]);
        $this->telegram->sendMessage($chatId, __('bot.linked_contact', ['name' => e($contact->name), 'client' => e((string) $contact->client?->name)]));

        return 'linked_contact';
    }

    /** @param  array<string, mixed>  $callback */
    private function callback(array $callback): string
    {
        $callbackId = (string) ($callback['id'] ?? '');
        $chatId = (int) ($callback['message']['chat']['id'] ?? ($callback['from']['id'] ?? 0));
        [$action, $token] = array_pad(explode(':', (string) ($callback['data'] ?? ''), 2), 2, '');
        if ($action === 'u') {
            return $this->uploaded($callbackId, $chatId, $token);
        }

        $request = ApprovalRequest::query()->with(['contact', 'contentItem.client'])->where('token', $token)->first();
        if ($request === null || $request->decided_at !== null || $request->expires_at->isPast()) {
            $this->telegram->answerCallback($callbackId, __('bot.expired'));

            return 'expired';
        }

        $contact = $request->contact;
        $item = $request->contentItem;
        if ($contact === null || $item === null || $request->chat_id !== $chatId || (int) $contact->telegram_chat_id !== $chatId
            || ! $contact->can_approve || $item->client_id !== $contact->client_id) {
            $this->telegram->answerCallback($callbackId, __('bot.not_allowed'));

            return 'not_allowed';
        }

        if ($item->status !== ContentStatus::AwaitingApproval) {
            $request->update(['decision' => 'stale', 'decided_at' => now()]);
            $this->telegram->answerCallback($callbackId, __('bot.expired'));

            return 'stale';
        }

        $actor = "client_contact:{$contact->id}";

        return match ($action) {
            'a' => $this->decide($request, $callbackId, 'approved', fn () => $this->review->approve($item, $actor, 'telegram'), __('bot.approved')),
            'x' => $this->decide($request, $callbackId, 'rejected', fn () => $this->review->reject($item, null, $actor, 'telegram'), __('bot.rejected')),
            'r' => $this->askRevision($request, $callbackId),
            default => 'ignored',
        };
    }

    /** F9.5: the admin confirms a manual upload from the reminder. */
    private function uploaded(string $callbackId, int $chatId, string $token): string
    {
        $job = PublishJob::query()->where('reminder_token', $token)->first();
        $admin = User::query()->where('telegram_chat_id', $chatId)->exists();
        if ($job === null || ! $admin) {
            $this->telegram->answerCallback($callbackId, __('bot.not_allowed'));

            return 'not_allowed';
        }
        if (! in_array($job->status, [PublishStatus::Packaged, PublishStatus::Missed], true)) {
            $this->telegram->answerCallback($callbackId, __('bot.expired'));

            return 'stale';
        }

        app(Publishing::class)->markUploaded($job, "admin_chat:{$chatId}");
        $this->telegram->answerCallback($callbackId, __('bot.uploaded'));

        return 'uploaded';
    }

    private function decide(ApprovalRequest $request, string $callbackId, string $decision, callable $apply, string $reply): string
    {
        $apply();
        $request->update(['decision' => $decision, 'decided_at' => now()]);
        $this->closeOthers($request);
        if ($request->message_id !== null) {
            $this->telegram->removeKeyboard($request->chat_id, $request->message_id);
        }
        $this->telegram->answerCallback($callbackId, $reply);
        $this->telegram->sendMessage($request->chat_id, $reply);
        $this->notifyAdmins($request, $decision);

        return $decision;
    }

    private function askRevision(ApprovalRequest $request, string $callbackId): string
    {
        $request->update(['awaiting_comment_at' => now()]);
        $this->telegram->answerCallback($callbackId, __('bot.ask_revision'));
        $this->telegram->sendMessage($request->chat_id, __('bot.ask_revision'), ['force_reply' => true]);

        return 'awaiting_comment';
    }

    private function revise(ApprovalRequest $request, string $comment): string
    {
        $item = $request->contentItem;
        $contact = $request->contact;
        if ($item === null || $contact === null || $item->status !== ContentStatus::AwaitingApproval) {
            $request->update(['decision' => 'stale', 'decided_at' => now()]);
            $this->telegram->sendMessage($request->chat_id, __('bot.expired'));

            return 'stale';
        }

        $this->review->requestRevision($item, $comment, false, "client_contact:{$contact->id}", 'telegram');
        $request->update(['decision' => 'revise', 'decided_at' => now()]);
        $this->closeOthers($request);
        if ($request->message_id !== null) {
            $this->telegram->removeKeyboard($request->chat_id, $request->message_id);
        }
        if ($item->client !== null) {
            DraftContent::request($item->client, [$item->id], null, "client_contact:{$contact->id}");
        }
        $this->telegram->sendMessage($request->chat_id, __('bot.revision_received'));
        $this->notifyAdmins($request, 'revise');

        return 'revise';
    }

    /** Other contacts' buttons for the same item stop working once one person decided. */
    private function closeOthers(ApprovalRequest $request): void
    {
        ApprovalRequest::query()->where('content_item_id', $request->content_item_id)->where('id', '!=', $request->id)
            ->whereNull('decided_at')->update(['decision' => 'superseded', 'decided_at' => now()]);
    }

    private function notifyAdmins(ApprovalRequest $request, string $decision): void
    {
        /** @var ContentItem $item */
        $item = $request->contentItem;
        $this->admins->send(__('bot.admin_decision', [
            'client' => e((string) $item->client?->name),
            'contact' => e((string) $request->contact?->name),
            'decision' => __("bot.admin_decision_{$decision}"),
            'date' => $item->publish_date->format('d/m'),
            'angle' => e(mb_strimwidth($item->angle, 0, 60, '…')),
        ]));
    }
}
