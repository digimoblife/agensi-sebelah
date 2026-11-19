<?php

use App\Http\Controllers\AssetController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect('/admin'));

// Private asset previews for the dashboard: short-lived signed URLs only (Asset::previewUrl).
Route::get('/assets/{asset}', AssetController::class)->middleware('signed')->name('assets.show');
