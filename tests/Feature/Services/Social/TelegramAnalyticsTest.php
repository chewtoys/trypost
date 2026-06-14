<?php

declare(strict_types=1);

use App\Enums\SocialAccount\Platform;
use App\Models\PostPlatform;
use App\Models\SocialAccount;
use App\Services\Social\Telegram\TelegramAnalytics;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['trypost.platforms.telegram.bot_token' => 'TESTTOKEN']);
});

it('returns the channel subscriber count as an account metric', function () {
    $account = SocialAccount::factory()->telegram()->create();

    Http::fake([
        '*/botTESTTOKEN/getChatMemberCount*' => Http::response(['ok' => true, 'result' => 1234], 200),
    ]);

    expect(app(TelegramAnalytics::class)->getMetrics($account))
        ->toBe([['label' => 'Subscribers', 'value' => 1234]]);
});

it('returns no account metrics when the member count call fails', function () {
    $account = SocialAccount::factory()->telegram()->create();

    Http::fake([
        '*/botTESTTOKEN/getChatMemberCount*' => Http::response(['ok' => false], 400),
    ]);

    expect(app(TelegramAnalytics::class)->getMetrics($account))->toBe([]);
});

it('maps stored reactions to post metrics, tagged as reactions', function () {
    config(['trypost.platforms.telegram.bot_token' => '']); // skip the subscriber lookup

    $postPlatform = PostPlatform::factory()->create([
        'platform' => Platform::Telegram,
        'meta' => ['reactions' => [['type' => '👍', 'count' => 12], ['type' => '❤️', 'count' => 5]]],
    ]);

    expect(app(TelegramAnalytics::class)->fetchPostMetrics($postPlatform))
        ->toBe([
            ['label' => '👍', 'value' => 12, 'kind' => 'reaction'],
            ['label' => '❤️', 'value' => 5, 'kind' => 'reaction'],
        ]);
});

it('includes the channel subscriber count alongside reactions', function () {
    $account = SocialAccount::factory()->telegram()->create();
    $postPlatform = PostPlatform::factory()->create([
        'social_account_id' => $account->id,
        'platform' => Platform::Telegram,
        'meta' => ['reactions' => [['type' => '👍', 'count' => 3]]],
    ]);

    Http::fake([
        '*/botTESTTOKEN/getChatMemberCount*' => Http::response(['ok' => true, 'result' => 50], 200),
    ]);

    expect(app(TelegramAnalytics::class)->fetchPostMetrics($postPlatform))
        ->toBe([
            ['label' => 'Subscribers', 'value' => 50, 'kind' => 'subscribers'],
            ['label' => '👍', 'value' => 3, 'kind' => 'reaction'],
        ]);
});

it('returns no post metrics when there are no reactions yet', function () {
    config(['trypost.platforms.telegram.bot_token' => '']);

    $postPlatform = PostPlatform::factory()->create([
        'platform' => Platform::Telegram,
        'meta' => [],
    ]);

    expect(app(TelegramAnalytics::class)->fetchPostMetrics($postPlatform))->toBe([]);
});
