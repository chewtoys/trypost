<?php

declare(strict_types=1);

namespace App\Services\Social\Reddit;

use App\Exceptions\Social\ErrorCategory;
use App\Exceptions\Social\RedditPublishException;
use App\Models\PostPlatform;
use App\Models\SocialAccount;
use App\Services\Social\Concerns\HasSocialHttpClient;
use App\Services\Social\ContentSanitizer;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Throwable;

/**
 * Publishes to Reddit. A single Reddit PostPlatform may target many subreddits
 * (meta.subreddits[]); each is its own /api/submit call (Reddit ~1 req/s, so we
 * pace them). The final permalink is resolved by polling /api/info — no
 * WebSocket. Partial failures keep the succeeded submissions and surface which
 * sub failed.
 */
class RedditPublisher
{
    use HasSocialHttpClient;

    private const SUBMIT_DELAY_MICROSECONDS = 1_100_000;

    public function __construct(private readonly RedditClient $client) {}

    /**
     * @return array{id: string, url: string}
     */
    public function publish(PostPlatform $postPlatform): array
    {
        $account = $postPlatform->socialAccount;

        $subreddits = collect((array) data_get($postPlatform->meta, 'subreddits', []))
            ->filter(fn ($s) => filled(data_get($s, 'name')))
            ->values();

        if ($subreddits->isEmpty()) {
            throw new RedditPublishException(
                userMessage: 'No subreddit selected for this Reddit post.',
                category: ErrorCategory::Unknown,
            );
        }

        $text = $postPlatform->post->content
            ? app(ContentSanitizer::class)->sanitize($postPlatform->post->content, $postPlatform->platform)
            : '';

        /** @var list<array{subreddit: string, id: string, url: string}> $results */
        $results = [];

        foreach ($subreddits as $index => $sub) {
            if ($index > 0) {
                usleep(self::SUBMIT_DELAY_MICROSECONDS);
            }

            try {
                $results[] = $this->submitOne($account, $sub, $text);
            } catch (Throwable $e) {
                $this->persistResults($postPlatform, $results);

                throw new RedditPublishException(
                    userMessage: 'Published to '.count($results).' subreddit(s); failed on r/'.data_get($sub, 'name').': '.$e->getMessage(),
                    category: ErrorCategory::Unknown,
                    previous: $e,
                );
            }
        }

        $this->persistResults($postPlatform, $results);

        return [
            'id' => implode(',', array_column($results, 'id')),
            'url' => implode(',', array_filter(array_column($results, 'url'))),
        ];
    }

    /**
     * @param  array<string, mixed>  $sub
     * @return array{subreddit: string, id: string, url: string}
     */
    private function submitOne(SocialAccount $account, array $sub, string $text): array
    {
        $name = (string) data_get($sub, 'name');
        $type = (string) data_get($sub, 'type', 'self');
        $title = trim((string) data_get($sub, 'title'));

        if ($title === '') {
            throw new RedditPublishException(
                userMessage: "A title is required to post to r/{$name}.",
                category: ErrorCategory::Unknown,
            );
        }

        $payload = array_filter([
            'api_type' => 'json',
            'sr' => $name,
            'title' => $title,
            'kind' => $this->kind($type),
            'nsfw' => (bool) data_get($sub, 'nsfw', false) ? 'true' : null,
            'spoiler' => (bool) data_get($sub, 'spoiler', false) ? 'true' : null,
            'flair_id' => data_get($sub, 'flair_id') ?: null,
            'flair_text' => data_get($sub, 'flair_text') ?: null,
        ], fn ($v) => $v !== null);

        if ($type === 'self') {
            $payload['text'] = $text;
        } elseif ($type === 'link') {
            $payload['url'] = (string) data_get($sub, 'url');
        } else {
            $payload['url'] = $this->uploadMedia($account, $sub);
        }

        $response = $this->reddit($account)->asForm()->post($this->url('/api/submit'), $payload);

        $this->assertOk($response);

        $id = (string) data_get($response->json(), 'json.data.id');
        $fullname = (string) data_get($response->json(), 'json.data.name');
        $fullname = $fullname !== '' ? $fullname : ($id !== '' ? "t3_{$id}" : '');

        return [
            'subreddit' => $name,
            'id' => $fullname,
            'url' => $this->resolveUrl($account, $fullname),
        ];
    }

    /**
     * @param  array<string, mixed>  $sub
     */
    private function uploadMedia(SocialAccount $account, array $sub): string
    {
        throw new RedditPublishException(
            userMessage: 'Reddit media upload is not available yet.',
            category: ErrorCategory::MediaFormat,
        );
    }

    private function resolveUrl(SocialAccount $account, string $fullname): string
    {
        if ($fullname === '') {
            return '';
        }

        return (string) data_get($this->client->info($account, [$fullname]), "{$fullname}.url", '');
    }

    private function kind(string $type): string
    {
        return match ($type) {
            'link' => 'link',
            'image' => 'image',
            'video' => 'video',
            'gallery' => 'gallery',
            default => 'self',
        };
    }

    private function assertOk(Response $response): void
    {
        if ($response->failed()) {
            throw RedditPublishException::fromApiResponse($response);
        }

        $errors = (array) data_get($response->json(), 'json.errors', []);

        if ($errors !== []) {
            throw new RedditPublishException(
                userMessage: (string) (data_get($errors, '0.1') ?: 'Reddit rejected the submission.'),
                category: ErrorCategory::Unknown,
            );
        }
    }

    /**
     * @param  list<array{subreddit: string, id: string, url: string}>  $results
     */
    private function persistResults(PostPlatform $postPlatform, array $results): void
    {
        $postPlatform->update(['meta' => array_merge((array) $postPlatform->meta, ['results' => $results])]);
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
