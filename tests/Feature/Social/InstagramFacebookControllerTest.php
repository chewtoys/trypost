<?php

declare(strict_types=1);

use App\Enums\SocialAccount\Platform;
use App\Enums\UserWorkspace\Role;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);
    $this->user->update(['current_workspace_id' => $this->workspace->id]);
    $this->workspace->members()->attach($this->user->id, ['role' => Role::Member->value]);
});

test('instagram-facebook select connects the page in self-hosted mode', function () {
    config()->set('trypost.self_hosted', true);

    session([
        'social_connect_workspace' => $this->workspace->id,
        'instagram_facebook_oauth' => [
            'user_token' => 'user-token',
            'reconnect_id' => null,
            'pages' => [
                [
                    'page_id' => 'page-1',
                    'page_name' => 'My Page',
                    'page_access_token' => 'page-token',
                    'ig_id' => 'ig-new',
                    'ig_username' => 'mybiz',
                    'ig_name' => 'My Biz',
                    'ig_picture' => null,
                ],
            ],
        ],
    ]);

    $response = $this->actingAs($this->user)->post(route('app.social.instagram-facebook.select'), [
        'page_id' => 'page-1',
    ]);

    $response->assertOk();
    $response->assertInertia(fn (AssertableInertia $page) => $page->component('accounts/PopupCallback'));
    $response->assertInertia(fn (AssertableInertia $page) => $page->where('success', true));

    $this->assertDatabaseHas('social_accounts', [
        'workspace_id' => $this->workspace->id,
        'platform' => Platform::InstagramFacebook->value,
        'platform_user_id' => 'ig-new',
        'username' => 'mybiz',
    ]);
});

test('instagram-facebook select shows network_taken when a standalone instagram is already connected', function () {
    config()->set('trypost.self_hosted', false);

    SocialAccount::factory()->create([
        'workspace_id' => $this->workspace->id,
        'platform' => Platform::Instagram,
        'platform_user_id' => 'existing-ig',
    ]);

    session([
        'social_connect_workspace' => $this->workspace->id,
        'instagram_facebook_oauth' => [
            'user_token' => 'user-token',
            'reconnect_id' => null,
            'pages' => [
                [
                    'page_id' => 'page-1',
                    'page_name' => 'My Page',
                    'page_access_token' => 'page-token',
                    'ig_id' => 'ig-new',
                    'ig_username' => 'mybiz',
                    'ig_name' => 'My Biz',
                    'ig_picture' => null,
                ],
            ],
        ],
    ]);

    $response = $this->actingAs($this->user)->post(route('app.social.instagram-facebook.select'), [
        'page_id' => 'page-1',
    ]);

    $response->assertOk();
    $response->assertInertia(fn (AssertableInertia $page) => $page->component('accounts/PopupCallback'));
    $response->assertInertia(fn (AssertableInertia $page) => $page->where('success', false));
    $response->assertInertia(fn (AssertableInertia $page) => $page->where('message', __('accounts.popup_callback.network_taken')));

    expect($this->workspace->socialAccounts()->whereIn('platform', [Platform::Instagram->value, Platform::InstagramFacebook->value])->count())->toBe(1);
});
