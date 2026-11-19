<?php

namespace App\Services\Llm\Exceptions;

final class AllProvidersFailed extends LlmGatewayException
{
    public function __construct(public readonly ?string $detail = null)
    {
        parent::__construct('Every candidate model failed.'.($detail !== null ? ' ('.$detail.')' : ''));
    }
}
