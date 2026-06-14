<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Enums\SocialAccount\Platform as SocialPlatform;
use App\Enums\SocialAccount\TelegramConnectStatus;
use App\Services\Social\TelegramConnectCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class TelegramController extends SocialController
{
    protected SocialPlatform $platform = SocialPlatform::Telegram;

    private const SESSION_KEY = 'telegram_connect_code';

    /**
     * Start a connection: issue a signed one-off code the user posts in their
     * channel (`/connect <code>`). The code carries the workspace, so the webhook
     * can link the channel without any persisted state.
     */
    public function connect(Request $request): JsonResponse
    {
        $this->ensurePlatformEnabled();

        $workspace = $request->user()->currentWorkspace;
        abort_if($workspace === null, SymfonyResponse::HTTP_CONFLICT, 'No active workspace.');

        $this->authorize('manageAccounts', $workspace);
        $this->ensureSocialAccountLimit($workspace);

        $expiresAt = now()->addMinutes(15);
        $code = TelegramConnectCode::issue($workspace->id, $expiresAt);

        $request->session()->put(self::SESSION_KEY, $code);

        return response()->json([
            'code' => $code,
            'bot_username' => config('trypost.platforms.telegram.bot_username'),
            'expires_at' => $expiresAt->toIso8601String(),
        ]);
    }

    /**
     * Poll whether the channel issued in this session has been linked yet.
     */
    public function status(Request $request): JsonResponse
    {
        $workspace = $request->user()->currentWorkspace;
        abort_if($workspace === null, SymfonyResponse::HTTP_CONFLICT, 'No active workspace.');

        $payload = TelegramConnectCode::decode($request->session()->get(self::SESSION_KEY));

        if ($payload === null) {
            return response()->json(['status' => TelegramConnectStatus::Unknown->value]);
        }

        $account = $workspace->socialAccounts()
            ->where('platform', SocialPlatform::Telegram->value)
            ->where('meta->connect_nonce', data_get($payload, 'nonce'))
            ->first();

        return response()->json([
            'status' => TelegramConnectStatus::for($account)->value,
        ]);
    }
}
