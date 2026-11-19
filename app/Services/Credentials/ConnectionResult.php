<?php

namespace App\Services\Credentials;

final readonly class ConnectionResult
{
    private function __construct(
        public bool $ok,
        public string $message,
        public ?string $errorCode = null,
    ) {}

    public static function ok(string $message): self
    {
        return new self(true, $message);
    }

    /** $errorCode is a short code such as `401_unauthorized` or `timeout`; never a response body. */
    public static function failed(string $errorCode, string $message): self
    {
        return new self(false, $message, $errorCode);
    }
}
