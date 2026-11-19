<?php

namespace App\Services\Connections;

use App\Enums\CredentialProvider;
use App\Models\Client;
use App\Models\OAuthConnection;
use App\Services\Credentials\CredentialStore;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * F1.2 Instagram via Facebook Login: user token → long-lived token → the Page that owns the IG business account
 * → Page token (does not expire when derived from a long-lived user token). Needs a Meta app with
 * instagram_content_publish (App Review, or the client added as an app tester in development mode).
 */
class MetaConnection
{
    public const SCOPES = ['instagram_basic', 'instagram_content_publish', 'instagram_manage_insights', 'pages_show_list', 'pages_read_engagement', 'business_management'];

    public function __construct(private readonly CredentialStore $credentials) {}

    public function redirectUri(): string
    {
        return url('/oauth/meta/callback');
    }

    public function authorizeUrl(Client $client): string
    {
        $values = $this->app();

        return "https://www.facebook.com/{$values['graph_version']}/dialog/oauth?".http_build_query([
            'client_id' => $values['app_id'],
            'redirect_uri' => $this->redirectUri(),
            'state' => OAuthState::issue('meta', $client->id),
            'scope' => implode(',', self::SCOPES),
            'response_type' => 'code',
        ]);
    }

    public function complete(string $code, ?string $state): OAuthConnection
    {
        $client = Client::query()->findOrFail(OAuthState::consume('meta', $state));
        $app = $this->app();

        $short = $this->graph()->get('oauth/access_token', [
            'client_id' => $app['app_id'], 'client_secret' => $app['app_secret'], 'redirect_uri' => $this->redirectUri(), 'code' => $code,
        ]);
        $userToken = $short->successful() ? $short->json('access_token') : null;
        if (! is_string($userToken)) {
            throw new OAuthException('code_exchange_failed');
        }

        $long = $this->graph()->get('oauth/access_token', [
            'grant_type' => 'fb_exchange_token', 'client_id' => $app['app_id'], 'client_secret' => $app['app_secret'], 'fb_exchange_token' => $userToken,
        ]);
        $userToken = is_string($long->json('access_token')) ? $long->json('access_token') : $userToken;

        $candidates = array_map(fn (array $p) => [
            'page_id' => (string) $p['id'],
            'page_name' => (string) ($p['name'] ?? ''),
            'ig_id' => (string) $p['instagram_business_account']['id'],
            'ig_username' => (string) ($p['instagram_business_account']['username'] ?? ''),
        ], $this->pages($userToken));

        if ($candidates === []) {
            throw new OAuthException('no_instagram_business_account');
        }

        $connection = OAuthConnection::query()->updateOrCreate(['client_id' => $client->id, 'platform' => 'instagram'], [
            'workspace_id' => $client->workspace_id,
            'access_token' => $userToken,
            'scopes' => self::SCOPES,
            'meta' => ['candidates' => $candidates],
            'status' => 'needs_selection',
            'account_ref' => null,
            'account_name' => null,
            'last_error_code' => null,
        ]);

        return count($candidates) === 1 ? $this->select($connection, $candidates[0]['ig_id']) : $connection;
    }

    /** Switches the stored user token for the Page token of the chosen IG account. */
    public function select(OAuthConnection $connection, string $igId): OAuthConnection
    {
        if ($connection->status !== 'needs_selection') {
            throw new OAuthException('selection_not_pending');
        }
        $userToken = (string) $connection->access_token;
        foreach ($this->pages($userToken) as $page) {
            if ((string) $page['instagram_business_account']['id'] === $igId) {
                $connection->update([
                    'account_ref' => $igId,
                    'account_name' => '@'.($page['instagram_business_account']['username'] ?? $igId),
                    'access_token' => (string) $page['access_token'],
                    'meta' => ['page_id' => (string) $page['id'], 'page_name' => (string) ($page['name'] ?? '')],
                    'status' => 'active',
                    'expires_at' => null,
                ]);

                return $connection;
            }
        }

        throw new OAuthException('account_not_found');
    }

    public function graph(): PendingRequest
    {
        return Http::timeout(30)->baseUrl('https://graph.facebook.com/'.$this->app()['graph_version'].'/');
    }

    /** @return list<array<string, mixed>> pages that have an IG business account */
    private function pages(string $userToken): array
    {
        $response = $this->graph()->get('me/accounts', [
            'fields' => 'id,name,access_token,instagram_business_account{id,username}', 'limit' => 50, 'access_token' => $userToken,
        ]);
        if (! $response->successful()) {
            throw new OAuthException('pages_lookup_failed');
        }

        return array_values(array_filter((array) $response->json('data', []), fn ($p) => is_array($p) && isset($p['instagram_business_account']['id'], $p['access_token'])));
    }

    /** @return array{app_id: string, app_secret: string, graph_version: string} */
    private function app(): array
    {
        $values = $this->credentials->values(CredentialProvider::Meta);
        if (! isset($values['app_id'], $values['app_secret'], $values['graph_version'])) {
            throw new OAuthException('meta_app_not_configured');
        }

        return ['app_id' => $values['app_id'], 'app_secret' => $values['app_secret'], 'graph_version' => $values['graph_version']];
    }
}
