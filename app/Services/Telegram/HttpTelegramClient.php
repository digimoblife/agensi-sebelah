<?php

namespace App\Services\Telegram;

use App\Enums\CredentialProvider;
use App\Services\Credentials\CredentialStore;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Telegram Bot API over HTTPS. The token comes from Pengaturan > Kredensial; it is never logged. */
final class HttpTelegramClient implements TelegramClient
{
    public function __construct(private readonly CredentialStore $credentials) {}

    public function getUpdates(int $offset, int $timeout): array
    {
        $response = $this->http($timeout + 10)->post('getUpdates', [
            'offset' => $offset, 'timeout' => $timeout, 'allowed_updates' => ['message', 'callback_query'],
        ]);

        return array_values((array) $this->ok($response, 'getUpdates'));
    }

    public function sendMessage(int $chatId, string $text, ?array $replyMarkup = null): ?int
    {
        $result = $this->ok($this->http()->post('sendMessage', array_filter([
            'chat_id' => $chatId, 'text' => $text, 'parse_mode' => 'HTML', 'link_preview_options' => ['is_disabled' => true],
            'reply_markup' => $replyMarkup,
        ])), 'sendMessage');

        return isset($result['message_id']) ? (int) $result['message_id'] : null;
    }

    public function sendPhotos(int $chatId, array $photos, ?string $caption = null): ?int
    {
        if (count($photos) === 1) {
            $request = $this->http(60)->attach('photo', $photos[0]['contents'], $photos[0]['name']);
            $result = $this->ok($request->post('sendPhoto', array_filter(['chat_id' => $chatId, 'caption' => $caption])), 'sendPhoto');

            return isset($result['message_id']) ? (int) $result['message_id'] : null;
        }

        $request = $this->http(90);
        $media = [];
        foreach (array_slice($photos, 0, 10) as $i => $photo) {
            $request = $request->attach("p{$i}", $photo['contents'], $photo['name']);
            $media[] = array_filter(['type' => 'photo', 'media' => "attach://p{$i}", 'caption' => $i === 0 ? $caption : null]);
        }
        $result = $this->ok($request->post('sendMediaGroup', ['chat_id' => $chatId, 'media' => json_encode($media)]), 'sendMediaGroup');

        return isset($result[0]['message_id']) ? (int) $result[0]['message_id'] : null;
    }

    public function sendDocument(int $chatId, string $name, string $contents, ?string $caption = null): ?int
    {
        $result = $this->ok($this->http(60)->attach('document', $contents, $name)
            ->post('sendDocument', array_filter(['chat_id' => $chatId, 'caption' => $caption])), 'sendDocument');

        return isset($result['message_id']) ? (int) $result['message_id'] : null;
    }

    public function answerCallback(string $callbackId, string $text): void
    {
        $this->http()->post('answerCallbackQuery', ['callback_query_id' => $callbackId, 'text' => $text]);
    }

    public function removeKeyboard(int $chatId, int $messageId): void
    {
        $this->http()->post('editMessageReplyMarkup', ['chat_id' => $chatId, 'message_id' => $messageId, 'reply_markup' => ['inline_keyboard' => []]]);
    }

    public function me(): ?array
    {
        $result = $this->ok($this->http()->get('getMe'), 'getMe');

        return isset($result['username']) ? ['username' => (string) $result['username']] : null;
    }

    private function http(int $timeout = 20): PendingRequest
    {
        $token = $this->credentials->get(CredentialProvider::Telegram, 'bot_token')
            ?? throw new RuntimeException('Telegram bot token is not configured.');

        return Http::timeout($timeout)->baseUrl("https://api.telegram.org/bot{$token}/");
    }

    /** @return array<mixed> */
    private function ok(Response $response, string $method): array
    {
        if (! $response->successful() || $response->json('ok') !== true) {
            // Status only: Telegram error descriptions can echo message text.
            throw new RuntimeException("Telegram {$method} failed (HTTP {$response->status()}).");
        }

        return (array) $response->json('result');
    }
}
