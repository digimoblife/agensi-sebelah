<?php

namespace App\Services\Credentials;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Throwable;

/** Turns an HTTP outcome into a short, body-free error code and an Indonesian message. */
final class HttpErrorCode
{
    public static function fromResponse(Response $response): ConnectionResult
    {
        $status = $response->status();

        return match (true) {
            in_array($status, [401, 403], true) => ConnectionResult::failed("{$status}_unauthorized", 'Kredensial ditolak. Periksa kembali nilainya.'),
            $status === 429 => ConnectionResult::failed('429_rate_limited', 'Terkena batas permintaan. Coba lagi beberapa saat lagi.'),
            $status >= 500 => ConnectionResult::failed("{$status}_server_error", 'Layanan penyedia sedang bermasalah.'),
            default => ConnectionResult::failed("{$status}_http_error", "Permintaan gagal (HTTP {$status})."),
        };
    }

    public static function fromThrowable(Throwable $e): ConnectionResult
    {
        if ($e instanceof ConnectionException) {
            return ConnectionResult::failed('timeout', 'Tidak bisa terhubung ke penyedia (timeout atau jaringan).');
        }

        return ConnectionResult::failed('error_'.class_basename($e), 'Tes koneksi gagal.');
    }
}
