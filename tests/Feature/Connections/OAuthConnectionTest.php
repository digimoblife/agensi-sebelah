<?php

use App\Enums\CredentialProvider;
use App\Services\Connections\GoogleConnection;
use App\Services\Connections\MetaConnection;
use App\Services\Connections\OAuthException;
use App\Services\Credentials\CredentialStore;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;

beforeEach(function () {
    $this->client = client();
    app(CredentialStore::class)->save(CredentialProvider::Meta, ['app_id' => '1234567', 'app_secret' => str_repeat('s', 32), 'graph_version' => 'v24.0']);
    app(CredentialStore::class)->save(CredentialProvider::GoogleOauth, ['client_id' => 'abc.apps.googleusercontent.com', 'client_secret' => str_repeat('g', 24)]);
});

function stateFrom(string $url): string
{
    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

    return (string) $query['state'];
}

function metaPages(array $pages): void
{
    Http::fake([
        'graph.facebook.com/v24.0/oauth/access_token*' => Http::response(['access_token' => 'user-token']),
        'graph.facebook.com/v24.0/me/accounts*' => Http::response(['data' => $pages]),
    ]);
}

it('builds the Facebook Login URL with publishing scopes and a session-bound state', function () {
    $url = app(MetaConnection::class)->authorizeUrl($this->client);

    expect($url)->toStartWith('https://www.facebook.com/v24.0/dialog/oauth?')->toContain('instagram_content_publish')
        ->and(Session::get('oauth.meta.client_id'))->toBe($this->client->id);
});

it('connects the only Instagram business account with its page token', function () {
    metaPages([['id' => 'p1', 'name' => 'Page', 'access_token' => 'page-token', 'instagram_business_account' => ['id' => '1789', 'username' => 'studio']]]);
    $state = stateFrom(app(MetaConnection::class)->authorizeUrl($this->client));

    $connection = app(MetaConnection::class)->complete('code123', $state);

    expect($connection->status)->toBe('active')->and($connection->account_ref)->toBe('1789')
        ->and($connection->access_token)->toBe('page-token')->and($connection->account_name)->toBe('@studio')
        ->and(DB::table('oauth_connections')->value('access_token'))->not->toContain('page-token');
});

it('asks the admin to choose when several accounts are available', function () {
    metaPages([
        ['id' => 'p1', 'name' => 'A', 'access_token' => 't1', 'instagram_business_account' => ['id' => '1', 'username' => 'a']],
        ['id' => 'p2', 'name' => 'B', 'access_token' => 't2', 'instagram_business_account' => ['id' => '2', 'username' => 'b']],
        ['id' => 'p3', 'name' => 'No IG', 'access_token' => 't3'],
    ]);
    $state = stateFrom(app(MetaConnection::class)->authorizeUrl($this->client));

    $connection = app(MetaConnection::class)->complete('code', $state);
    expect($connection->status)->toBe('needs_selection')->and($connection->meta['candidates'])->toHaveCount(2);

    app(MetaConnection::class)->select($connection, '2');
    expect($connection->fresh()->access_token)->toBe('t2')->and($connection->fresh()->status)->toBe('active');
});

it('rejects a callback with a forged or reused state', function () {
    app(MetaConnection::class)->authorizeUrl($this->client);

    app(MetaConnection::class)->complete('code', 'forged');
})->throws(OAuthException::class, 'invalid_state');

it('stores a GA4 refresh token, needs a property id, and refreshes expired access tokens', function () {
    Http::fake(['oauth2.googleapis.com/token' => Http::sequence()
        ->push(['access_token' => 'a1', 'refresh_token' => 'r1', 'expires_in' => 3600])
        ->push(['access_token' => 'a2', 'expires_in' => 3600])]);
    $google = app(GoogleConnection::class);
    $state = stateFrom($google->authorizeUrl($this->client));

    $connection = $google->complete('code', $state);
    expect($connection->status)->toBe('needs_selection')->and($connection->refresh_token)->toBe('r1');

    $google->setProperty($connection, '123456789');
    $connection->update(['expires_at' => now()->subMinute()]);

    expect($connection->fresh()->status)->toBe('active')->and($google->accessToken($connection->fresh()))->toBe('a2');
});
