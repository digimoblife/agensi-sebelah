<?php

namespace App\Services\Llm\Providers;

use App\Enums\LlmProvider;
use App\Services\Credentials\ConnectionResult;
use App\Services\Llm\CompletionRequest;
use App\Services\Llm\CompletionResponse;
use App\Services\Llm\LlmProviderAdapter;
use Illuminate\Http\Client\PendingRequest;
use SensitiveParameter;

/** Gemini generateContent with a JSON schema response. Thinking tokens are billed as output. */
final class GeminiProvider implements LlmProviderAdapter
{
    use HttpProviderSupport;

    public function provider(): LlmProvider
    {
        return LlmProvider::Gemini;
    }

    public function complete(CompletionRequest $request, #[SensitiveParameter] array $credentials): CompletionResponse
    {
        $generation = ['maxOutputTokens' => $request->maxTokens];
        if ($request->jsonSchema !== null) {
            $generation['responseMimeType'] = 'application/json';
            $generation['responseJsonSchema'] = $request->jsonSchema;
        }

        $body = [
            'systemInstruction' => ['parts' => [['text' => $request->system]]],
            'contents' => [['role' => 'user', 'parts' => [
                ['text' => $request->cachedContext],
                ['text' => $request->task],
            ]]],
            'generationConfig' => $generation,
        ];

        $response = $this->send(fn () => $this->client($credentials)
            ->post('/models/'.rawurlencode($request->modelId).':generateContent', $body));

        $text = '';
        foreach ((array) $response->json('candidates.0.content.parts', []) as $part) {
            if (is_array($part) && isset($part['text']) && ! ($part['thought'] ?? false)) {
                $text .= $part['text'];
            }
        }

        $prompt = (int) $response->json('usageMetadata.promptTokenCount', 0);
        $cached = (int) $response->json('usageMetadata.cachedContentTokenCount', 0);

        return new CompletionResponse(
            text: $text,
            inputTokens: max(0, $prompt - $cached),
            cachedInputTokens: $cached,
            cacheWriteTokens: 0,
            outputTokens: (int) $response->json('usageMetadata.candidatesTokenCount', 0) + (int) $response->json('usageMetadata.thoughtsTokenCount', 0),
            stopReason: $response->json('candidates.0.finishReason'),
            modelId: (string) $response->json('modelVersion', $request->modelId),
        );
    }

    public function testConnection(#[SensitiveParameter] array $credentials): ConnectionResult
    {
        return $this->probe(fn () => $this->client($credentials, (float) config('credentials.test_timeout_seconds'))->get('/models', ['pageSize' => 1]), 'OK, API key Gemini valid.');
    }

    /** @param  array<string, string>  $credentials */
    private function client(#[SensitiveParameter] array $credentials, ?float $timeout = null): PendingRequest
    {
        return $this->http($timeout ?? (float) config('llm.timeout_seconds'))
            ->baseUrl((string) config('llm.endpoints.gemini'))
            ->withHeaders(['x-goog-api-key' => $this->requireKey($credentials)]);
    }
}
