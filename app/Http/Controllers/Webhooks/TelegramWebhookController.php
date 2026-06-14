<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhooks;

use App\Enums\SocialAccount\Platform as SocialPlatform;
use App\Enums\SocialAccount\Status;
use App\Features\SocialAccountLimit;
use App\Http\Controllers\Controller;
use App\Models\TelegramConnectRequest;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Laravel\Pennant\Feature;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class TelegramWebhookController extends Controller
{
    /**
     * Receives Bot API updates. The only update we act on is a `/connect <code>`
     * message/channel_post: it ties the originating channel to the workspace that
     * generated the code. Everything else is acknowledged and ignored.
     */
    public function handle(Request $request): Response
    {
        $secret = (string) config('trypost.platforms.telegram.webhook_secret');

        abort_if(
            $secret === '' || ! hash_equals($secret, (string) $request->header('X-Telegram-Bot-Api-Secret-Token')),
            SymfonyResponse::HTTP_FORBIDDEN,
        );

        $update = $request->all();
        $chat = data_get($update, 'message.chat') ?? data_get($update, 'channel_post.chat');
        $text = data_get($update, 'message.text') ?? data_get($update, 'channel_post.text');

        if (! is_array($chat) || ! is_string($text) || ! preg_match('/^\/connect(?:@\S+)?\s+(\S+)/', $text, $matches)) {
            return response()->noContent();
        }

        $connectRequest = TelegramConnectRequest::query()
            ->whereNull('social_account_id')
            ->where('code', $matches[1])
            ->where('expires_at', '>', now())
            ->first();

        if ($connectRequest === null) {
            return response()->noContent();
        }

        $chatId = (string) data_get($chat, 'id');
        $username = data_get($chat, 'username');

        $workspace = $connectRequest->workspace;

        // Mirror the controller's limit gate: block only brand-new accounts, never reconnects.
        $isNewAccount = ! $workspace->socialAccounts()
            ->where('platform', SocialPlatform::Telegram->value)
            ->where('platform_user_id', $chatId)
            ->exists();

        if ($isNewAccount && $this->workspaceAtAccountLimit($workspace)) {
            return response()->noContent();
        }

        $account = $workspace->socialAccounts()->updateOrCreate(
            [
                'platform' => SocialPlatform::Telegram->value,
                'platform_user_id' => $chatId,
            ],
            [
                'username' => $username,
                'display_name' => data_get($chat, 'title') ?? $username ?? "Telegram {$chatId}",
                'access_token' => '',
                'refresh_token' => '',
                'token_expires_at' => null,
                'scopes' => [],
                'status' => Status::Connected,
                'error_message' => null,
                'disconnected_at' => null,
                'meta' => [
                    'chat_id' => $chatId,
                    'username' => $username,
                    'type' => data_get($chat, 'type'),
                ],
            ],
        );

        $connectRequest->update(['social_account_id' => $account->id]);

        return response()->noContent();
    }

    private function workspaceAtAccountLimit(Workspace $workspace): bool
    {
        if (config('trypost.self_hosted')) {
            return false;
        }

        $limit = Feature::for($workspace->account)->value(SocialAccountLimit::class);

        return $workspace->socialAccounts()->count() >= $limit;
    }
}
