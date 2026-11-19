<?php

namespace App\Services\Render;

interface HtmlRenderer
{
    /**
     * @param  array<string, string>  $files  extra files referenced by name from the HTML (images, fonts)
     * @return string PNG bytes
     */
    public function png(string $html, array $files, int $width, int $height): string;

    /** @return string PDF bytes (A4) */
    public function pdf(string $html): string;
}
