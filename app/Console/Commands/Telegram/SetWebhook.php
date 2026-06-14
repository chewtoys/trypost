<?php

declare(strict_types=1);

namespace App\Console\Commands\Telegram;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

#[Signature('telegram:set-webhook')]
#[Description('Register the Telegram bot webhook with the configured URL and secret token')]
class SetWebhook extends Command
{
    public function handle(): int
    {
        $token = (string) config('trypost.platforms.telegram.bot_token');
        $api = rtrim((string) config('trypost.platforms.telegram.api'), '/');
        $secret = (string) config('trypost.platforms.telegram.webhook_secret');

        if ($token === '' || $secret === '') {
            $this->error('TELEGRAM_BOT_TOKEN and TELEGRAM_WEBHOOK_SECRET must both be set.');

            return self::FAILURE;
        }

        $url = route('telegram.webhook');

        $response = Http::post("{$api}/bot{$token}/setWebhook", [
            'url' => $url,
            'secret_token' => $secret,
            'allowed_updates' => ['message', 'channel_post'],
        ]);

        if (! $response->successful() || data_get($response->json(), 'ok') !== true) {
            $this->error('Failed to set webhook: '.$response->body());

            return self::FAILURE;
        }

        $this->info("Telegram webhook registered at {$url}");

        return self::SUCCESS;
    }
}
