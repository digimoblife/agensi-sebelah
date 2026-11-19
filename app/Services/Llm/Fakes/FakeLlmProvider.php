<?php

namespace App\Services\Llm\Fakes;

use App\Enums\LlmProvider;
use App\Services\Credentials\ConnectionResult;
use App\Services\Llm\CompletionRequest;
use App\Services\Llm\CompletionResponse;
use App\Services\Llm\Exceptions\ProviderException;
use App\Services\Llm\LlmProviderAdapter;
use App\Services\Llm\ProviderErrorCode;
use RuntimeException;
use SensitiveParameter;

/**
 * Stands in for every provider when LLM_PROVIDER=fake (always in tests). Queue responses with respond() /
 * fail(); unqueued calls return the matching fixture from resources/schemas/examples when one exists.
 */
final class FakeLlmProvider implements LlmProviderAdapter
{
    /** @var list<string|ProviderErrorCode> */
    private array $queue = [];

    /** @var list<CompletionRequest> */
    public array $requests = [];

    public function __construct(private readonly LlmProvider $provider = LlmProvider::Anthropic) {}

    public function provider(): LlmProvider
    {
        return $this->provider;
    }

    /** @param  string|array<string, mixed>  $output */
    public function respond(string|array $output): self
    {
        $this->queue[] = is_array($output) ? (string) json_encode($output, JSON_UNESCAPED_UNICODE) : $output;

        return $this;
    }

    public function fail(ProviderErrorCode $code): self
    {
        $this->queue[] = $code;

        return $this;
    }

    public function complete(CompletionRequest $request, #[SensitiveParameter] array $credentials): CompletionResponse
    {
        $this->requests[] = $request;
        $next = array_shift($this->queue) ?? $this->fixture($request->schemaName);

        if ($next instanceof ProviderErrorCode) {
            throw new ProviderException($next);
        }

        return new CompletionResponse(
            text: $next,
            inputTokens: (int) ceil(mb_strlen($request->system.$request->task) / 3.5),
            cachedInputTokens: (int) ceil(mb_strlen($request->cachedContext) / 3.5),
            cacheWriteTokens: 0,
            outputTokens: (int) ceil(mb_strlen($next) / 3.5),
            stopReason: 'end_turn',
            modelId: $request->modelId,
        );
    }

    public function testConnection(#[SensitiveParameter] array $credentials): ConnectionResult
    {
        return ConnectionResult::ok('OK (fake provider).');
    }

    private function fixture(string $schemaName): string
    {
        $path = resource_path("schemas/examples/{$schemaName}.json");
        if (! is_file($path)) {
            throw new RuntimeException("FakeLlmProvider has no queued response and no fixture for [{$schemaName}].");
        }

        $data = json_decode((string) file_get_contents($path), true);
        unset($data['meta']); // models never produce meta; the backend injects it

        return (string) json_encode($data, JSON_UNESCAPED_UNICODE);
    }
}
