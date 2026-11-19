<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Serves private asset files to the dashboard via short-lived signed URLs only. */
class AssetController
{
    public function __invoke(string $asset): StreamedResponse
    {
        $model = Asset::query()->where('ulid', $asset)->firstOrFail();

        return Storage::disk('assets')->response($model->path, null, [
            'Content-Type' => $model->mime,
            'Cache-Control' => 'private, max-age=600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
