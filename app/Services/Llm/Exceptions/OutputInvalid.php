<?php

namespace App\Services\Llm\Exceptions;

final class OutputInvalid extends LlmGatewayException
{
    public function __construct(public readonly ?string $detail = null)
    {
        parent::__construct('The model output failed schema validation twice; the task needs manual handling.'.($detail !== null ? ' ('.$detail.')' : ''));
    }
}
