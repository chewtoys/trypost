<?php

declare(strict_types=1);

namespace App\Exceptions\Social;

use Illuminate\Http\Client\Response;
use RuntimeException;
use Throwable;

class RedditPublishException extends SocialPublishException
{
    public function __construct(
        string $userMessage,
        ErrorCategory $category,
        ?string $platformErrorCode = null,
        ?string $rawResponse = null,
        ?Throwable $previous = null,
    ) {
        $this->userMessage = $userMessage;
        $this->category = $category;
        $this->platformErrorCode = $platformErrorCode;
        $this->rawResponse = $rawResponse;

        RuntimeException::__construct($userMessage, 0, $previous);
    }

    public static function fromApiResponse(mixed $response): static
    {
        /** @var Response $response */
        $status = $response->status();
        $rawResponse = $response->body();
        $json = $response->json() ?? [];

        if ($status === 401 || $status === 403) {
            return new static(
                userMessage: 'Reddit rejected the request. Check that the account is connected and has permission to post.',
                category: ErrorCategory::Permission,
                platformErrorCode: (string) $status,
                rawResponse: $rawResponse,
            );
        }

        if ($status === 429) {
            return new static(
                userMessage: 'Reddit rate limit reached. Please try again shortly.',
                category: ErrorCategory::RateLimit,
                platformErrorCode: (string) $status,
                rawResponse: $rawResponse,
            );
        }

        if ($status >= 500) {
            return new static(
                userMessage: 'Reddit is temporarily unavailable. Please try again later.',
                category: ErrorCategory::ServerError,
                platformErrorCode: (string) $status,
                rawResponse: $rawResponse,
            );
        }

        $message = self::extractApiMessage($json, $status);

        return new static(
            userMessage: $message,
            category: ErrorCategory::Unknown,
            platformErrorCode: (string) $status,
            rawResponse: $rawResponse,
        );
    }

    /**
     * @param  array<string, mixed>  $json
     */
    private static function extractApiMessage(array $json, int $status): string
    {
        foreach ([
            data_get($json, 'json.errors.0.1'),
            data_get($json, 'errors.0.1'),
            data_get($json, 'explanation'),
            data_get($json, 'reason'),
            data_get($json, 'message'),
        ] as $candidate) {
            if (is_string($candidate) && $candidate !== '') {
                return $candidate;
            }
        }

        return "An unknown Reddit error occurred (HTTP {$status}).";
    }

    public function platform(): string
    {
        return 'reddit';
    }
}
