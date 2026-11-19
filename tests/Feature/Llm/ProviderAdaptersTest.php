<?php

use App\Enums\LlmProvider;
use App\Services\Llm\CompletionRequest;
use App\Services\Llm\Exceptions\ProviderException;
use App\Services\Llm\ProviderErrorCode;
use App\Services\Llm\Providers\AnthropicProvider;
use App\Services\Llm\Providers\DeepSeekProvider;
use App\Services\Llm\Providers\GeminiProvider;
use App\Services\Llm\Providers\OpenAiProvider;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Http;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Tests\Support\FakeSecrets;

function completion(array $overrides = []): CompletionRequest
{
    return new CompletionRequest(...$overrides + [
        'modelId' => 'test-model',
        'system' => 'SYSTEM',
        'cachedContext' => 'CONTEXT',
        'task' => 'TASK',
        'jsonSchema' => ['type' => 'object', 'properties' => ['a' => ['type' => 'string']], 'required' => ['a'], 'additionalProperties' => false],
        'schemaName' => 'test.v1',
        'maxTokens' => 1000,
    ]);
}

/** PSR-18 transport that records the request and returns a canned response. */
function anthropicTransport(int $status, array $body): ClientInterface
{
    return new class($status, $body) implements ClientInterface
    {
        public ?RequestInterface $request = null;

        public function __construct(private int $status, private array $body) {}

        public function sendRequest(RequestInterface $request): ResponseInterface
        {
            $this->request = $request;

            return new Response($this->status, ['Content-Type' => 'application/json', 'request-id' => 'req_test'], json_encode($this->body));
        }
    };
}

it('sends Claude a cached system prefix and JSON schema output, and normalizes usage', function () {
    $transport = anthropicTransport(200, [
        'id' => 'msg_1', 'type' => 'message', 'role' => 'assistant', 'model' => 'claude-opus-5-5',
        'content' => [['type' => 'text', 'text' => '{"a":"b"}']],
        'stop_reason' => 'end_turn', 'stop_sequence' => null,
        'usage' => ['input_tokens' => 100, 'cache_read_input_tokens' => 900, 'cache_creation_input_tokens' => 50, 'output_tokens' => 20],
    ]);

    $response = (new AnthropicProvider($transport))->complete(completion(['effort' => 'low']), ['api_key' => FakeSecrets::apiKey(LlmProvider::Anthropic)]);

    $sent = json_decode((string) $transport->request->getBody(), true);
    expect($sent['system'][1])->toBe(['type' => 'text', 'text' => 'CONTEXT', 'cache_control' => ['type' => 'ephemeral']])
        ->and($sent['output_config']['format']['type'])->toBe('json_schema')
        ->and($sent['output_config']['effort'])->toBe('low')
        ->and($sent)->not->toHaveKey('thinking')
        ->and($response->text)->toBe('{"a":"b"}')
        ->and([$response->inputTokens, $response->cachedInputTokens, $response->cacheWriteTokens, $response->outputTokens])->toBe([100, 900, 50, 20]);
});

it('opts into server-side refusal fallback on models that support it', function () {
    $transport = anthropicTransport(200, [
        'id' => 'msg_1', 'type' => 'message', 'role' => 'assistant', 'model' => 'claude-opus-5-5',
        'content' => [['type' => 'text', 'text' => '{}']], 'stop_reason' => 'end_turn', 'stop_sequence' => null,
        'usage' => ['input_tokens' => 1, 'output_tokens' => 1],
    ]);

    (new AnthropicProvider($transport))->complete(completion(['serverFallback' => true]), ['api_key' => FakeSecrets::apiKey(LlmProvider::Anthropic)]);

    expect(json_decode((string) $transport->request->getBody(), true)['fallbacks'])->toBe('default')
        ->and($transport->request->getHeaderLine('anthropic-beta'))->toContain('server-side-fallback-2026-07-01');
});

