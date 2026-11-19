<?php

namespace App\Services\Llm\Providers;

use App\Enums\LlmProvider;
use App\Services\Credentials\ConnectionResult;
use App\Services\Llm\CompletionRequest;
use App\Services\Llm\CompletionResponse;
use App\Services\Llm\LlmProviderAdapter;
use Illuminate\Http\Client\PendingRequest;
use SensitiveParameter;

/**
 * OpenAI Chat Completions with a JSON schema response format. `strict` stays off because strict mode requires
 * every property to be required; the gateway validates the full schema locally instead.
 */
final class OpenAiProvider implements LlmProviderAdapter
{
    use HttpProviderSupport;

    public function provider(): LlmProvider
    {
        return LlmProvider::OpenAi;
    }

    public function complete(CompletionRequest $request, #[SensitiveParameter] array $credentials): CompletionResponse
    {
        $body = [
            'model' => $request->modelId,
            'max_completion_tokens' => $request->maxTokens,
            'messages' => [
                ['role' => 'system', 'content' => $request->system],
                // Stable client context first so OpenAI's automatic prefix cache can reuse it.
                ['role' => 'user', 'content' => $request->cachedContext."\n\n".$request->task],
            ],
        ];
        if ($request->jsonSchema !== null) {
            $body['response_format'] = ['type' => 'json_schema', 'json_schema' => [
                'name' => str_replace('.', '_', $request->schemaName), 'schema' => $request->jsonSchema, 'strict' => false,
            ]];
        }

        $response = $this->send(fn () => $this->client($credentials)->post('/chat/completions', $body));

        $prompt = (int) $response->json('usage.prompt_tokens', 0);
        $cached = (int) $response->json('usage.prompt_tokens_details.cached_tokens', 0);

        return new CompletionResponse(
            text: (string) $response->json('choices.0.message.content', ''),
            inputTokens: max(0, $prompt - $cached),
            cachedInputTokens: $cached,
            cacheWriteTokens: 0,
            outputTokens: (int) $response->json('usage.completion_tokens', 0),
            stopReason: $response->json('choices.0.finish_reason'),
            modelId: (string) $response->json('model', $request->modelId),
        );
    }

    public function testConnection(#[SensitiveParameter] array $credentials): ConnectionResult
    {
        return $this->probe(fn () => $this->client($credentials, (float) config('credentials.test_timeout_seconds'))->get('/models'), 'OK, API key OpenAI valid.');
    }

    /** @param  array<string, string>  $credentials */
    private function client(#[SensitiveParameter] array $credentials, ?float $timeout = null): PendingRequest
    {
        $request = $this->http($timeout ?? (float) config('llm.timeout_seconds'))
            ->baseUrl((string) config('llm.endpoints.openai'))
            ->withToken($this->requireKey($credentials));

        return ($credentials['organization'] ?? '') !== ''
            ? $request->withHeaders(['OpenAI-Organization' => $credentials['organization']])
            : $request;
    }
}
