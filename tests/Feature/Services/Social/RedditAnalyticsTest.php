<?php

declare(strict_types=1);

use App\Models\PostPlatform;
use App\Models\SocialAccount;
use App\Models\Workspace;
use App\Services\Social\Reddit\RedditAnalytics;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'trypost.platforms.reddit.api' => 'https://oauth.reddit.com',
        'trypost.platforms.reddit.user_agent' => 'web:it.trypost:1.0',
    ]);
    $this->account = SocialAccount::factory()->reddit()->create(['workspace_id' => Workspace::factory()->create()->id]);
});

test('post metrics sum score and comments across subreddits', function () {
    Http::fake(['https://oauth.reddit.com/api/info*' => Http::response(['data' => ['children' => [
        ['data' => ['name' => 't3_one', 'score' => 10, 'num_comments' => 3]],
        ['data' => ['name' => 't3_two', 'score' => 5, 'num_comments' => 2]],
    ]]], 200)]);

    $platform = PostPlatform::factory()->reddit()->create([
        'social_account_id' => $this->account->id,
        'platform_post_id' => 't3_one,t3_two',
    ]);

    $metrics = app(RedditAnalytics::class)->fetchPostMetrics($platform);

    expect($metrics)->not->toBeEmpty()
        ->and((int) collect($metrics)->firstWhere('kind', 'reaction')['value'])->toBe(15)
        ->and((int) collect($metrics)->firstWhere('kind', 'comments')['value'])->toBe(5);
});

test('account metrics expose total karma', function () {
    Http::fake(['https://oauth.reddit.com/api/v1/me*' => Http::response(['total_karma' => 1234, 'link_karma' => 1000, 'comment_karma' => 234], 200)]);

    $metrics = app(RedditAnalytics::class)->getMetrics($this->account);

    expect((int) $metrics[0]['value'])->toBe(1234);
});

test('post metrics return empty when platform_post_id is blank', function () {
    $platform = PostPlatform::factory()->reddit()->create([
        'social_account_id' => $this->account->id,
        'platform_post_id' => null,
    ]);

    expect(app(RedditAnalytics::class)->fetchPostMetrics($platform))->toBe([]);
});

test('post metrics return empty when info API returns no children', function () {
    Http::fake(['https://oauth.reddit.com/api/info*' => Http::response(['data' => ['children' => []]], 200)]);

    $platform = PostPlatform::factory()->reddit()->create([
        'social_account_id' => $this->account->id,
        'platform_post_id' => 't3_abc',
    ]);

    expect(app(RedditAnalytics::class)->fetchPostMetrics($platform))->toBe([]);
});

test('account metrics return empty when me API throws a connection exception', function () {
    Http::fake(['https://oauth.reddit.com/api/v1/me*' => fn () => throw new ConnectionException('timeout')]);

    $account = SocialAccount::factory()->reddit()->create(['workspace_id' => $this->account->workspace_id]);

    $metrics = app(RedditAnalytics::class)->getMetrics($account);

    expect($metrics)->toBe([]);
});
