<?php

use Illuminate\Support\Facades\Http;
use Inovector\Mixpost\Facades\ServiceManager;
use Inovector\Mixpost\Models\Account;
use Inovector\Mixpost\SocialProviders\Meta\Jobs\RefreshFacebookAccessTokenJob;

beforeEach(function () {
    ServiceManager::put('facebook', [
        'client_id' => 'app-id',
        'client_secret' => 'app-secret',
        'api_version' => 'v20.0',
    ], true);
});

it('refreshes a facebook token that is about to expire and preserves other token data', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response([
            'access_token' => 'new-long-lived-token',
            'token_type' => 'bearer',
            'expires_in' => 5184000,
        ]),
    ]);

    $account = Account::factory()->create([
        'provider' => 'facebook_page',
        'access_token' => [
            'access_token' => 'old-token',
            'page_access_token' => 'page-token',
            'expires_at' => now()->addDay()->timestamp,
        ],
    ]);

    (new RefreshFacebookAccessTokenJob($account))->handle();

    $token = $account->refresh()->access_token->toArray();

    expect($token['access_token'])->toBe('new-long-lived-token')
        ->and($token['page_access_token'])->toBe('page-token')
        ->and($token)->toHaveKey('expires_at')
        ->and($token['expires_at'])->toBeGreaterThan(now()->addDays(30)->timestamp);
});

it('does not overwrite the stored token when the refresh fails', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response(['error' => ['message' => 'bad', 'code' => 100]], 400),
    ]);

    $account = Account::factory()->create([
        'provider' => 'facebook_page',
        'access_token' => [
            'access_token' => 'old-token',
            'expires_at' => now()->addDay()->timestamp,
        ],
    ]);

    (new RefreshFacebookAccessTokenJob($account))->handle();

    expect($account->refresh()->access_token->toArray()['access_token'])->toBe('old-token');
});

it('marks the account unauthorized when facebook reports an invalid token', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response(['error' => ['message' => 'expired', 'code' => 190]], 400),
    ]);

    $account = Account::factory()->create([
        'provider' => 'facebook_page',
        'authorized' => true,
        'access_token' => [
            'access_token' => 'old-token',
            'expires_at' => now()->addDay()->timestamp,
        ],
    ]);

    (new RefreshFacebookAccessTokenJob($account))->handle();

    expect($account->refresh()->isUnauthorized())->toBeTrue();
});

it('skips refreshing a token that is not close to expiring', function () {
    Http::fake();

    $account = Account::factory()->create([
        'provider' => 'facebook_page',
        'access_token' => [
            'access_token' => 'old-token',
            'expires_at' => now()->addDays(40)->timestamp,
        ],
    ]);

    (new RefreshFacebookAccessTokenJob($account))->handle();

    Http::assertNothingSent();
    expect($account->refresh()->access_token->toArray()['access_token'])->toBe('old-token');
});
