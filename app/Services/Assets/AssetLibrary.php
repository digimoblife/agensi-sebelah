<?php

namespace App\Services\Assets;

use App\Enums\AssetKind;
use App\Models\Asset;
use App\Models\Client;
use App\Models\ContentItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** F9.3: stores files on the private `assets` disk and picks publishable assets for content. */
class AssetLibrary
{
    /** @param  array<string, mixed>  $attributes */
    public function storeContents(Client $client, string $contents, string $extension, array $attributes): Asset
    {
        $path = $client->ulid.'/'.Str::ulid()->toBase32().'.'.$extension;
        Storage::disk('assets')->put($path, $contents);
        $size = @getimagesizefromstring($contents) ?: [null, null, 'mime' => null];

        return Asset::query()->create($attributes + [
            'workspace_id' => $client->workspace_id,
            'client_id' => $client->id,
            'path' => $path,
            'mime' => $size['mime'] ?? 'application/octet-stream',
            'width' => $size[0],
            'height' => $size[1],
            'bytes' => strlen($contents),
            'consent_status' => 'not_needed',
        ]);
    }

    /**
     * Fills technical fields for a file uploaded through the dashboard (already on the assets disk).
     *
     * @return array{mime: string, width: ?int, height: ?int, bytes: int}
     */
    public function describeUpload(string $path): array
    {
        $contents = Storage::disk('assets')->get($path) ?? '';
        $size = @getimagesizefromstring($contents) ?: [null, null, 'mime' => null];

        return [
            'mime' => $size['mime'] ?? (Storage::disk('assets')->mimeType($path) ?: 'application/octet-stream'),
            'width' => $size[0],
            'height' => $size[1],
            'bytes' => strlen($contents),
        ];
    }

    public function logo(Client $client, ?string $preferredUlid = null): ?Asset
    {
        $query = $client->assets()->where('kind', AssetKind::Logo);

        return ($preferredUlid !== null ? (clone $query)->where('ulid', $preferredUlid)->first() : null)
            ?? $query->latest('id')->first();
    }

    /**
     * Best publishable photo for a free-text hint: tag/title word overlap, offering match, then least used.
     */
    public function pick(Client $client, ?string $hint, ?int $offeringId = null): ?Asset
    {
        $words = collect(preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower((string) $hint)) ?: [])->filter(fn ($w) => mb_strlen($w) >= 3)->values();

        return $client->assets()
            ->whereIn('kind', [AssetKind::Photo, AssetKind::Screenshot, AssetKind::Illustration])
            ->withCount('usages')
            ->get()
            ->filter(fn (Asset $a) => $a->isPublishable() && str_starts_with((string) $a->mime, 'image/'))
            ->sortByDesc(function (Asset $a) use ($words, $offeringId) {
                $haystack = mb_strtolower($a->title.' '.implode(' ', $a->tags ?? []));
                $score = $words->filter(fn ($w) => str_contains($haystack, $w))->count() * 10;
                $score += ($offeringId !== null && $a->offering_id === $offeringId) ? 5 : 0;

                return $score - min((int) $a->getAttribute('usages_count'), 4);
            })
            ->first();
    }

    public function recordUsage(Asset $asset, ContentItem $item, string $role): void
    {
        DB::table('asset_usages')->updateOrInsert(
            ['asset_id' => $asset->id, 'content_item_id' => $item->id, 'role' => $role],
            ['used_at' => now()],
        );
    }

    public function contents(Asset $asset): string
    {
        return (string) Storage::disk('assets')->get($asset->path);
    }
}
