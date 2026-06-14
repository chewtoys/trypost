<?php

declare(strict_types=1);

namespace App\Enums\SocialAccount;

use App\Models\SocialAccount;

enum TelegramConnectStatus: string
{
    case Unknown = 'unknown';
    case Pending = 'pending';
    case Connected = 'connected';

    /**
     * Connected once the channel has been linked to an account; otherwise still pending.
     */
    public static function for(?SocialAccount $account): self
    {
        return $account === null ? self::Pending : self::Connected;
    }
}
