<?php

namespace App\Services\Credentials\ConnectionTesters;

use App\Services\Credentials\ConnectionResult;
use App\Services\Credentials\ConnectionTester;
use App\Services\Credentials\HttpErrorCode;
use Illuminate\Support\Facades\Http;
use Throwable;

/** Requests an app access token (client credentials) to prove App ID + App Secret belong together. */
final class MetaTester implements ConnectionTester
{
    public function test(array $values): ConnectionResult
    {
        try {
            $response = Http::timeout((int) config('credentials.test_timeout_seconds'))
                ->asForm()
                ->post("https://graph.facebook.com/{$values['graph_version']}/oauth/access_token", [
                    'client_id' => $values['app_id'],
                    'client_secret' => $values['app_secret'],
                    'grant_type' => 'client_credentials',
                ]);
        } catch (Throwable $e) {
            return HttpErrorCode::fromThrowable($e);
        }

        if (! $response->successful() || ! is_string($response->json('access_token'))) {
            return HttpErrorCode::fromResponse($response);
        }

        return ConnectionResult::ok('OK, App ID dan App Secret cocok.');
    }
}
