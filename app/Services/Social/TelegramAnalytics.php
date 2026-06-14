<?php

declare(strict_types=1);

namespace App\Services\Social;

use App\Models\PostPlatform;
use App\Models\SocialAccount;
use Illuminate\Support\Facades\Http;
use Throwable;

class TelegramAnalytics
{
    /**
     * Account-level metrics. The Bot API only exposes the subscriber count.
     *
     * @return array<int, array{label: string, value: int}>
     */
    public function getMetrics(SocialAccount $account): array
    {
        $token = (string) config('trypost.platforms.telegram.bot_token');
        $api = rtrim((string) config('trypost.platforms.telegram.api'), '/');
        $chatId = data_get($account->meta, 'chat_id');

        if ($token === '' || $chatId === null) {
            return [];
        }

        try {
            $count = data_get(
                Http::get("{$api}/bot{$token}/getChatMemberCount", ['chat_id' => $chatId])->json(),
                'result',
            );
        } catch (Throwable) {
            return [];
        }

        if (! is_int($count)) {
            return [];
        }

        return [
            ['label' => __('analytics.metrics.subscribers'), 'value' => $count],
        ];
    }

    /**
     * Post-level metrics. Reaction counts are pushed by the webhook and stored
     * on the post platform's meta (the Bot API offers no post views to bots).
     *
     * @return array<int, array{label: string, value: int}>
     */
    public function fetchPostMetrics(PostPlatform $postPlatform): array
    {
        $reactions = data_get($postPlatform->meta, 'reactions', []);

        if (! is_array($reactions)) {
            return [];
        }

        return array_values(array_map(fn (array $reaction): array => [
            'label' => (string) data_get($reaction, 'type'),
            'value' => (int) data_get($reaction, 'count'),
        ], $reactions));
    }
}
