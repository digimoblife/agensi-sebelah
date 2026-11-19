<?php

namespace App\Services\Render;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Google Fonts named in Visual Rules, downloaded once (regular + bold, latin) and cached on the private disk so
 * Gotenberg, which has no internet access, can render them. Failure falls back to a system sans-serif.
 */
class FontLibrary
{
    private const WEIGHTS = [400, 700];

    /** @return array{css: string, files: array<string, string>} */
    public function faces(string $family): array
    {
        $family = trim($family);
        if ($family === '' || config('services.renderer.driver') === 'fake' || preg_match('/^[\p{L}\p{N} ]{2,60}$/u', $family) !== 1) {
            return ['css' => '', 'files' => []];
        }

        $slug = Str::slug($family);
        $disk = Storage::disk('local');
        $css = '';
        $files = [];

        foreach (self::WEIGHTS as $weight) {
            $path = "fonts/{$slug}-{$weight}.woff2";
            if (! $disk->exists($path) && ! $this->download($family, $weight, $path)) {
                continue;
            }
            $name = "{$slug}-{$weight}.woff2";
            $files[$name] = (string) $disk->get($path);
            $css .= "@font-face{font-family:'{$family}';font-weight:{$weight};src:url('{$name}') format('woff2');}";
        }

        return ['css' => $css, 'files' => $files];
    }

    private function download(string $family, int $weight, string $path): bool
    {
        try {
            $css = Http::timeout(10)->withHeaders(['User-Agent' => 'Mozilla/5.0 (Macintosh) AppleWebKit/537.36 Chrome/120 Safari/537.36'])
                ->get('https://fonts.googleapis.com/css2', ['family' => "{$family}:wght@{$weight}", 'display' => 'swap'])->body();
            // The latin subset is the last block Google returns.
            preg_match_all('/src:\s*url\((https:[^)]+\.woff2)\)/', $css, $m);
            $url = end($m[1]);
            if (! is_string($url)) {
                return false;
            }
            $font = Http::timeout(15)->get($url);
            if (! $font->successful()) {
                return false;
            }
            Storage::disk('local')->put($path, $font->body());

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
