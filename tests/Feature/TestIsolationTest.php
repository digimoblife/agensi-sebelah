<?php

use Illuminate\Support\Facades\Http;

it('pins every external service to a fake or unreachable value', function (string $key, string $expected) {
    expect((string) env($key))->toBe($expected);
})->with([
    ['DB_DATABASE', 'agensi_test'],
    ['REDIS_HOST', 'redis.invalid'],
    ['GOTENBERG_URL', 'http://gotenberg.invalid:3000'],
    ['LLM_PROVIDER', 'fake'],
    ['TELEGRAM_CLIENT', 'fake'],
    ['PUBLISHER', 'fake'],
    ['RENDERER', 'fake'],
    ['QUEUE_CONNECTION', 'sync'],
    ['CACHE_STORE', 'array'],
]);

it('blocks stray outgoing HTTP requests', function () {
    Http::get('https://api.example.com');
})->throws(RuntimeException::class);
