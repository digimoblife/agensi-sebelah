<?php

namespace App\Services\Connections;

use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;

/** CSRF protection for OAuth: a random state bound to the admin session and one client. */
final class OAuthState
{
    public static function issue(string $platform, int $clientId): string
    {
        $state = Str::random(40);
        Session::put("oauth.{$platform}", ['state' => $state, 'client_id' => $clientId, 'at' => now()->timestamp]);

        return $state;
    }

    /** @return int client id */
    public static function consume(string $platform, ?string $state): int
    {
        $stored = Session::pull("oauth.{$platform}");
        if (! is_array($stored) || ! is_string($state) || ! hash_equals((string) $stored['state'], $state) || (int) now()->timestamp - (int) $stored['at'] > 900) {
            throw new OAuthException('invalid_state');
        }

        return (int) $stored['client_id'];
    }
}
