<?php

namespace App\Http\Controllers;

use App\Enums\AssetKind;
use App\Models\Asset;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** The only route meant to be reachable through the public tunnel: signed, short-lived, rendered images only. */
class PublicMediaController
{
    public function __invoke(string $asset): StreamedResponse
    {
        $model = Asset::query()->where('ulid', $asset)->where('kind', AssetKind::Render)->firstOrFail();

        return Storage::disk('assets')->response($model->path, $model->ulid.'.png', [
            'Content-Type' => $model->mime, 'Cache-Control' => 'private, max-age=600', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
