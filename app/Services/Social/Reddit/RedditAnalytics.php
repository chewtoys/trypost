<?php

declare(strict_types=1);

namespace App\Services\Social\Reddit;

use App\Models\PostPlatform;
use App\Models\SocialAccount;
use Throwable;

/**
 * Reddit engagement metrics. Account-level exposes the connected user's karma;
 * per post, the summed upvote score and comment count across every subreddit the
 * post was submitted to (PostPlatform.platform_post_id is a comma-joined list of
 * Reddit fullnames).
 */
class RedditAnalytics
{
    public function __construct(private RedditClient $client) {}

    /**
     * @return array<int, array{label: string, value: int}>
     */
    public function getMetrics(SocialAccount $account): array
    {
        try {
            $karma = $this->client->me($account);
        } catch (Throwable) {
            return [];
        }

        return [
            ['label' => __('analytics.metrics.karma'), 'value' => $karma['total_karma']],
        ];
    }

    /**
     * @return array<int, array{label: string, value: int, kind?: string}>
     */
    public function fetchPostMetrics(PostPlatform $postPlatform): array
    {
        $account = $postPlatform->socialAccount;

        $fullnames = collect(explode(',', (string) $postPlatform->platform_post_id))
            ->map(fn ($id) => trim($id))
            ->filter()
            ->values()
            ->all();

        if (! $account || $fullnames === []) {
            return [];
        }

        try {
            $info = $this->client->info($account, $fullnames);
        } catch (Throwable) {
            return [];
        }

        if ($info === []) {
            return [];
        }

        return [
            ['label' => __('analytics.metrics.upvotes'), 'value' => (int) collect($info)->sum('score'), 'kind' => 'reaction'],
            ['label' => __('analytics.metrics.comments'), 'value' => (int) collect($info)->sum('num_comments'), 'kind' => 'comments'],
        ];
    }
}
