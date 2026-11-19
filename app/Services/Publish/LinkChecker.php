<?php

namespace App\Services\Publish;

use Illuminate\Support\Facades\Http;
use Throwable;

/** F4.3: does the link in the caption still answer? Network problems count as "unknown", not broken. */
class LinkChecker
{
    public function status(string $url): string
    {
        try {
            $response = Http::timeout(6)->withOptions(['allow_redirects' => ['max' => 5]])->head($url);
            if ($response->status() === 405) {
                $response = Http::timeout(6)->get($url);
            }

            return $response->successful() ? 'ok' : ($response->status() >= 400 && $response->status() < 500 ? 'broken' : 'unknown');
        } catch (Throwable) {
            return 'unknown';
        }
    }
}
