<?php

namespace App\Services\Content;

use App\Enums\AssetKind;
use App\Enums\ContentStatus;
use App\Jobs\RenderAndSend;
use App\Models\Asset;
use App\Models\ContentItem;
use App\Services\Assets\AssetLibrary;
use InvalidArgumentException;

/**
 * Attaches an AI image the admin generated in a chat app. It is stored as an `ai_image` asset (ai_generated =
 * true, for the record; no AI label is added to packages or reminders automatically) and used only by this item.
 */
class AiImageUpload
{
    public const STATUSES = [ContentStatus::Drafted, ContentStatus::GuardianFailed, ContentStatus::AwaitingApproval, ContentStatus::RevisionRequested];

    public function __construct(private readonly AssetLibrary $assets) {}

    public function attach(ContentItem $item, string $uploadedPath): Asset
    {
        if (! in_array($item->status, self::STATUSES, true)) {
            throw new InvalidArgumentException('An AI image can only be attached before the content is approved.');
        }

        $asset = Asset::query()->create($this->assets->describeUpload($uploadedPath) + [
            'workspace_id' => $item->workspace_id,
            'client_id' => $item->client_id,
            'offering_id' => $item->offering_id,
            'kind' => AssetKind::AiImage,
            'source' => 'ai',
            'title' => 'Gambar AI · '.$item->plan_item_key,
            'path' => $uploadedPath,
            'tags' => ['ai_image', $item->format],
            'consent_status' => 'not_needed',
            'ai_generated' => true,
        ]);

        $previous = $item->visual_asset_id;
        $item->forceFill(['visual_asset_id' => $asset->id])->save();
        $this->assets->recordUsage($asset, $item, 'source');
        if ($previous !== null) {
            Asset::query()->whereKey($previous)->where('kind', AssetKind::AiImage)->update(['tags' => ['ai_image', 'replaced']]);
        }

        // Ready now: render with the new image and send to the client (or re-send the updated version).
        if ($item->status === ContentStatus::AwaitingApproval) {
            dispatch(new RenderAndSend([$item->id]));
        }

        return $asset;
    }
}
