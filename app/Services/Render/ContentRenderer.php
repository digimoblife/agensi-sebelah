<?php

namespace App\Services\Render;

use App\Enums\ArtifactType;
use App\Enums\AssetKind;
use App\Models\Asset;
use App\Models\Client;
use App\Models\ContentItem;
use App\Services\Artifacts\Generators\ClientContext;
use App\Services\Artifacts\PrerequisiteMissing;
use App\Services\Assets\AssetLibrary;
use Illuminate\Support\Facades\View;

/**
 * F3.2 Graphic Designer (T0, no LLM): fills built-in HTML templates with the approved Visual Rules and the draft,
 * renders PNGs, and stores them as `render` assets. Video formats get a cover image; the clip itself is phase 2.
 */
class ContentRenderer
{
    /** Canvas per format (pixels). */
    public const SIZES = [
        'feed_image' => [1080, 1350], 'carousel' => [1080, 1350], 'story' => [1080, 1920],
        'reel' => [1080, 1920], 'short_video' => [1080, 1920], 'gbp_post' => [1200, 900],
    ];

    public function __construct(
        private readonly HtmlRenderer $renderer,
        private readonly FontLibrary $fonts,
        private readonly AssetLibrary $assets,
        private readonly ClientContext $context,
    ) {}

    public function supports(ContentItem $item): bool
    {
        return isset(self::SIZES[$item->format]);
    }

    /** True when the stored render was made from the current draft. */
    public function isCurrent(ContentItem $item): bool
    {
        return $item->render !== null && $item->draft !== null && ($item->render['draft_hash'] ?? null) === $this->hash($item);
    }

    /** @return list<Asset> rendered files, in slide order */
    public function render(ContentItem $item): array
    {
        if ($item->draft === null || ! $this->supports($item)) {
            return [];
        }

        /** @var Client $client */
        $client = $item->client()->firstOrFail();
        $rules = $this->context->approved($client, ArtifactType::VisualRules)
            ?? throw new PrerequisiteMissing('Setujui Visual Rules sebelum merender visual.');

        [$width, $height] = self::SIZES[$item->format];
        $draft = $item->draft;
        $logo = $this->assets->logo($client, $rules['logo']['asset_id'] ?? null);
        // An uploaded AI image (manual chat-app flow) always wins; otherwise pick a library photo.
        $photo = $item->visualAsset()->first()
            ?? (($draft['visual']['mode'] ?? 'template') === 'asset' || $item->format !== 'carousel'
                ? $this->assets->pick($client, ($draft['visual']['asset_query'] ?? null) ?: $item->asset_hint, $item->offering_id)
                : null);

        $heading = $this->fonts->faces((string) ($rules['typography']['heading_font'] ?? ''));
        $body = $this->fonts->faces((string) ($rules['typography']['body_font'] ?? ''));
        $files = $heading['files'] + $body['files'];
        if ($logo !== null) {
            $files['logo'] = $this->assets->contents($logo);
        }
        if ($photo !== null) {
            $files['photo'] = $this->assets->contents($photo);
        }

        // Carousel: a cover with the visual headline, then one page per slide (headline + body).
        $cover = ['headline' => (string) ($draft['visual']['headline'] ?? ($draft['hook'] ?? '')), 'body' => null];
        $pages = $item->format === 'carousel'
            ? [$cover, ...array_map(fn (array $slide) => ['headline' => (string) ($slide['headline'] ?? ''), 'body' => $slide['body'] ?? null], (array) ($draft['slides'] ?? []))]
            : [$cover];

        $rendered = [];
        foreach ($pages as $index => $page) {
            $html = View::make($this->template($item, $index), [
                'width' => $width,
                'height' => $height,
                'colors' => $rules['colors'],
                'headingFont' => $rules['typography']['heading_font'] ?? 'sans-serif',
                'bodyFont' => $rules['typography']['body_font'] ?? 'sans-serif',
                'fontCss' => $heading['css'].$body['css'],
                'logo' => $logo !== null ? 'logo' : null,
                'logoPlacement' => $rules['logo']['placement'] ?? 'bottom_right',
                'photo' => $photo !== null && $index === 0 ? 'photo' : null,
                'headline' => $page['headline'],
                'body' => $page['body'],
                'cta' => $index === array_key_last($pages) ? ($draft['cta'] ?? $item->cta) : null,
                'page' => count($pages) > 1 ? ($index + 1).'/'.count($pages) : null,
            ])->render();

            $png = $this->renderer->png($html, $files, $width, $height);
            $asset = $this->assets->storeContents($client, $png, 'png', [
                'kind' => AssetKind::Render, 'source' => 'system', 'offering_id' => $item->offering_id,
                'title' => "{$item->plan_item_key} ".($index + 1), 'tags' => ['render', $item->format],
            ]);
            $this->assets->recordUsage($asset, $item, 'output');
            $rendered[] = $asset;
        }

        if ($photo !== null) {
            $this->assets->recordUsage($photo, $item, 'source');
        }

        $item->forceFill(['render' => [
            'assets' => array_map(fn (Asset $a) => $a->ulid, $rendered),
            'draft_hash' => $this->hash($item),
            'rendered_at' => now()->toIso8601String(),
            'needs_video' => in_array($item->format, ['reel', 'short_video'], true),
        ]])->save();

        return $rendered;
    }

    /** @return 'render.cover'|'render.slide'|'render.story' */
    private function template(ContentItem $item, int $index): string
    {
        return match (true) {
            $item->format === 'carousel' => $index === 0 ? 'render.cover' : 'render.slide',
            in_array($item->format, ['story', 'reel', 'short_video'], true) => 'render.story',
            default => 'render.cover',
        };
    }

    /**
     * Canonical hash of what the render depends on: the draft (PostgreSQL jsonb does not keep key order, so keys
     * are sorted first) and the uploaded AI image, so replacing the image makes the render stale.
     */
    private function hash(ContentItem $item): string
    {
        return sha1((string) json_encode($this->canonical((array) $item->draft), JSON_UNESCAPED_UNICODE).'|'.($item->visual_asset_id ?? ''));
    }

    /**
     * @param  array<mixed>  $data
     * @return array<mixed>
     */
    private function canonical(array $data): array
    {
        if (! array_is_list($data)) {
            ksort($data);
        }

        return array_map(fn ($v) => is_array($v) ? $this->canonical($v) : $v, $data);
    }
}
