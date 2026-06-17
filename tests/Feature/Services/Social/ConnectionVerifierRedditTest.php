<?php

declare(strict_types=1);

use App\Exceptions\TokenExpiredException;
use App\Models\SocialAccount;
use App\Services\Social\ConnectionVerifier;
use Illuminate\Support\Facades\Http;

test('verifies reddit account with valid token', function () {
    Http::fake([
        config('trypost.platforms.reddit.api').'/api/v1/me' => Http::response(['name' => 'testuser'], 200),
    ]);

    $account = SocialAccount::factory()->reddit()->create([
        'token_expires_at' => now()->addDays(30),
    ]);

    $result = (new ConnectionVerifier)->verify($account);

    expect($result)->toBeTrue();
    Http::assertSent(fn ($request) => str_contains($request->url(), '/api/v1/me'));
});

test('throws TokenExpiredException when reddit verify returns 401', function () {
    Http::fake([
        config('trypost.platforms.reddit.api').'/api/v1/me' => Http::response([], 401),
    ]);

    $account = SocialAccount::factory()->reddit()->create([
        'token_expires_at' => now()->addDays(30),
    ]);

    expect(fn () => (new ConnectionVerifier)->verify($account))
        ->toThrow(TokenExpiredException::class, 'Reddit access token is invalid or expired');
});

test('refreshing a reddit token stores the new access token', function () {
    Http::fake([
        config('trypost.platforms.reddit.oauth_api').'/access_token' => Http::response([
            'access_token' => 'new-access',
            'expires_in' => 3600,
        ], 200),
    ]);

    $account = SocialAccount::factory()->reddit()->create([
        'access_token' => 'old-access',
        'refresh_token' => 'reddit-refresh-token',
    ]);

    (new ConnectionVerifier)->refreshToken($account);

    expect($account->fresh()->access_token)->toBe('new-access');
});

test('refreshes reddit token before verifying when expired', function () {
    Http::fake([
        config('trypost.platforms.reddit.oauth_api').'/access_token' => Http::response([
            'access_token' => 'new-access',
            'expires_in' => 3600,
        ], 200),
        config('trypost.platforms.reddit.api').'/api/v1/me' => Http::response(['name' => 'testuser'], 200),
    ]);

    $account = SocialAccount::factory()->reddit()->create([
        'token_expires_at' => now()->subHour(),
        'access_token' => 'old-access',
        'refresh_token' => 'reddit-refresh-token',
    ]);

    $result = (new ConnectionVerifier)->verify($account);

    expect($result)->toBeTrue();
    expect($account->fresh()->access_token)->toBe('new-access');

    Http::assertSent(fn ($request) => str_contains($request->url(), '/access_token'));
});

test('throws TokenExpiredException when reddit has no refresh token', function () {
    $account = SocialAccount::factory()->reddit()->create([
        'token_expires_at' => now()->subHour(),
        'refresh_token' => null,
    ]);

    expect(fn () => (new ConnectionVerifier)->refreshToken($account))
        ->toThrow(TokenExpiredException::class, 'No refresh token available for Reddit account');
});
