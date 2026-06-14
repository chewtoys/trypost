<?php

declare(strict_types=1);

namespace App\Services\Social\Telegram;

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
        $chatId = data_get($account->meta, 'chat_id');

        if (TelegramApi::token() === '' || $chatId === null) {
            return [];
        }

        try {
            $count = data_get(
                Http::get(TelegramApi::endpoint('getChatMemberCount'), ['chat_id' => $chatId])->json(),
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
     * @return array<int, array{label: string, value: int, kind?: string}>
     */
    public function fetchPostMetrics(PostPlatform $postPlatform): array
    {
        $account = $postPlatform->socialAccount;
        $metrics = $account
            ? array_map(fn (array $metric): array => [...$metric, 'kind' => 'subscribers'], $this->getMetrics($account))
            : [];

        $reactions = data_get($postPlatform->meta, 'reactions', []);

        if (is_array($reactions)) {
            foreach ($reactions as $reaction) {
                $metrics[] = [
                    'label' => (string) data_get($reaction, 'type'),
                    'value' => (int) data_get($reaction, 'count'),
                    'kind' => 'reaction',
                ];
            }
        }

        return $metrics;
    }
}
