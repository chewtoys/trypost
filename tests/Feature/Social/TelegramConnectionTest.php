<?php

declare(strict_types=1);

use App\Enums\SocialAccount\Platform;
use App\Enums\UserWorkspace\Role;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Social\ConnectionVerifier;
use App\Services\Social\TelegramConnectCode;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config([
        'trypost.platforms.telegram.bot_token' => 'TESTTOKEN',
        'trypost.platforms.telegram.bot_username' => 'TryPostBot',
        'trypost.platforms.telegram.webhook_secret' => 'shh-secret',
    ]);

    $this->workspace = Workspace::factory()->create();
    $this->user = User::factory()->create([
        'current_workspace_id' => $this->workspace->id,
        'account_id' => $this->workspace->account_id,
    ]);
    $this->workspace->members()->attach($this->user->id, ['role' => Role::Admin->value]);
    $this->user->refresh();
});

function telegramUpdate(string $code, array $chat = []): array
{
    return [
        'channel_post' => [
            'message_id' => 5,
            'chat' => array_merge([
                'id' => -1001234567890,
                'title' => 'My Channel',
                'username' => 'mychannel',
                'type' => 'channel',
            ], $chat),
            'text' => "/connect {$code}",
        ],
    ];
}

it('issues a signed connect code carrying the workspace', function () {
    $response = $this->actingAs($this->user)
        ->postJson(route('app.social.telegram.connect'))
        ->assertOk()
        ->assertJsonStructure(['code', 'bot_username', 'expires_at']);

    expect($response->json('bot_username'))->toBe('TryPostBot');
    expect(data_get(TelegramConnectCode::decode($response->json('code')), 'workspace_id'))
        ->toBe($this->workspace->id);
});

it('links the channel when the webhook receives a matching /connect', function () {
    Http::fake();
    $code = TelegramConnectCode::issue($this->workspace->id, now()->addMinutes(15));

    $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'shh-secret')
        ->postJson(route('telegram.webhook'), telegramUpdate($code))
        ->assertNoContent();

    $account = SocialAccount::where('workspace_id', $this->workspace->id)
        ->where('platform', Platform::Telegram)
        ->first();

    expect($account)->not->toBeNull();
    expect($account->platform_user_id)->toBe('-1001234567890');
    expect($account->display_name)->toBe('My Channel');
    expect($account->username)->toBe('mychannel');
    expect(data_get($account->meta, 'chat_id'))->toBe('-1001234567890');
    expect(data_get($account->meta, 'connect_nonce'))
        ->toBe(data_get(TelegramConnectCode::decode($code), 'nonce'));
});

it('stores the channel photo as the account avatar on connect', function () {
    Storage::fake();

    Http::fake([
        '*/botTESTTOKEN/getChat*' => Http::response(['ok' => true, 'result' => ['photo' => ['big_file_id' => 'BIGFILE']]], 200),
        '*/botTESTTOKEN/getFile*' => Http::response(['ok' => true, 'result' => ['file_path' => 'photos/file_1.jpg']], 200),
        '*/file/botTESTTOKEN/photos/file_1.jpg' => Http::response('image-bytes', 200, ['Content-Type' => 'image/jpeg']),
    ]);

    $code = TelegramConnectCode::issue($this->workspace->id, now()->addMinutes(15));

    $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'shh-secret')
        ->postJson(route('telegram.webhook'), telegramUpdate($code))
        ->assertNoContent();

    $account = SocialAccount::where('platform', Platform::Telegram)->first();

    expect($account->getRawOriginal('avatar_url'))->not->toBeNull();
});

it('links a private channel that has no username', function () {
    Http::fake();
    $code = TelegramConnectCode::issue($this->workspace->id, now()->addMinutes(15));

    $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'shh-secret')
        ->postJson(route('telegram.webhook'), telegramUpdate($code, ['username' => null]))
        ->assertNoContent();

    $account = SocialAccount::where('platform', Platform::Telegram)->first();

    expect($account->username)->toBeNull();
    expect($account->display_name)->toBe('My Channel');
    expect(data_get($account->meta, 'username'))->toBeNull();
});

it('does not create a new telegram account when the workspace is at its limit', function () {
    config(['trypost.self_hosted' => false]);

    SocialAccount::factory()->count(5)->create(['workspace_id' => $this->workspace->id]);

    $code = TelegramConnectCode::issue($this->workspace->id, now()->addMinutes(15));

    $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'shh-secret')
        ->postJson(route('telegram.webhook'), telegramUpdate($code))
        ->assertNoContent();

    expect($this->workspace->socialAccounts()->count())->toBe(5);
    expect(
        SocialAccount::where('platform', Platform::Telegram)->where('platform_user_id', '-1001234567890')->exists()
    )->toBeFalse();
});

