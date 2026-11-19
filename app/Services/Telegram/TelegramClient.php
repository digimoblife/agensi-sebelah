<?php

namespace App\Services\Telegram;

/** Outgoing Telegram Bot API calls. HttpTelegramClient in real use, FakeTelegramClient in tests. */
interface TelegramClient
{
    /** @return list<array<string, mixed>> */
    public function getUpdates(int $offset, int $timeout): array;

    /** @param  array<string, mixed>|null  $replyMarkup */
    public function sendMessage(int $chatId, string $text, ?array $replyMarkup = null): ?int;

    /**
     * Sends one photo, or an album when several are given. Returns the id of the first message.
     *
     * @param  list<array{name: string, contents: string}>  $photos
     */
    public function sendPhotos(int $chatId, array $photos, ?string $caption = null): ?int;

    public function sendDocument(int $chatId, string $name, string $contents, ?string $caption = null): ?int;

    public function answerCallback(string $callbackId, string $text): void;

    public function removeKeyboard(int $chatId, int $messageId): void;

    /** @return array{username: string}|null */
    public function me(): ?array;
}
