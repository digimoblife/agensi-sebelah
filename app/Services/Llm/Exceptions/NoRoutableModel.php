<?php

namespace App\Services\Llm\Exceptions;

final class NoRoutableModel extends LlmGatewayException
{
    public function __construct(public readonly ?string $detail = null)
    {
        parent::__construct('No configured model with prices, capabilities and credentials is available for this role.'.($detail !== null ? ' ('.$detail.')' : ''));
    }
}
