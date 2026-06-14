<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Enums\SocialAccount\Platform as SocialPlatform;
use App\Enums\SocialAccount\TelegramConnectStatus;
use App\Models\TelegramConnectRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class TelegramController extends SocialController
{
    protected SocialPlatform $platform = SocialPlatform::Telegram;

    /**
     * Start a connection: issue a one-time code the user posts in their channel
     * (`/connect <code>`) so the webhook can tie the channel to this workspace.
     */
    public function connect(Request $request): JsonResponse
    {
        $this->ensurePlatformEnabled();

        $workspace = $request->user()->currentWorkspace;
        abort_if($workspace === null, SymfonyResponse::HTTP_CONFLICT, 'No active workspace.');

        $this->authorize('manageAccounts', $workspace);
        $this->ensureSocialAccountLimit($workspace);

        $connectRequest = TelegramConnectRequest::create([
            'workspace_id' => $workspace->id,
            'user_id' => $request->user()->id,
            'code' => Str::lower(Str::random(12)),
            'expires_at' => now()->addMinutes(15),
        ]);

        return response()->json([
            'code' => $connectRequest->code,
            'bot_username' => config('trypost.platforms.telegram.bot_username'),
            'expires_at' => $connectRequest->expires_at->toIso8601String(),
        ]);
    }

    /**
     * Poll whether the channel has been linked yet.
     */
    public function status(Request $request): JsonResponse
    {
        $workspace = $request->user()->currentWorkspace;
        abort_if($workspace === null, SymfonyResponse::HTTP_CONFLICT, 'No active workspace.');

        $connectRequest = TelegramConnectRequest::query()
            ->where('workspace_id', $workspace->id)
            ->where('code', (string) $request->query('code'))
            ->first();

        return response()->json([
            'status' => TelegramConnectStatus::for($connectRequest)->value,
        ]);
    }
}
