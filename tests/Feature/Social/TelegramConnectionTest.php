<?php

declare(strict_types=1);

use App\Enums\SocialAccount\Platform;
use App\Enums\UserWorkspace\Role;
use App\Models\SocialAccount;
use App\Models\TelegramConnectRequest;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Social\ConnectionVerifier;
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

it('issues a connect code', function () {
    $response = $this->actingAs($this->user)
        ->postJson(route('app.social.telegram.connect'))
        ->assertOk()
        ->assertJsonStructure(['code', 'bot_username', 'expires_at']);

    expect($response->json('bot_username'))->toBe('TryPostBot');

    $this->assertDatabaseHas('telegram_connect_requests', [
        'workspace_id' => $this->workspace->id,
        'code' => $response->json('code'),
        'social_account_id' => null,
    ]);
});

it('links the channel when the webhook receives a matching /connect', function () {
    $request = TelegramConnectRequest::create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'code' => 'abc123code',
        'expires_at' => now()->addMinutes(15),
    ]);

    $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'shh-secret')
        ->postJson(route('telegram.webhook'), telegramUpdate('abc123code'))
        ->assertNoContent();

    $account = SocialAccount::where('workspace_id', $this->workspace->id)
        ->where('platform', Platform::Telegram)
        ->first();

    expect($account)->not->toBeNull();
    expect($account->platform_user_id)->toBe('-1001234567890');
    expect($account->display_name)->toBe('My Channel');
    expect($account->username)->toBe('mychannel');
    expect(data_get($account->meta, 'chat_id'))->toBe('-1001234567890');

    expect($request->fresh()->social_account_id)->toBe($account->id);
});

it('links a private channel that has no username', function () {
    TelegramConnectRequest::create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'code' => 'privatecode',
        'expires_at' => now()->addMinutes(15),
    ]);

    $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'shh-secret')
        ->postJson(route('telegram.webhook'), telegramUpdate('privatecode', ['username' => null]))
        ->assertNoContent();

    $account = SocialAccount::where('platform', Platform::Telegram)->first();

    expect($account->username)->toBeNull();
    expect($account->display_name)->toBe('My Channel');
    expect(data_get($account->meta, 'username'))->toBeNull();
});

it('rejects the webhook without the secret token', function () {
    $this->postJson(route('telegram.webhook'), telegramUpdate('whatever'))
        ->assertForbidden();
});

it('ignores the webhook for an unknown or expired code', function () {
    TelegramConnectRequest::create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'code' => 'expiredcode',
        'expires_at' => now()->subMinute(),
    ]);

    $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'shh-secret')
        ->postJson(route('telegram.webhook'), telegramUpdate('expiredcode'))
        ->assertNoContent();

    $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'shh-secret')
        ->postJson(route('telegram.webhook'), telegramUpdate('does-not-exist'))
        ->assertNoContent();

    expect(SocialAccount::where('platform', Platform::Telegram)->count())->toBe(0);
});

it('reports connection status while pending and once connected', function () {
    $request = TelegramConnectRequest::create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'code' => 'statuscode',
        'expires_at' => now()->addMinutes(15),
    ]);

    $this->actingAs($this->user)
        ->getJson(route('app.social.telegram.status', ['code' => 'statuscode']))
        ->assertOk()
        ->assertJson(['status' => 'pending']);

    $account = SocialAccount::factory()->telegram()->create(['workspace_id' => $this->workspace->id]);
    $request->update(['social_account_id' => $account->id]);

    $this->actingAs($this->user)
        ->getJson(route('app.social.telegram.status', ['code' => 'statuscode']))
        ->assertOk()
        ->assertJson(['status' => 'connected']);
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
