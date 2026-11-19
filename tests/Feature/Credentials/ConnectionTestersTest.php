<?php

use App\Services\Credentials\ConnectionTesters\MetaTester;
use App\Services\Credentials\ConnectionTesters\TelegramTester;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\Support\FakeSecrets;

it('reports the bot username when the Telegram token works', function () {
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['username' => 'agensi_bot']])]);

    $result = app(TelegramTester::class)->test(['bot_token' => FakeSecrets::telegramToken()]);

    expect($result->ok)->toBeTrue()->and($result->message)->toBe('OK, bot @agensi_bot');
});

it('maps Telegram failures to body-free error codes', function ($response, string $code) {
    Http::fake(['api.telegram.org/*' => $response]);

    $result = app(TelegramTester::class)->test(['bot_token' => FakeSecrets::telegramToken()]);

    expect($result->ok)->toBeFalse()->and($result->errorCode)->toBe($code)
        ->and($result->message)->not->toContain('description');
})->with([
    'unauthorized' => [fn () => Http::response(['ok' => false, 'description' => 'Unauthorized'], 401), '401_unauthorized'],
    'timeout' => [fn () => throw new ConnectionException('timed out'), 'timeout'],
]);

it('checks that the Meta app id and secret belong together', function () {
    Http::fake(['graph.facebook.com/v24.0/oauth/access_token' => Http::response(['access_token' => 'app|token'])]);

    $result = app(MetaTester::class)->test(['app_id' => '1234567', 'app_secret' => str_repeat('s', 32), 'graph_version' => 'v24.0']);

    expect($result->ok)->toBeTrue();
    Http::assertSent(fn ($request) => $request->method() === 'POST' && $request['grant_type'] === 'client_credentials');
});
