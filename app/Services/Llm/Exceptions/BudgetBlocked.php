<?php

namespace App\Services\Llm\Exceptions;

final class BudgetBlocked extends LlmGatewayException
{
    public function __construct(public readonly ?string $detail = null)
    {
        parent::__construct('The client monthly AI budget does not allow this task now.'.($detail !== null ? ' ('.$detail.')' : ''));
    }
}
