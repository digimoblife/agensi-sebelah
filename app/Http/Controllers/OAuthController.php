<?php

namespace App\Http\Controllers;

use App\Filament\Resources\Clients\ClientResource;
use App\Models\Client;
use App\Services\Activity\ActivityLogger;
use App\Services\Connections\GoogleConnection;
use App\Services\Connections\MetaConnection;
use App\Services\Connections\OAuthException;
use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** F1.2 connect flows. Admin-only (auth middleware); state is bound to the session. */
class OAuthController
{
    public function redirect(string $platform, string $client): RedirectResponse
    {
        $model = Client::query()->where('ulid', $client)->firstOrFail();

        try {
            return redirect()->away($this->service($platform)->authorizeUrl($model));
        } catch (OAuthException $e) {
            return $this->back($model->id, 'Belum bisa menghubungkan: '.$this->message($e->getMessage()), false);
        }
    }

    public function callback(Request $request, string $platform): RedirectResponse
    {
        if (! is_string($request->query('code'))) {
            Notification::make()->title('Koneksi dibatalkan.')->warning()->send();

            return redirect('/admin');
        }

        try {
            $connection = $this->service($platform)->complete($request->query('code'), $request->query('state'));
        } catch (OAuthException $e) {
            Notification::make()->title($this->message($e->getMessage()))->danger()->send();

            return redirect('/admin');
        }

        app(ActivityLogger::class)->log('connection.connected', $platform, ['client_id' => $connection->client_id, 'status' => $connection->status]);

        return $this->back($connection->client_id, $connection->status === 'active'
            ? 'Akun terhubung: '.$connection->account_name
            : 'Akun terhubung. Pilih akun/properti yang dipakai di tab Koneksi.', true);
    }

    private function service(string $platform): MetaConnection|GoogleConnection
    {
        return match ($platform) {
            'meta' => app(MetaConnection::class),
            'google' => app(GoogleConnection::class),
            default => abort(404),
        };
    }

    private function back(int $clientId, string $message, bool $ok): RedirectResponse
    {
        Notification::make()->title($message)->{$ok ? 'success' : 'danger'}()->send();

        return redirect(ClientResource::getUrl('edit', ['record' => $clientId]));
    }

    private function message(string $code): string
    {
        return match ($code) {
            'meta_app_not_configured' => 'App ID/Secret Meta belum diisi di Kredensial.',
            'google_app_not_configured' => 'Client ID/Secret Google belum diisi di Kredensial.',
            'no_instagram_business_account' => 'Tidak ada akun Instagram Business/Creator yang tertaut ke Facebook Page akun ini.',
            'invalid_state' => 'Sesi koneksi kedaluwarsa. Ulangi dari dashboard.',
            default => 'Koneksi gagal ('.$code.').',
        };
    }
}
