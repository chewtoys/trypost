<?php

declare(strict_types=1);

namespace App\Services\Social\Reddit;

use App\DataTransferObjects\MediaItem;
use App\Exceptions\Social\ErrorCategory;
use App\Exceptions\Social\RedditPublishException;
use App\Models\PostPlatform;
use App\Models\SocialAccount;
use App\Services\Social\Concerns\HasSocialHttpClient;
use App\Services\Social\ContentSanitizer;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
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

        $imageMedia = $postPlatform->post->mediaItems
            ->filter(fn (MediaItem $item) => $item->isImage())
            ->take($postPlatform->platform->maxImages())
            ->values();

        /** @var list<array{subreddit: string, id: string, url: string}> $results */
        $results = [];

        foreach ($subreddits as $index => $sub) {
            if ($index > 0) {
                usleep(self::SUBMIT_DELAY_MICROSECONDS);
            }

            try {
                $results[] = $this->submitOne($account, $sub, $text, $imageMedia);
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
     * @param  Collection<int, MediaItem>  $imageMedia
     * @return array{subreddit: string, id: string, url: string}
     */
    private function submitOne(SocialAccount $account, array $sub, string $text, Collection $imageMedia): array
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

        if ($type === 'image' && $imageMedia->count() >= 2) {
            return $this->submitGallery($account, $sub, $name, $title, $imageMedia);
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
            $payload['url'] = $this->uploadSingleImage($account, $imageMedia);
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
     * @param  Collection<int, MediaItem>  $imageMedia
     * @return array{subreddit: string, id: string, url: string}
     */
    private function submitGallery(SocialAccount $account, array $sub, string $name, string $title, Collection $imageMedia): array
    {
        $imageMedia->each(function (MediaItem $item) {
            if ($item->isVideo()) {
                throw new RedditPublishException(
                    userMessage: 'Reddit video posts are not supported yet.',
                    category: ErrorCategory::MediaFormat,
                );
            }
        });

        $items = $imageMedia->map(function (MediaItem $item) use ($account) {
            ['asset_id' => $assetId] = $this->leaseAndUpload($account, $item);

            return ['media_id' => $assetId, 'caption' => '', 'outbound_url' => ''];
        })->values()->all();

        $payload = array_filter([
            'api_type' => 'json',
            'sr' => $name,
            'title' => $title,
            'nsfw' => (bool) data_get($sub, 'nsfw', false) ? 'true' : null,
            'spoiler' => (bool) data_get($sub, 'spoiler', false) ? 'true' : null,
            'flair_id' => data_get($sub, 'flair_id') ?: null,
            'flair_text' => data_get($sub, 'flair_text') ?: null,
            'items' => json_encode($items),
        ], fn ($v) => $v !== null);

        $response = $this->reddit($account)->asForm()->post($this->url('/api/submit_gallery_post.json'), $payload);

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
     * @param  Collection<int, MediaItem>  $imageMedia
     */
    private function uploadSingleImage(SocialAccount $account, Collection $imageMedia): string
    {
        $item = $imageMedia->first() ?? throw new RedditPublishException(
            userMessage: 'No image attached for this Reddit image post.',
            category: ErrorCategory::MediaFormat,
        );

        if ($item->isVideo()) {
            throw new RedditPublishException(
                userMessage: 'Reddit video posts are not supported yet.',
                category: ErrorCategory::MediaFormat,
            );
        }

        ['url' => $hostedUrl] = $this->leaseAndUpload($account, $item);

        return $hostedUrl;
    }

    /**
     * Leases a Reddit media asset, uploads the file to S3, and returns both
     * the asset_id (needed for gallery submissions) and the hosted URL
     * (needed for single-image submissions).
     *
     * @return array{asset_id: string, url: string}
     */
    private function leaseAndUpload(SocialAccount $account, MediaItem $item): array
    {
        $mime = (string) ($item->mime_type ?: 'image/jpeg');
        $filename = $item->original_filename ?: (basename((string) $item->path) ?: 'image');

        $lease = $this->reddit($account)->asForm()->post($this->url('/api/media/asset'), [
            'filepath' => $filename,
            'mimetype' => $mime,
        ]);
        $this->assertOk($lease);

        $assetId = (string) data_get($lease->json(), 'asset.asset_id');
        $action = 'https:'.preg_replace('#^https?:#', '', (string) data_get($lease->json(), 'args.action'));
        $fields = collect((array) data_get($lease->json(), 'args.fields'))
            ->mapWithKeys(fn ($f) => [(string) data_get($f, 'name') => (string) data_get($f, 'value')])
            ->all();

        $bytes = Http::timeout(120)->get($item->url)->body();

        $upload = Http::asMultipart();
        foreach ($fields as $fieldName => $value) {
            $upload = $upload->attach($fieldName, $value);
        }
        $upload = $upload->attach('file', $bytes, $filename);
        $s3 = $upload->post($action);

        if ($s3->failed()) {
            throw new RedditPublishException(
                userMessage: 'Failed to upload the image to Reddit.',
                category: ErrorCategory::MediaFormat,
            );
        }

        if (preg_match('/<Location>(.*?)<\/Location>/', $s3->body(), $m)) {
            return ['asset_id' => $assetId, 'url' => html_entity_decode($m[1])];
        }

        return ['asset_id' => $assetId, 'url' => rtrim($action, '/').'/'.($fields['key'] ?? $filename)];
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
