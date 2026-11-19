<?php

namespace App\Services\Telegram;

/** Records everything the bot would send. Forced in tests (TELEGRAM_CLIENT=fake). */
final class FakeTelegramClient implements TelegramClient
{
    /** @var list<array{type: string, chat_id: int, text: ?string, markup: ?array<string, mixed>, photos: int}> */
    public array $sent = [];

    /** @var list<array<string, mixed>> */
    public array $updates = [];

    /** @var list<array{id: string, text: string}> */
    public array $answers = [];

    private int $messageId = 100;

    public function getUpdates(int $offset, int $timeout): array
    {
        $updates = array_values(array_filter($this->updates, fn (array $u) => $u['update_id'] >= $offset));
        $this->updates = [];

        return $updates;
    }

    public function sendMessage(int $chatId, string $text, ?array $replyMarkup = null): int
    {
        $this->sent[] = ['type' => 'message', 'chat_id' => $chatId, 'text' => $text, 'markup' => $replyMarkup, 'photos' => 0];

        return ++$this->messageId;
    }

    public function sendPhotos(int $chatId, array $photos, ?string $caption = null): int
    {
        $this->sent[] = ['type' => 'photos', 'chat_id' => $chatId, 'text' => $caption, 'markup' => null, 'photos' => count($photos)];

        return ++$this->messageId;
    }

    public function answerCallback(string $callbackId, string $text): void
    {
        $this->answers[] = ['id' => $callbackId, 'text' => $text];
    }

    public function removeKeyboard(int $chatId, int $messageId): void {}

    /** @return array{username: string} */
    public function me(): array
    {
        return ['username' => 'agensi_test_bot'];
    }

    /** @return list<string> */
    public function textsTo(int $chatId): array
    {
        return array_values(array_map(fn ($m) => (string) $m['text'], array_filter($this->sent, fn ($m) => $m['chat_id'] === $chatId)));
    }
}
