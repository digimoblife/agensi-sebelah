<?php

namespace App\Services\Llm\Providers;

use Anthropic\Beta\AnthropicBeta;
use Anthropic\Beta\Messages\BetaTextBlock;
use Anthropic\Client;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Core\Exceptions\APITimeoutException;
use Anthropic\Core\Exceptions\AuthenticationException;
use Anthropic\Core\Exceptions\BadRequestException;
use Anthropic\Core\Exceptions\InternalServerException;
use Anthropic\Core\Exceptions\OverloadedException;
use Anthropic\Core\Exceptions\PaymentRequiredException;
use Anthropic\Core\Exceptions\PermissionDeniedException;
use Anthropic\Core\Exceptions\RateLimitException;
use Anthropic\Messages\TextBlock;
use Anthropic\RequestOptions;
use App\Enums\LlmProvider;
use App\Services\Credentials\ConnectionResult;
use App\Services\Llm\CompletionRequest;
use App\Services\Llm\CompletionResponse;
use App\Services\Llm\Exceptions\ProviderException;
use App\Services\Llm\LlmProviderAdapter;
use App\Services\Llm\ProviderErrorCode;
use Psr\Http\Client\ClientInterface;
use SensitiveParameter;
use Throwable;

/**
 * Claude via the official Anthropic PHP SDK. Retries are disabled here: the gateway owns retry and fallback so
 * every attempt is logged and budgeted.
 */
final class AnthropicProvider implements LlmProviderAdapter
{
    public function __construct(private readonly ?ClientInterface $transporter = null) {}

    public function provider(): LlmProvider
    {
        return LlmProvider::Anthropic;
    }

    public function complete(CompletionRequest $request, #[SensitiveParameter] array $credentials): CompletionResponse
    {
        $client = $this->client($credentials, (float) config('llm.timeout_seconds'));

        $system = [['type' => 'text', 'text' => $request->system]];
        if ($request->cachedContext !== '') {
            // Breakpoint after the stable client context; short prefixes simply do not cache.
            $system[] = ['type' => 'text', 'text' => $request->cachedContext, 'cacheControl' => ['type' => 'ephemeral']];
        }

        $outputConfig = [];
        if ($request->jsonSchema !== null) {
            $outputConfig['format'] = ['type' => 'json_schema', 'schema' => $request->jsonSchema];
        }
        $effort = match ($request->effort) {
            'low', 'medium', 'high', 'xhigh', 'max' => $request->effort,
            default => null,
        };
        if ($effort !== null) {
            $outputConfig['effort'] = $effort;
        }

        $params = [
            'model' => $request->modelId,
            'maxTokens' => $request->maxTokens,
            'system' => $system,
            'messages' => [['role' => 'user', 'content' => $request->task]],
            'outputConfig' => $outputConfig === [] ? null : $outputConfig,
        ];

        try {
            $message = $request->serverFallback
                // Server-side fallback on policy refusals, routed by refusal category.
                ? $client->beta->messages->create(...$params, betas: [AnthropicBeta::SERVER_SIDE_FALLBACK_2026_07_01], fallbacks: 'default')
                : $client->messages->create(...$params);
        } catch (Throwable $e) {
            throw new ProviderException($this->errorCode($e), $e);
        }

        if ($message->stopReason === 'refusal') {
            throw new ProviderException(ProviderErrorCode::Refused);
        }

        $text = '';
        foreach ($message->content as $block) {
            if ($block instanceof TextBlock || $block instanceof BetaTextBlock) {
                $text .= $block->text;
            }
        }

        return new CompletionResponse(
            text: $text,
            inputTokens: $message->usage->inputTokens,
            cachedInputTokens: $message->usage->cacheReadInputTokens ?? 0,
            cacheWriteTokens: $message->usage->cacheCreationInputTokens ?? 0,
            outputTokens: $message->usage->outputTokens,
            stopReason: $message->stopReason,
            modelId: $message->model,
        );
    }

    public function testConnection(#[SensitiveParameter] array $credentials): ConnectionResult
    {
        try {
            $this->client($credentials, (float) config('credentials.test_timeout_seconds'))->models->list(limit: 1);
        } catch (Throwable $e) {
            return match ($this->errorCode($e)) {
                ProviderErrorCode::Unauthorized => ConnectionResult::failed('401_unauthorized', 'API key ditolak oleh Anthropic.'),
                ProviderErrorCode::Timeout, ProviderErrorCode::Connection => ConnectionResult::failed('timeout', 'Tidak bisa terhubung ke Anthropic.'),
                default => ConnectionResult::failed($this->errorCode($e)->value, 'Tes koneksi ke Anthropic gagal.'),
            };
        }

        return ConnectionResult::ok('OK, API key Anthropic valid.');
    }

    /** @param  array<string, string>  $credentials */
    private function client(#[SensitiveParameter] array $credentials, float $timeout): Client
    {
        if (($credentials['api_key'] ?? '') === '') {
            throw new ProviderException(ProviderErrorCode::MissingCredentials);
        }

        return new Client(
            apiKey: $credentials['api_key'],
            requestOptions: RequestOptions::with(timeout: $timeout, maxRetries: 0, transporter: $this->transporter),
            openTelemetry: false,
        );
    }

    private function errorCode(Throwable $e): ProviderErrorCode
    {
        return match (true) {
            $e instanceof ProviderException => $e->errorCode,
            $e instanceof AuthenticationException, $e instanceof PermissionDeniedException => ProviderErrorCode::Unauthorized,
            $e instanceof RateLimitException => ProviderErrorCode::RateLimited,
            $e instanceof PaymentRequiredException => ProviderErrorCode::QuotaExceeded,
            $e instanceof OverloadedException => ProviderErrorCode::Overloaded,
            $e instanceof InternalServerException => ProviderErrorCode::ServerError,
            $e instanceof BadRequestException => ProviderErrorCode::BadRequest,
            $e instanceof APITimeoutException => ProviderErrorCode::Timeout,
            $e instanceof APIConnectionException => ProviderErrorCode::Connection,
            $e instanceof APIStatusException => ProviderErrorCode::ServerError,
            default => ProviderErrorCode::Unknown,
        };
    }
}
