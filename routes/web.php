<?php

use App\Http\Controllers\AssetController;
use App\Http\Controllers\OAuthController;
use App\Http\Controllers\PublicMediaController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect('/admin'));

// Private asset previews for the dashboard: short-lived signed URLs only (Asset::previewUrl).
Route::get('/assets/{asset}', AssetController::class)->middleware('signed')->name('assets.show');

// F1.2 account connections (admin only).
Route::middleware('auth')->group(function () {
    Route::get('/oauth/{platform}/connect/{client}', [OAuthController::class, 'redirect'])->whereIn('platform', ['meta', 'google'])->name('oauth.connect');
    Route::get('/oauth/{platform}/callback', [OAuthController::class, 'callback'])->whereIn('platform', ['meta', 'google'])->name('oauth.callback');
});

// Rendered media for Instagram to fetch during publishing. Relative signature so it validates behind a tunnel.
Route::get('/media/{asset}', PublicMediaController::class)->middleware('signed:relative')->name('media.public');
