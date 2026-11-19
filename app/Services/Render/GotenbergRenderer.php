<?php

namespace App\Services\Render;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Chromium screenshot through Gotenberg (internal network only). Replaces the planned Node renderer for MVP. */
final class GotenbergRenderer implements HtmlRenderer
{
    public function png(string $html, array $files, int $width, int $height): string
    {
        $request = Http::timeout((int) config('services.renderer.timeout'))
            ->attach('files', $html, 'index.html');
        foreach ($files as $name => $contents) {
            $request = $request->attach('files', $contents, $name);
        }

        $response = $request->post(rtrim((string) config('services.renderer.gotenberg_url'), '/').'/forms/chromium/screenshot/html', [
            'width' => $width,
            'height' => $height,
            'clip' => 'true',
            'format' => 'png',
            'skipNetworkIdleEvent' => 'false',
        ]);

        if (! $response->successful() || ! str_starts_with($response->body(), "\x89PNG")) {
            throw new RuntimeException('Render failed (HTTP '.$response->status().').');
        }

        return $response->body();
    }
}
