<?php

declare(strict_types=1);

use App\Enums\SocialAccount\Platform;
use App\Enums\SocialAccount\Status;
use App\Enums\UserWorkspace\Role;
use App\Models\User;
use App\Models\Workspace;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);
    $this->user->update(['current_workspace_id' => $this->workspace->id]);
    $this->workspace->members()->attach($this->user->id, ['role' => Role::Member->value]);
});

test('reddit connect redirects to the oauth provider', function () {
    $driverMock = Mockery::mock();
    $driverMock->shouldReceive('scopes')->andReturnSelf();
    $driverMock->shouldReceive('redirect')->andReturn(Mockery::mock([
        'getTargetUrl' => 'https://www.reddit.com/api/v1/authorize?test=1',
    ]));

    Socialite::shouldReceive('driver')->with('reddit')->andReturn($driverMock);

    $this->actingAs($this->user)
        ->withHeader('X-Inertia', 'true')
        ->get(route('app.social.reddit.connect'))
        ->assertStatus(409);

    expect(session('social_connect_workspace'))->toBe($this->workspace->id);
});

test('reddit oauth callback creates the account', function () {
    session(['social_connect_workspace' => $this->workspace->id]);

    $socialiteUser = Mockery::mock(SocialiteUser::class);
    $socialiteUser->shouldReceive('getId')->andReturn('t2_abc123');
    $socialiteUser->shouldReceive('getNickname')->andReturn('redditor_name');
    $socialiteUser->shouldReceive('getName')->andReturn('Redditor Name');
    $socialiteUser->shouldReceive('getAvatar')->andReturn(null);
    $socialiteUser->token = 'reddit-access-token';
    $socialiteUser->refreshToken = 'reddit-refresh-token';
    $socialiteUser->expiresIn = 3600;
    $socialiteUser->approvedScopes = ['submit'];

    Socialite::shouldReceive('driver')->with('reddit')->andReturn(Mockery::mock(['user' => $socialiteUser]));

    $response = $this->actingAs($this->user)->get(route('app.social.reddit.callback'));

    $response->assertOk();
    $response->assertViewHas('success', true);

    $this->assertDatabaseHas('social_accounts', [
        'workspace_id' => $this->workspace->id,
        'platform' => Platform::Reddit->value,
        'platform_user_id' => 't2_abc123',
        'status' => Status::Connected->value,
    ]);
});

test('reddit callback fails gracefully on socialite error', function () {
    session(['social_connect_workspace' => $this->workspace->id]);

    $mock = Mockery::mock();
    $mock->shouldReceive('user')->andThrow(new RuntimeException('Reddit OAuth failed.'));

    Socialite::shouldReceive('driver')->with('reddit')->andReturn($mock);

    $response = $this->actingAs($this->user)->get(route('app.social.reddit.callback'));

    $response->assertOk();
    $response->assertViewHas('success', false);

    expect($this->workspace->socialAccounts()->where('platform', Platform::Reddit)->count())->toBe(0);
});
