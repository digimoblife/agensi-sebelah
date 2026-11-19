<?php

namespace App\Services\Llm\Exceptions;

use App\Services\Llm\ProviderErrorCode;
use RuntimeException;

/** Carries only a code; provider response bodies are never put in the message (they may echo prompt text). */
final class ProviderException extends RuntimeException
{
    public function __construct(public readonly ProviderErrorCode $errorCode, ?\Throwable $previous = null)
    {
        parent::__construct('LLM provider error: '.$errorCode->value, 0, $previous);
    }
}
