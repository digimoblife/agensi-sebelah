<?php

namespace App\Services\Credentials\ConnectionTesters;

use App\Services\Credentials\ConnectionResult;
use App\Services\Credentials\ConnectionTester;
use App\Services\Credentials\HttpErrorCode;
use Illuminate\Support\Facades\Http;
use Throwable;

final class TelegramTester implements ConnectionTester
{
    public function test(array $values): ConnectionResult
    {
        try {
            $response = Http::timeout((int) config('credentials.test_timeout_seconds'))
                ->get('https://api.telegram.org/bot'.$values['bot_token'].'/getMe');
        } catch (Throwable $e) {
            return HttpErrorCode::fromThrowable($e);
        }

        if (! $response->successful() || $response->json('ok') !== true) {
            return HttpErrorCode::fromResponse($response);
        }

        return ConnectionResult::ok('OK, bot @'.$response->json('result.username'));
    }
}
