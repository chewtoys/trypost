<?php

declare(strict_types=1);

namespace App\Services\Social;

use App\Enums\SocialAccount\Platform;
use App\Exceptions\PlatformUnavailableException;
use App\Exceptions\TokenExpiredException;
use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;

/**
 * Normalizes the failure modes of an OAuth token-refresh request:
 *
 * - ConnectionException (timeout / DNS / refused) → PlatformUnavailableException
 * - HTTP 5xx                                      → PlatformUnavailableException
 * - HTTP 4xx                                      → TokenExpiredException
 *
 * Callers configure the actual HTTP call through the closure passed to
 * `send()`, so platform-specific quirks (form vs JSON body, auth headers,
 * basic auth, etc.) stay where they belong — in the per-platform refresh
 * method — while the failure semantics are uniform across providers.
 */
class TokenRefreshClient
{
    public function __construct(public readonly Platform $platform) {}

    public static function for(Platform $platform): self
    {
        return new self($platform);
    }

    /**
     * @param  Closure():Response  $request
     *
     * @throws PlatformUnavailableException
     * @throws TokenExpiredException
     */
    public function send(Closure $request): Response
    {
        $name = $this->platform->label();

        try {
            $response = $request();
        } catch (ConnectionException $e) {
            throw new PlatformUnavailableException("{$name} API unreachable: {$e->getMessage()}");
        }

        if ($response->serverError() || $response->status() === 429) {
            throw new PlatformUnavailableException(
                "{$name} API returned {$response->status()} during token refresh",
                $response->status(),
            );
        }

        if ($response->failed()) {
            Log::error("TokenRefreshClient: {$name} token refresh failed", [
                'body' => TokenRedactor::redact($response->body()),
            ]);

            $body = $response->json();
            $message = data_get($body, 'error_description')
                ?? data_get($body, 'error.message')
                ?? "Failed to refresh {$name} token";

            throw new TokenExpiredException($message, platformErrorCode: (string) $response->status());
        }

        return $response;
    }
}
