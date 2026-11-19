<?php

namespace App\Services\Content;

use App\Enums\ArtifactType;
use App\Models\Client;
use App\Models\ContentItem;
use App\Services\Artifacts\Generators\ClientContext;
use App\Services\Render\ContentRenderer;

/**
 * Composes a ready-to-paste prompt for generating an item's image in a chat app (ChatGPT, Gemini) by hand.
 * The image is a background only: the template adds the headline, logo and CTA afterwards, so the prompt asks
 * for no text and no logos, and repeats the brand's "AI must not depict" list (PRD §Sumber aset rule 4).
 */
class ImagePrompt
{
    /** Always forbidden, whatever the brand says. */
    private const ALWAYS_AVOID = [
        'text, letters, words, numbers or watermarks in the image',
        'logos or brand marks',
        'faces of real or recognisable people',
    ];

    public function __construct(private readonly ClientContext $context) {}

    public function for(ContentItem $item): string
    {
        /** @var Client $client */
        $client = $item->client()->firstOrFail();
        $rules = $this->context->approved($client, ArtifactType::VisualRules) ?? [];
        $draft = (array) $item->draft;

        [$width, $height] = ContentRenderer::SIZES[$item->format] ?? [1080, 1350];
        $subject = trim((string) ($draft['visual']['image_prompt'] ?? ''))
            ?: trim($item->angle.'. '.($item->asset_hint ?? ''));

        $avoid = [...self::ALWAYS_AVOID, ...array_map('strval', (array) ($rules['imagery']['ai_forbidden_for'] ?? []))];
        $colors = array_filter([(string) ($rules['colors']['primary'] ?? ''), (string) ($rules['colors']['secondary'] ?? ''), (string) ($rules['colors']['accent'] ?? '')]);

        $lines = array_filter([
            $subject,
            isset($rules['imagery']['style']) ? 'Style: '.$rules['imagery']['style'] : null,
            $colors !== [] ? 'Brand colour palette: '.implode(', ', $colors).'.' : null,
            isset($rules['imagery']['image_prompt_suffix']) ? (string) $rules['imagery']['image_prompt_suffix'] : null,
            "Aspect ratio {$this->ratio($width, $height)} ({$width}×{$height} px), photographic quality.",
            'Leave calm space in the lower third for a headline that will be added later.',
            'Do not include: '.implode('; ', array_unique($avoid)).'.',
        ]);

        return implode("\n", $lines);
    }

    private function ratio(int $width, int $height): string
    {
        $d = $this->gcd($width, $height);

        return ($width / $d).':'.($height / $d);
    }

    private function gcd(int $a, int $b): int
    {
        return $b === 0 ? $a : $this->gcd($b, $a % $b);
    }
}
