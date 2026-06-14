<?php

declare(strict_types=1);

namespace App\Enums\SocialAccount;

use App\Models\TelegramConnectRequest;

enum TelegramConnectStatus: string
{
    case Unknown = 'unknown';
    case Pending = 'pending';
    case Connected = 'connected';
    case Expired = 'expired';

    /**
     * Derive the connection status the frontend polls for from a connect request.
     */
    public static function for(?TelegramConnectRequest $request): self
    {
        return match (true) {
            $request === null => self::Unknown,
            $request->social_account_id !== null => self::Connected,
            $request->isExpired() => self::Expired,
            default => self::Pending,
        };
    }
}
