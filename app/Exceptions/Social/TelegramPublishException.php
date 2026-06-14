<?php

declare(strict_types=1);

namespace App\Exceptions\Social;

use Illuminate\Http\Client\Response;

class TelegramPublishException extends SocialPublishException
{
    public static function fromApiResponse(mixed $response): static
    {
        /** @var Response $response */
        $status = $response->status();
        $rawResponse = $response->body();
        $description = (string) data_get($response->json(), 'description', 'An unknown Telegram error occurred.');

        // 403: the bot was removed or isn't an admin of the channel anymore.
        if ($status === 403) {
            return new static(
                userMessage: 'The bot is not an admin of this channel. Re-add it as an administrator and try again.',
                category: ErrorCategory::Permission,
                platformErrorCode: (string) $status,
                rawResponse: $rawResponse,
            );
        }

        // 401: the configured bot token is invalid (operator-level misconfiguration).
        if ($status === 401) {
            return new static(
                userMessage: 'Telegram rejected the bot token. Check the TELEGRAM_BOT_TOKEN configuration.',
                category: ErrorCategory::Permission,
                platformErrorCode: (string) $status,
                rawResponse: $rawResponse,
            );
        }

        if ($status === 429) {
            return new static(
                userMessage: 'Telegram rate limit reached. Please try again shortly.',
                category: ErrorCategory::RateLimit,
                platformErrorCode: (string) $status,
                rawResponse: $rawResponse,
            );
        }

        if ($status >= 500) {
            return new static(
                userMessage: 'Telegram is temporarily unavailable. Please try again later.',
                category: ErrorCategory::ServerError,
                platformErrorCode: (string) $status,
                rawResponse: $rawResponse,
            );
        }

        return new static(
            userMessage: $description,
            category: ErrorCategory::Unknown,
            platformErrorCode: (string) $status,
            rawResponse: $rawResponse,
        );
    }

    public function platform(): string
    {
        return 'telegram';
    }
}
