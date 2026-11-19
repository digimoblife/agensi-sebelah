<?php

namespace App\Enums;

enum LlmCallStatus: string
{
    case Ok = 'ok';
    case SchemaInvalid = 'schema_invalid';
    case ProviderError = 'provider_error';
    case RateLimited = 'rate_limited';
    case Refused = 'refused';
    case BudgetBlocked = 'budget_blocked';
    case CacheHit = 'cache_hit';
}
