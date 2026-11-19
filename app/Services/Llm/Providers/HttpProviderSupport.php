<?php

namespace App\Services\Llm\Providers;

use App\Services\Credentials\ConnectionResult;
use App\Services\Credentials\HttpErrorCode;
use App\Services\Llm\Exceptions\ProviderException;
use App\Services\Llm\ProviderErrorCode;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/** Shared plumbing for the providers called over plain HTTP (OpenAI, Gemini, DeepSeek). */
trait HttpProviderSupport
{
    /** @param  callable(): Response  $send */
    private function send(callable $send): Response
    {
        try {
            $response = $send();
        } catch (ConnectionException $e) {
            throw new ProviderException(ProviderErrorCode::Timeout, $e);
        } catch (Throwable $e) {
            throw new ProviderException(ProviderErrorCode::Connection, $e);
        }

        if ($response->successful()) {
            return $response;
        }

        throw new ProviderException(match (true) {
            in_array($response->status(), [401, 403], true) => ProviderErrorCode::Unauthorized,
            $response->status() === 402 => ProviderErrorCode::QuotaExceeded,
            $response->status() === 429 => ProviderErrorCode::RateLimited,
            in_array($response->status(), [503, 529], true) => ProviderErrorCode::Overloaded,
            $response->status() >= 500 => ProviderErrorCode::ServerError,
            default => ProviderErrorCode::BadRequest,
        });
    }

    /** @param  callable(): Response  $send */
    private function probe(callable $send, string $okMessage): ConnectionResult
    {
        try {
            $response = $send();
        } catch (Throwable $e) {
            return HttpErrorCode::fromThrowable($e);
        }

        return $response->successful() ? ConnectionResult::ok($okMessage) : HttpErrorCode::fromResponse($response);
    }

    private function http(float $timeout): PendingRequest
    {
        return Http::timeout((int) ceil($timeout))->acceptJson()->asJson();
    }

    /** @param  array<string, string>  $credentials */
    private function requireKey(array $credentials): string
    {
        $key = $credentials['api_key'] ?? '';
        if ($key === '') {
            throw new ProviderException(ProviderErrorCode::MissingCredentials);
        }

        return $key;
    }
}