it('maps Claude errors and refusals to provider error codes', function (int $status, array $body, ProviderErrorCode $code) {
    $provider = new AnthropicProvider(anthropicTransport($status, $body));

    try {
        $provider->complete(completion(), ['api_key' => FakeSecrets::apiKey(LlmProvider::Anthropic)]);
        $this->fail('Expected a ProviderException');
    } catch (ProviderException $e) {
        expect($e->errorCode)->toBe($code)->and($e->getMessage())->not->toContain('detail');
    }
})->with([
    'rate limit' => [429, ['type' => 'error', 'error' => ['type' => 'rate_limit_error', 'message' => 'detail']], ProviderErrorCode::RateLimited],
    'auth' => [401, ['type' => 'error', 'error' => ['type' => 'authentication_error', 'message' => 'detail']], ProviderErrorCode::Unauthorized],
    'overloaded' => [529, ['type' => 'error', 'error' => ['type' => 'overloaded_error', 'message' => 'detail']], ProviderErrorCode::Overloaded],
    'refusal' => [200, ['id' => 'm', 'type' => 'message', 'role' => 'assistant', 'model' => 'x', 'content' => [], 'stop_reason' => 'refusal', 'stop_sequence' => null, 'usage' => ['input_tokens' => 1, 'output_tokens' => 0]], ProviderErrorCode::Refused],
]);

it('normalizes OpenAI usage with cached tokens', function () {
    Http::fake(['api.openai.com/v1/chat/completions' => Http::response([
        'model' => 'gpt-test', 'choices' => [['message' => ['content' => '{"a":"b"}'], 'finish_reason' => 'stop']],
        'usage' => ['prompt_tokens' => 1000, 'completion_tokens' => 30, 'prompt_tokens_details' => ['cached_tokens' => 800]],
    ])]);

    $response = app(OpenAiProvider::class)->complete(completion(), ['api_key' => FakeSecrets::apiKey(LlmProvider::OpenAi)]);

    expect([$response->inputTokens, $response->cachedInputTokens, $response->outputTokens])->toBe([200, 800, 30]);
    Http::assertSent(fn ($r) => $r['response_format']['type'] === 'json_schema' && $r['messages'][1]['content'] === "CONTEXT\n\nTASK");
});

it('puts the schema in the DeepSeek prompt and reads cache hit tokens', function () {
    Http::fake(['api.deepseek.com/chat/completions' => Http::response([
        'model' => 'deepseek-test', 'choices' => [['message' => ['content' => '{"a":"b"}'], 'finish_reason' => 'stop']],
        'usage' => ['prompt_tokens' => 1000, 'prompt_cache_hit_tokens' => 600, 'prompt_cache_miss_tokens' => 400, 'completion_tokens' => 10],
    ])]);

    $response = app(DeepSeekProvider::class)->complete(completion(), ['api_key' => FakeSecrets::apiKey(LlmProvider::DeepSeek)]);

    expect([$response->inputTokens, $response->cachedInputTokens])->toBe([400, 600]);
    Http::assertSent(fn ($r) => $r['response_format'] === ['type' => 'json_object'] && str_contains($r['messages'][1]['content'], 'JSON Schema'));
});

it('counts Gemini thinking tokens as output and skips thought parts', function () {
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
        'modelVersion' => 'gemini-test',
        'candidates' => [['content' => ['parts' => [['text' => 'thinking...', 'thought' => true], ['text' => '{"a":"b"}']]], 'finishReason' => 'STOP']],
        'usageMetadata' => ['promptTokenCount' => 500, 'cachedContentTokenCount' => 100, 'candidatesTokenCount' => 20, 'thoughtsTokenCount' => 40],
    ])]);

    $response = app(GeminiProvider::class)->complete(completion(), ['api_key' => FakeSecrets::apiKey(LlmProvider::Gemini)]);

    expect($response->text)->toBe('{"a":"b"}')->and([$response->inputTokens, $response->cachedInputTokens, $response->outputTokens])->toBe([400, 100, 60]);
    Http::assertSent(fn ($r) => $r->hasHeader('x-goog-api-key') && $r['generationConfig']['responseMimeType'] === 'application/json');
});

it('maps HTTP provider errors without leaking bodies', function (int $status, ProviderErrorCode $code) {
    Http::fake(['api.openai.com/*' => Http::response(['error' => ['message' => 'secret detail']], $status)]);

    try {
        app(OpenAiProvider::class)->complete(completion(), ['api_key' => FakeSecrets::apiKey(LlmProvider::OpenAi)]);
        $this->fail('Expected a ProviderException');
    } catch (ProviderException $e) {
        expect($e->errorCode)->toBe($code)->and($e->getMessage())->not->toContain('secret detail');
    }
})->with([
    [401, ProviderErrorCode::Unauthorized],
    [402, ProviderErrorCode::QuotaExceeded],
    [429, ProviderErrorCode::RateLimited],
    [500, ProviderErrorCode::ServerError],
    [400, ProviderErrorCode::BadRequest],
]);
