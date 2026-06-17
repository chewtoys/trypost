<?php

declare(strict_types=1);

namespace App\Services\Social\Reddit;

use App\Models\SocialAccount;
use App\Services\Social\Concerns\HasSocialHttpClient;
use Illuminate\Http\Client\PendingRequest;
use Throwable;

class RedditClient
{
    use HasSocialHttpClient;

    /**
     * @return list<array{name: string, title: string, subscribers: int, over_18: bool}>
     */
    public function searchSubreddits(SocialAccount $account, string $query): array
    {
        $response = $this->reddit($account)->get($this->url('/subreddits/search'), [
            'q' => $query,
            'show' => 'public',
            'sort' => 'activity',
            'show_users' => 'false',
            'limit' => 10,
        ]);

        return collect(data_get($response->json(), 'data.children', []))
            ->map(fn ($child) => [
                'name' => (string) data_get($child, 'data.display_name'),
                'title' => (string) data_get($child, 'data.title'),
                'subscribers' => (int) data_get($child, 'data.subscribers', 0),
                'over_18' => (bool) data_get($child, 'data.over18', false),
            ])
            ->filter(fn ($sub) => $sub['name'] !== '')
            ->values()
            ->all();
    }

    /**
     * @return array{allowed_types: list<string>, flair_required: bool, flairs: list<array{id: string, text: string}>}
     */
    public function restrictions(SocialAccount $account, string $subreddit): array
    {
        $about = $this->reddit($account)->get($this->url("/r/{$subreddit}/about"))->json();
        $submissionType = (string) data_get($about, 'data.submission_type', 'any');
        $allowImages = (bool) data_get($about, 'data.allow_images', true);

        $allowedTypes = match ($submissionType) {
            'self' => ['self'],
            'link' => ['link'],
            default => ['self', 'link'],
        };

        if ($allowImages) {
            $allowedTypes[] = 'image';
        }

        $flairRequired = (bool) data_get(
            $this->reddit($account)->get($this->url("/api/v1/{$subreddit}/post_requirements"))->json(),
            'is_flair_required',
            false
        );

        return [
            'allowed_types' => $allowedTypes,
            'flair_required' => $flairRequired,
            'flairs' => $this->flairs($account, $subreddit),
        ];
    }

    /**
     * @return list<array{id: string, text: string}>
     */
    public function flairs(SocialAccount $account, string $subreddit): array
    {
        try {
            return collect($this->reddit($account)->get($this->url("/r/{$subreddit}/api/link_flair_v2"))->json())
                ->map(fn ($flair) => [
                    'id' => (string) data_get($flair, 'id'),
                    'text' => (string) data_get($flair, 'text'),
                ])
                ->filter(fn ($flair) => $flair['id'] !== '')
                ->values()
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @param  list<string>  $fullnames  Reddit fullnames, e.g. ['t3_abc123'].
     * @return array<string, array{score: int, num_comments: int, url: string}>
     */
    public function info(SocialAccount $account, array $fullnames): array
    {
        if ($fullnames === []) {
            return [];
        }

        $response = $this->reddit($account)->get($this->url('/api/info'), ['id' => implode(',', $fullnames)]);

        return collect(data_get($response->json(), 'data.children', []))
            ->mapWithKeys(fn ($child) => [
                (string) data_get($child, 'data.name') => [
                    'score' => (int) data_get($child, 'data.score', 0),
                    'num_comments' => (int) data_get($child, 'data.num_comments', 0),
                    'url' => (string) data_get($child, 'data.url', ''),
                ],
            ])
            ->all();
    }

    /**
     * @return array{link_karma: int, comment_karma: int, total_karma: int}
     */
    public function me(SocialAccount $account): array
    {
        $data = $this->reddit($account)->get($this->url('/api/v1/me'))->json();

        return [
            'link_karma' => (int) data_get($data, 'link_karma', 0),
            'comment_karma' => (int) data_get($data, 'comment_karma', 0),
            'total_karma' => (int) data_get($data, 'total_karma', 0),
        ];
    }

    private function url(string $path): string
    {
        return (string) config('trypost.platforms.reddit.api').$path;
    }

    private function reddit(SocialAccount $account): PendingRequest
    {
        return $this->socialHttp()
            ->withToken((string) $account->access_token)
            ->withHeaders(['User-Agent' => (string) config('trypost.platforms.reddit.user_agent')]);
    }
}