it('still reconnects an existing telegram channel even at the account limit', function () {
    Http::fake();
    config(['trypost.self_hosted' => false]);

    SocialAccount::factory()->count(4)->create(['workspace_id' => $this->workspace->id]);
    SocialAccount::factory()->telegram()->create([
        'workspace_id' => $this->workspace->id,
        'platform_user_id' => '-1001234567890',
    ]);

    $code = TelegramConnectCode::issue($this->workspace->id, now()->addMinutes(15));

    $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'shh-secret')
        ->postJson(route('telegram.webhook'), telegramUpdate($code))
        ->assertNoContent();

    expect($this->workspace->socialAccounts()->count())->toBe(5);
    expect(
        SocialAccount::where('platform', Platform::Telegram)->where('platform_user_id', '-1001234567890')->count()
    )->toBe(1);
});

it('consumes the code once so it cannot be replayed for another chat', function () {
    Http::fake();
    $code = TelegramConnectCode::issue($this->workspace->id, now()->addMinutes(15));

    $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'shh-secret')
        ->postJson(route('telegram.webhook'), telegramUpdate($code, ['id' => -1001111111111]))
        ->assertNoContent();

    $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'shh-secret')
        ->postJson(route('telegram.webhook'), telegramUpdate($code, ['id' => -1002222222222]))
        ->assertNoContent();

    expect(SocialAccount::where('platform', Platform::Telegram)->count())->toBe(1);
    expect(
        SocialAccount::where('platform', Platform::Telegram)->where('platform_user_id', '-1001111111111')->exists()
    )->toBeTrue();
});

it('rejects the webhook without the secret token', function () {
    $code = TelegramConnectCode::issue($this->workspace->id, now()->addMinutes(15));

    $this->postJson(route('telegram.webhook'), telegramUpdate($code))
        ->assertForbidden();
});

it('ignores the webhook for a tampered or expired code', function () {
    $expired = TelegramConnectCode::issue($this->workspace->id, now()->subMinute());

    $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'shh-secret')
        ->postJson(route('telegram.webhook'), telegramUpdate($expired))
        ->assertNoContent();

    $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'shh-secret')
        ->postJson(route('telegram.webhook'), telegramUpdate('not-a-valid-code'))
        ->assertNoContent();

    expect(SocialAccount::where('platform', Platform::Telegram)->count())->toBe(0);
});

it('reports the connection status for a code while pending and once connected', function () {
    $code = TelegramConnectCode::issue($this->workspace->id, now()->addMinutes(15));
    $nonce = data_get(TelegramConnectCode::decode($code), 'nonce');

    $this->actingAs($this->user)
        ->getJson(route('app.social.telegram.status', ['code' => $code]))
        ->assertOk()
        ->assertJson(['status' => 'pending']);

    SocialAccount::factory()->telegram()->create([
        'workspace_id' => $this->workspace->id,
        'meta' => ['chat_id' => '-1001234567890', 'username' => 'mychannel', 'type' => 'channel', 'connect_nonce' => $nonce],
    ]);

    $this->actingAs($this->user)
        ->getJson(route('app.social.telegram.status', ['code' => $code]))
        ->assertOk()
        ->assertJson(['status' => 'connected']);
});

it('reports unknown status without a valid code', function () {
    $this->actingAs($this->user)
        ->getJson(route('app.social.telegram.status'))
        ->assertOk()
        ->assertJson(['status' => 'unknown']);

    $this->actingAs($this->user)
        ->getJson(route('app.social.telegram.status', ['code' => 'tampered']))
        ->assertOk()
        ->assertJson(['status' => 'unknown']);
});

it('verifies a connected telegram account via getChat', function () {
    config(['trypost.platforms.telegram.bot_token' => 'TESTTOKEN']);

    $account = SocialAccount::factory()->telegram()->create(['workspace_id' => $this->workspace->id]);

    Http::fake([
        '*/botTESTTOKEN/getChat*' => Http::response(['ok' => true, 'result' => ['id' => -1001234567890]], 200),
    ]);

    expect(app(ConnectionVerifier::class)->verify($account))->toBeTrue();
});

it('reports a telegram account as invalid when getChat fails', function () {
    config(['trypost.platforms.telegram.bot_token' => 'TESTTOKEN']);

    $account = SocialAccount::factory()->telegram()->create(['workspace_id' => $this->workspace->id]);

    Http::fake([
        '*/botTESTTOKEN/getChat*' => Http::response(['ok' => false, 'description' => 'chat not found'], 400),
    ]);

    expect(app(ConnectionVerifier::class)->verify($account))->toBeFalse();
});

it('registers the webhook via the artisan command', function () {
    Http::fake([
        '*/botTESTTOKEN/setWebhook' => Http::response(['ok' => true, 'result' => true], 200),
    ]);

    $this->artisan('telegram:set-webhook')->assertSuccessful();

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/setWebhook')
            && $request['secret_token'] === 'shh-secret'
            && str_contains($request['url'], 'telegram/webhook');
    });
});
