<?php

namespace App\Services\Connections;

use App\Enums\CredentialProvider;
use App\Models\Client;
use App\Models\OAuthConnection;
use App\Services\Credentials\CredentialStore;
use Illuminate\Support\Facades\Http;

/** F1.2 GA4 (read-only) via Google OAuth with offline access. The GA4 property id is entered by the admin. */
class GoogleConnection
{
    public const SCOPES = ['https://www.googleapis.com/auth/analytics.readonly'];

    public function __construct(private readonly CredentialStore $credentials) {}

    public function redirectUri(): string
    {
        return url('/oauth/google/callback');
    }

    public function authorizeUrl(Client $client): string
    {
        return 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
            'client_id' => $this->app()['client_id'],
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            'scope' => implode(' ', self::SCOPES),
            'access_type' => 'offline',
            'prompt' => 'consent',
            'include_granted_scopes' => 'true',
            'state' => OAuthState::issue('google', $client->id),
        ]);
    }

    public function complete(string $code, ?string $state): OAuthConnection
    {
        $client = Client::query()->findOrFail(OAuthState::consume('google', $state));
        $app = $this->app();
        $response = Http::asForm()->timeout(30)->post('https://oauth2.googleapis.com/token', [
            'code' => $code, 'client_id' => $app['client_id'], 'client_secret' => $app['client_secret'],
            'redirect_uri' => $this->redirectUri(), 'grant_type' => 'authorization_code',
        ]);
        if (! $response->successful() || ! is_string($response->json('refresh_token'))) {
            throw new OAuthException('code_exchange_failed');
        }

        $existing = OAuthConnection::query()->where(['client_id' => $client->id, 'platform' => 'ga4'])->first();

        return OAuthConnection::query()->updateOrCreate(['client_id' => $client->id, 'platform' => 'ga4'], [
            'workspace_id' => $client->workspace_id,
            'access_token' => (string) $response->json('access_token'),
            'refresh_token' => (string) $response->json('refresh_token'),
            'expires_at' => now()->addSeconds((int) $response->json('expires_in', 3600) - 60),
            'scopes' => self::SCOPES,
            'status' => $existing?->account_ref !== null ? 'active' : 'needs_selection',
            'last_error_code' => null,
        ]);
    }

    public function setProperty(OAuthConnection $connection, string $propertyId): void
    {
        $connection->update(['account_ref' => $propertyId, 'account_name' => "GA4 {$propertyId}", 'status' => $connection->refresh_token !== null ? 'active' : 'pending']);
    }

    /** A valid access token, refreshed when needed. */
    public function accessToken(OAuthConnection $connection): string
    {
        if ($connection->access_token !== null && $connection->expires_at !== null && $connection->expires_at->isFuture()) {
            return (string) $connection->access_token;
        }

        $app = $this->app();
        $response = Http::asForm()->timeout(30)->post('https://oauth2.googleapis.com/token', [
            'refresh_token' => $connection->refresh_token, 'client_id' => $app['client_id'], 'client_secret' => $app['client_secret'], 'grant_type' => 'refresh_token',
        ]);
        if (! $response->successful() || ! is_string($response->json('access_token'))) {
            $connection->update(['status' => 'error', 'last_error_code' => 'refresh_failed_'.$response->status()]);
            throw new OAuthException('refresh_failed');
        }

        $connection->update(['access_token' => $response->json('access_token'), 'expires_at' => now()->addSeconds((int) $response->json('expires_in', 3600) - 60)]);

        return (string) $response->json('access_token');
    }

    /** @return array{client_id: string, client_secret: string} */
    private function app(): array
    {
        $values = $this->credentials->values(CredentialProvider::GoogleOauth);
        if (! isset($values['client_id'], $values['client_secret'])) {
            throw new OAuthException('google_app_not_configured');
        }

        return ['client_id' => $values['client_id'], 'client_secret' => $values['client_secret']];
    }
}
