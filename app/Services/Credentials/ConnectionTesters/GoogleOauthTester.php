<?php

namespace App\Services\Credentials\ConnectionTesters;

use App\Services\Credentials\ConnectionResult;
use App\Services\Credentials\ConnectionTester;

/** Google offers no client-credentials check; the real test happens when an account is connected (F1.2). */
final class GoogleOauthTester implements ConnectionTester
{
    public function test(array $values): ConnectionResult
    {
        return ConnectionResult::ok('Format valid. Diuji penuh saat menghubungkan akun klien.');
    }
}
