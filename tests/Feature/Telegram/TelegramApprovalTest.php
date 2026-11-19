<?php

use App\Enums\ContentStatus;
use App\Enums\CredentialProvider;
use App\Jobs\DraftContent;
use App\Models\ApprovalRequest;
use App\Models\ClientContact;
use App\Models\ContentItem;
use App\Services\Budget\BudgetService;
use App\Services\Credentials\CredentialStore;
use App\Services\Render\ContentRenderer;
use App\Services\Telegram\ApprovalSender;
use App\Services\Telegram\Invites;
use App\Services\Telegram\UpdateHandler;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakeSecrets;

beforeEach(fn () => Storage::fake('assets'));

const CHAT = 5550001;

function update(int $id, array $payload): array
{
    return ['update_id' => $id] + $payload;
}

function text(int $id, string $text, int $chat = CHAT): array
{
    return update($id, ['message' => ['message_id' => $id, 'chat' => ['id' => $chat, 'type' => 'private'], 'text' => $text]]);
}

function press(int $id, string $data, int $chat = CHAT): array
{
    return update($id, ['callback_query' => ['id' => "cb{$id}", 'data' => $data, 'from' => ['id' => $chat], 'message' => ['chat' => ['id' => $chat]]]]);
}

/** Item awaiting approval, rendered, with one linked approver; returns the approval token. */
function sentForApproval(): array
{
    $item = awaitingItem();
    $contact = ClientContact::query()->create(['workspace_id' => $item->workspace_id, 'client_id' => $item->client_id, 'name' => 'Bu Rina',
        'telegram_chat_id' => CHAT, 'linked_at' => now()]);
    app(ContentRenderer::class)->render($item);
    app(ApprovalSender::class)->send($item->fresh());

    return [$item, $contact, ApprovalRequest::query()->sole()->token];
}

it('links a client contact through a one-time invite code', function () {
    $item = awaitingItem();
    $contact = ClientContact::query()->create(['workspace_id' => $item->workspace_id, 'client_id' => $item->client_id, 'name' => 'Pak Budi']);
    $link = app(Invites::class)->forContact($contact);
    $code = substr($link, strpos($link, 'start=') + 6);

    expect($link)->toStartWith('https://t.me/agensi_test_bot?start=')
        ->and(app(UpdateHandler::class)->handle(text(1, "/start {$code}")))->toBe('linked_contact')
        ->and($contact->fresh()->telegram_chat_id)->toBe(CHAT)
        ->and(app(UpdateHandler::class)->handle(text(2, "/start {$code}", 777)))->toBe('invite_invalid');
});

it('links the admin for notifications and sends budget alerts there', function () {
    $user = admin();
    $link = app(Invites::class)->forAdmin($user);
    app(UpdateHandler::class)->handle(text(1, '/start '.substr($link, strpos($link, 'start=') + 6), 999));
    expect($user->fresh()->telegram_chat_id)->toBe(999);

    $client = client(['name' => 'Klien Alert']);
    $budget = app(BudgetService::class)->current($client);
    $budget->update(['llm_limit_usd' => 1]);
    app(BudgetService::class)->recordLlmSpend($budget, 0.85);

    expect(implode("\n", fakeTelegram()->textsTo(999)))->toContain('Klien Alert')->toContain('80%');
});

it('sends the visual, caption and decision buttons to approvers', function () {
    [$item] = sentForApproval();

    $sent = fakeTelegram()->sent;
    expect($sent[0]['type'])->toBe('photos')->and($sent[0]['photos'])->toBe(3)
        ->and($sent[1]['text'])->toContain('Konten untuk disetujui')->toContain('Tiga jam, satu meja')
        ->and($sent[1]['markup']['inline_keyboard'][0][0]['callback_data'])->toStartWith('a:')
        ->and($item->fresh()->sent_for_approval_at)->not->toBeNull();
});

it('approves from Telegram and marks the approved visual as final', function () {
    [$item, $contact, $token] = sentForApproval();

    expect(app(UpdateHandler::class)->handle(press(10, "a:{$token}")))->toBe('approved');

    $item->refresh();
    expect($item->status)->toBe(ContentStatus::Rendered)
        ->and($item->approvals()->sole()->only(['channel', 'decided_by']))->toBe(['channel' => 'telegram', 'decided_by' => "client_contact:{$contact->id}"])
        ->and(app(UpdateHandler::class)->handle(press(11, "a:{$token}")))->toBe('expired');
});

it('takes the next message as the revision comment and queues a redraft', function () {
    Queue::fake();
    [$item, , $token] = sentForApproval();

    app(UpdateHandler::class)->handle(press(10, "r:{$token}"));
    expect(app(UpdateHandler::class)->handle(text(11, 'Hook-nya kurang menarik')))->toBe('revise');

    $item->refresh();
    expect($item->status)->toBe(ContentStatus::RevisionRequested)->and($item->revision_comment)->toBe('Hook-nya kurang menarik');
    Queue::assertPushed(DraftContent::class, fn (DraftContent $job) => $job->itemIds === [$item->id]);
});

it('rejects callbacks from other chats, duplicates and stale items', function () {
    [$item, , $token] = sentForApproval();
    $handler = app(UpdateHandler::class);

    $outcomes = [$handler->handle(press(10, "a:{$token}", 123)), $handler->handle(press(10, "a:{$token}")), $handler->handle(press(11, 'a:not-a-token'))];
    expect($outcomes)->toBe(['not_allowed', 'duplicate', 'expired']);

    ContentItem::query()->whereKey($item->id)->update(['status' => ContentStatus::Cancelled]);
    expect($handler->handle(press(12, "x:{$token}")))->toBe('stale');
});

it('does not send when the client has no linked approver', function () {
    $item = awaitingItem();
    ClientContact::query()->create(['workspace_id' => $item->workspace_id, 'client_id' => $item->client_id, 'name' => 'Belum terhubung']);

    expect(app(ApprovalSender::class)->send($item))->toBe(0)->and(fakeTelegram()->sent)->toBeEmpty();
});

it('polls once and processes each update a single time', function () {
    workspace();
    app(CredentialStore::class)->save(CredentialProvider::Telegram, ['bot_token' => FakeSecrets::telegramToken()]);
    fakeTelegram()->updates = [text(41, '/start'), text(42, 'halo')];

    $this->artisan('telegram:poll --once')->assertSuccessful();

    expect(DB::table('telegram_updates')->pluck('update_id')->all())->toBe([41, 42]);
});
