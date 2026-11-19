<?php

namespace App\Services\Credentials;

interface ConnectionTester
{
    /** @param  array<string, string>  $values  decrypted values of every field of the provider */
    public function test(array $values): ConnectionResult;
}
