<?php

declare(strict_types=1);

use App\Enums\PostPlatform\ContentType;
use App\Enums\SocialAccount\Platform;
use App\Models\Post;
use App\Models\PostPlatform;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Social\LinkedInPageAnalytics;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);
    $this->socialAccount = SocialAccount::factory()->linkedinPage()->create([
        'workspace_id' => $this->workspace->id,
        'token_expires_at' => now()->subHour(),
        'refresh_token' => 'old_refresh_token',
    ]);
    $this->post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
    ]);
    $this->postPlatform = PostPlatform::factory()->create([
        'post_id' => $this->post->id,
        'social_account_id' => $this->socialAccount->id,
        'platform' => Platform::LinkedInPage,
        'content_type' => ContentType::LinkedInPagePost,
        'platform_post_id' => 'urn:li:share:1234567890',
    ]);
});

test('linkedin page analytics refresh hits the configured oauth host', function () {
    $oauthApi = config('trypost.platforms.linkedin.oauth_api');
    $api = config('trypost.platforms.linkedin-page.api');

    Http::fake([
        "{$oauthApi}/oauth/v2/accessToken" => Http::response([
            'access_token' => 'new_token',
            'refresh_token' => 'new_refresh_token',
            'expires_in' => 5184000,
        ], 200),
        "{$api}/rest/socialActions/*" => Http::response([
            'likesSummary' => ['totalLikes' => 0],
            'commentsSummary' => ['aggregatedTotalComments' => 0],
        ], 200),
    ]);

    (new LinkedInPageAnalytics)->fetchPostMetrics($this->postPlatform);

    Http::assertSent(fn ($request) => str_contains($request->url(), "{$oauthApi}/oauth/v2/accessToken"));
});
