<?php

namespace App\Services\Publish;

use App\Models\Asset;
use Illuminate\Support\Facades\URL;

/** Short-lived public URLs for rendered media, through the tunnel base URL (relative signature). */
class PublicMediaUrl
{
    public function available(): bool
    {
        $base = (string) config('services.publish.public_media_base');

        return str_starts_with($base, 'https://');
    }

    public function for(Asset $asset): string
    {
        $path = URL::temporarySignedRoute('media.public', now()->addMinutes((int) config('services.publish.media_url_ttl_minutes')), ['asset' => $asset->ulid], absolute: false);

        return rtrim((string) config('services.publish.public_media_base'), '/').$path;
    }
}
