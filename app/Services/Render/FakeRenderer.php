<?php

namespace App\Services\Render;

/** Used in tests (RENDERER=fake): returns a tiny real PNG and remembers what it was asked to render. */
final class FakeRenderer implements HtmlRenderer
{
    /** @var list<array{html: string, files: list<string>, width: int, height: int}> */
    public array $renders = [];

    public function png(string $html, array $files, int $width, int $height): string
    {
        $this->renders[] = ['html' => $html, 'files' => array_keys($files), 'width' => $width, 'height' => $height];

        $image = imagecreatetruecolor(4, 5);
        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }

    /** @var list<string> */
    public array $pdfs = [];

    public function pdf(string $html): string
    {
        $this->pdfs[] = $html;

        return "%PDF-1.4\n% fake\n";
    }
}
