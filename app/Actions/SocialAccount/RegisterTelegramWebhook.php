<?php

declare(strict_types=1);

namespace App\Actions\SocialAccount;

use App\Services\Social\Telegram\TelegramApi;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

class RegisterTelegramWebhook
{
    /**
     * Register the bot webhook (URL + secret token) with the Telegram Bot API.
     *
     * @return string the webhook URL that was registered
     *
     * @throws InvalidArgumentException when the bot token or secret is missing
     * @throws RuntimeException when Telegram rejects the request
     */
    public static function execute(): string
    {
        $secret = (string) config('trypost.platforms.telegram.webhook_secret');

        if (TelegramApi::token() === '' || $secret === '') {
            throw new InvalidArgumentException('TELEGRAM_BOT_TOKEN and TELEGRAM_WEBHOOK_SECRET must both be set.');
        }

        $url = route('telegram.webhook');

        $response = Http::post(TelegramApi::endpoint('setWebhook'), [
            'url' => $url,
            'secret_token' => $secret,
            'allowed_updates' => ['message', 'channel_post', 'message_reaction_count'],
        ]);

        if (! $response->successful() || data_get($response->json(), 'ok') !== true) {
            throw new RuntimeException("Failed to set Telegram webhook: {$response->body()}");
        }

        return $url;
    }
}
