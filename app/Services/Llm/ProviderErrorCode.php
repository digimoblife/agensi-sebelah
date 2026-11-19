<?php

namespace App\Services\Llm;

enum ProviderErrorCode: string
{
    case Unauthorized = 'unauthorized';
    case RateLimited = 'rate_limited';
    case QuotaExceeded = 'quota_exceeded';
    case Overloaded = 'overloaded';
    case ServerError = 'server_error';
    case Timeout = 'timeout';
    case Connection = 'connection';
    case BadRequest = 'bad_request';
    case Refused = 'refused';
    case MissingCredentials = 'missing_credentials';
    case Unknown = 'unknown';

    /** Whether the gateway should try the fallback model (PRD: error, rate limit or quota → fallback). */
    public function allowsFallback(): bool
    {
        return $this !== self::BadRequest;
    }
}
