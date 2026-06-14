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

it('issues a signed connect code and stores it in the session', function () {
    $response = $this->actingAs($this->user)
        ->postJson(route('app.social.telegram.connect'))
        ->assertOk()
        ->assertJsonStructure(['code', 'bot_username', 'expires_at'])
        ->assertSessionHas('telegram_connect_code');

    expect($response->json('bot_username'))->toBe('TryPostBot');
    expect(data_get(TelegramConnectCode::decode($response->json('code')), 'workspace_id'))
        ->toBe($this->workspace->id);
});

it('links the channel when the webhook receives a matching /connect', function () {
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

it('links a private channel that has no username', function () {
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

it('reports the session connection status while pending and once connected', function () {
    $code = TelegramConnectCode::issue($this->workspace->id, now()->addMinutes(15));
    $nonce = data_get(TelegramConnectCode::decode($code), 'nonce');

    $this->actingAs($this->user)
        ->withSession(['telegram_connect_code' => $code])
        ->getJson(route('app.social.telegram.status'))
        ->assertOk()
        ->assertJson(['status' => 'pending']);

    SocialAccount::factory()->telegram()->create([
        'workspace_id' => $this->workspace->id,
        'meta' => ['chat_id' => '-1001234567890', 'username' => 'mychannel', 'type' => 'channel', 'connect_nonce' => $nonce],
    ]);

    $this->actingAs($this->user)
        ->withSession(['telegram_connect_code' => $code])
        ->getJson(route('app.social.telegram.status'))
        ->assertOk()
        ->assertJson(['status' => 'connected']);
});

it('reports unknown status without a session code', function () {
    $this->actingAs($this->user)
        ->getJson(route('app.social.telegram.status'))
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
