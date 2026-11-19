<?php

namespace App\Services\Llm;

use App\Enums\LlmProvider;
use App\Services\Credentials\ConnectionResult;
use SensitiveParameter;

interface LlmProviderAdapter
{
    public function provider(): LlmProvider;

    /**
     * @param  array<string, string>  $credentials  decrypted provider fields (api_key, base_url, ...)
     *
     * @throws Exceptions\ProviderException
     */
    public function complete(CompletionRequest $request, #[SensitiveParameter] array $credentials): CompletionResponse;

    /** @param  array<string, string>  $credentials */
    public function testConnection(#[SensitiveParameter] array $credentials): ConnectionResult;
}
