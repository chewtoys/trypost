<?php

declare(strict_types=1);

use App\Enums\PostPlatform\ContentType;
use App\Enums\SocialAccount\Platform;
use App\Models\Post;
use App\Models\PostPlatform;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Social\MastodonAnalytics;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);
    $this->post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
    ]);
});

test('mastodon analytics falls back to configured default instance', function () {
    $defaultInstance = (string) config('trypost.platforms.mastodon.default_instance');

    Http::fake([
        "{$defaultInstance}/api/v1/statuses/*" => Http::response([
            'favourites_count' => 5,
            'reblogs_count' => 2,
            'replies_count' => 1,
        ], 200),
    ]);

    $account = SocialAccount::factory()->mastodon()->create([
        'workspace_id' => $this->workspace->id,
        'meta' => [],
    ]);
    $postPlatform = PostPlatform::factory()->create([
        'post_id' => $this->post->id,
        'social_account_id' => $account->id,
        'platform' => Platform::Mastodon,
        'content_type' => ContentType::MastodonPost,
        'platform_post_id' => '109876543210',
    ]);

    $metrics = (new MastodonAnalytics)->fetchPostMetrics($postPlatform);

    expect($metrics)->toBeArray();
    Http::assertSent(fn ($request) => str_starts_with($request->url(), $defaultInstance.'/api/v1/statuses/'));
});

test('mastodon analytics honors per-account instance override', function () {
    Http::fake([
        'techhub.social/api/v1/statuses/*' => Http::response([
            'favourites_count' => 0, 'reblogs_count' => 0, 'replies_count' => 0,
        ], 200),
    ]);

    $account = SocialAccount::factory()->mastodon()->create([
        'workspace_id' => $this->workspace->id,
        'meta' => ['instance' => 'https://techhub.social'],
    ]);
    $postPlatform = PostPlatform::factory()->create([
        'post_id' => $this->post->id,
        'social_account_id' => $account->id,
        'platform' => Platform::Mastodon,
        'content_type' => ContentType::MastodonPost,
        'platform_post_id' => '999',
    ]);

    (new MastodonAnalytics)->fetchPostMetrics($postPlatform);

    Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://techhub.social/api/v1/statuses/'));
});
