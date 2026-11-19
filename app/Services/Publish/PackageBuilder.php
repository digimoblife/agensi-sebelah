<?php

namespace App\Services\Publish;

use App\Enums\AssetKind;
use App\Models\Asset;
use App\Models\ContentItem;
use App\Services\Assets\AssetLibrary;
use RuntimeException;
use ZipArchive;

/** F9.5: one download with the final files, caption, hashtags and schedule. */
class PackageBuilder
{
    public function __construct(private readonly AssetLibrary $assets, private readonly QaPublisher $qa) {}

    public function build(ContentItem $item, string $scheduledLocal): Asset
    {
        $tmp = tempnam(sys_get_temp_dir(), 'pkg');
        $zip = new ZipArchive;
        if ($tmp === false || $zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Cannot create package.');
        }

        $files = Asset::query()->whereIn('ulid', $item->render['assets'] ?? [])->get()
            ->sortBy(fn (Asset $a) => array_search($a->ulid, $item->render['assets'] ?? [], true))->values();
        foreach ($files as $i => $asset) {
            $zip->addFromString(sprintf('%02d.png', $i + 1), $this->assets->contents($asset));
        }
        $zip->addFromString('caption.txt', $this->qa->caption($item));
        $zip->addFromString('info.txt', implode("\n", [
            'Klien: '.$item->client?->name,
            "Platform: {$item->platform}",
            "Format: {$item->format}",
            "Jadwal: {$scheduledLocal}",
            ($item->render['needs_video'] ?? false) ? 'Catatan: format video. Gambar adalah sampul; klip video disiapkan terpisah.' : '',
        ]));
        $zip->close();

        $contents = (string) file_get_contents($tmp);
        @unlink($tmp);

        $asset = $this->assets->storeContents($item->client()->firstOrFail(), $contents, 'zip', [
            'kind' => AssetKind::Package, 'source' => 'system', 'title' => "Paket {$item->plan_item_key}", 'offering_id' => $item->offering_id,
        ]);
        $asset->update(['mime' => 'application/zip']);

        return $asset;
    }
}
