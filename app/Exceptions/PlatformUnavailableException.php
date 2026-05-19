<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

/**
 * Raised when a social platform's API is unreachable or returning a server
 * error during a token verify/refresh. Distinct from TokenExpiredException
 * because the account's token is not provably invalid — the platform is
 * just down. Callers should retry later instead of disconnecting the user.
 */
class PlatformUnavailableException extends Exception
{
    public function __construct(
        string $message = 'Platform API is unavailable',
        public ?int $httpStatus = null,
    ) {
        parent::__construct($message);
    }
}
