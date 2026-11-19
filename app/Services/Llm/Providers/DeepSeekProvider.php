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
 * DeepSeek's OpenAI-compatible API. It offers JSON mode but not schema-constrained output, so the schema is put
 * in the prompt and the gateway validates the result.
 */
final class DeepSeekProvider implements LlmProviderAdapter
{
    use HttpProviderSupport;

    public function provider(): LlmProvider
    {
        return LlmProvider::DeepSeek;
    }

    public function complete(CompletionRequest $request, #[SensitiveParameter] array $credentials): CompletionResponse
    {
        $task = $request->task;
        if ($request->jsonSchema !== null) {
            $task .= "\n\nBalas hanya dengan satu objek JSON yang sesuai JSON Schema berikut:\n"
                .json_encode($request->jsonSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $body = [
            'model' => $request->modelId,
            'max_tokens' => $request->maxTokens,
            'messages' => [
                ['role' => 'system', 'content' => $request->system],
                ['role' => 'user', 'content' => $request->cachedContext."\n\n".$task],
            ],
        ];
        if ($request->jsonSchema !== null) {
            $body['response_format'] = ['type' => 'json_object'];
        }

        $response = $this->send(fn () => $this->client($credentials)->post('/chat/completions', $body));

        $hit = (int) $response->json('usage.prompt_cache_hit_tokens', 0);
        $miss = $response->json('usage.prompt_cache_miss_tokens');

        return new CompletionResponse(
            text: (string) $response->json('choices.0.message.content', ''),
            inputTokens: $miss !== null ? (int) $miss : max(0, (int) $response->json('usage.prompt_tokens', 0) - $hit),
            cachedInputTokens: $hit,
            cacheWriteTokens: 0,
            outputTokens: (int) $response->json('usage.completion_tokens', 0),
            stopReason: $response->json('choices.0.finish_reason'),
            modelId: (string) $response->json('model', $request->modelId),
        );
    }

    public function testConnection(#[SensitiveParameter] array $credentials): ConnectionResult
    {
        return $this->probe(fn () => $this->client($credentials, (float) config('credentials.test_timeout_seconds'))->get('/models'), 'OK, API key DeepSeek valid.');
    }

    /** @param  array<string, string>  $credentials */
    private function client(#[SensitiveParameter] array $credentials, ?float $timeout = null): PendingRequest
    {
        return $this->http($timeout ?? (float) config('llm.timeout_seconds'))
            ->baseUrl(rtrim($credentials['base_url'] ?? (string) config('llm.endpoints.deepseek'), '/'))
            ->withToken($this->requireKey($credentials));
    }
}
