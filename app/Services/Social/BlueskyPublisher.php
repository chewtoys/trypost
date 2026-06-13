<?php

declare(strict_types=1);

namespace App\Services\Social;

use App\Enums\SocialAccount\Platform;
use App\Exceptions\Social\BlueskyPublishException;
use App\Models\PostPlatform;
use App\Models\SocialAccount;
use App\Services\Media\MediaOptimizer;
use App\Services\Social\Concerns\HasSocialHttpClient;
use Exception;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class BlueskyPublisher
{
    use HasSocialHttpClient;

    public function publish(PostPlatform $postPlatform): array
    {
        $this->validateContentLength($postPlatform);

        $content = $postPlatform->post->content ? app(ContentSanitizer::class)->sanitize($postPlatform->post->content, $postPlatform->platform) : null;

        $account = $postPlatform->socialAccount;
        $service = $account->meta['service'] ?? config('trypost.platforms.bluesky.default_service');

        // Refresh token if needed
        if ($account->is_token_expired || $account->is_token_expiring_soon) {
            app(ConnectionVerifier::class)->refreshToken($account);
        }

        $medias = $postPlatform->post->mediaItems;
        $embed = null;

        // Upload images if present (max 4)
        if ($medias->count() > 0) {
            $images = [];
            foreach ($medias->take(4) as $media) {
                if ($media->isImage()) {
                    $blob = $this->uploadBlob($account, $service, $media->url, $media->mime_type);
                    if ($blob) {
                        $images[] = [
                            'alt' => '',
                            'image' => $blob,
                        ];
                    }
                }
            }

            if (count($images) > 0) {
                $embed = [
                    '$type' => 'app.bsky.embed.images',
                    'images' => $images,
                ];
            }
        }

        // Parse facets (links, mentions, hashtags) from text
        $text = $content ?? '';
        $facets = $this->parseFacets($text);

        // Create post record
        $record = [
            '$type' => 'app.bsky.feed.post',
            'text' => $text,
            'createdAt' => now()->toIso8601ZuluString(),
        ];

        if ($embed) {
            $record['embed'] = $embed;
        }

        if (! empty($facets)) {
            $record['facets'] = $facets;
        }

        $response = $this->socialHttp()->withToken($account->access_token)
            ->post("{$service}/xrpc/com.atproto.repo.createRecord", [
                'repo' => $account->platform_user_id,
                'collection' => 'app.bsky.feed.post',
                'record' => $record,
            ]);

        if ($response->failed()) {
            Log::error('Bluesky post failed', [
                'status' => $response->status(),
                'body' => $this->redactResponseBody($response->body()),
            ]);

            $this->handleApiError($response);
        }

        $data = $response->json();

        // Extract post ID from URI (at://did/app.bsky.feed.post/xxx)
        $uri = data_get($data, 'uri');
        $postId = basename($uri);

        return [
            'id' => $postId,
            'url' => $this->buildPostUrl($account->username, $postId),
        ];
    }

    private function uploadBlob(SocialAccount $account, string $service, string $url, string $mimeType): ?array
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'bsky_blob_');

        try {
            $downloadResponse = Http::withOptions(['sink' => $tempFile])->timeout(600)->get($url);

            if ($downloadResponse->failed()) {
                throw new Exception('Failed to download media: HTTP '.$downloadResponse->status());
            }

            $fileSize = filesize($tempFile);

            if ($fileSize === false || $fileSize === 0) {
                Log::error('Bluesky failed to download media', ['url' => $url]);

                return null;
            }

            // Optimize images for Bluesky's 1MB limit
            if (str_starts_with($mimeType, 'image/') && ! str_starts_with($mimeType, 'image/gif')) {
                $optimizer = app(MediaOptimizer::class);
                $optimizedPath = $optimizer->optimizeImage($tempFile, Platform::Bluesky);
                @unlink($tempFile);
                $tempFile = $optimizedPath;
                $mimeType = 'image/jpeg';
            }

            $stream = fopen($tempFile, 'r');

            $response = $this->socialHttp()->withToken($account->access_token)
                ->withHeaders(['Content-Type' => $mimeType])
                ->withBody($stream, $mimeType)
                ->post("{$service}/xrpc/com.atproto.repo.uploadBlob");

            if (is_resource($stream)) {
                fclose($stream);
            }

            if ($response->failed()) {
                Log::error('Bluesky blob upload failed', [
                    'status' => $response->status(),
                    'body' => $this->redactResponseBody($response->body()),
                ]);

                return null;
            }

            return data_get($response->json(), 'blob');
        } catch (Exception $e) {
            Log::error('Bluesky blob upload exception', [
                'error' => $e->getMessage(),
                'url' => $url,
            ]);

            return null;
        } finally {
            @unlink($tempFile);
        }
    }

    private function parseFacets(string $text): array
    {
        $facets = [];

        // Parse URLs
        preg_match_all(
            '/(https?:\/\/[^\s]+)/u',
            $text,
            $urlMatches,
            PREG_OFFSET_CAPTURE
        );

        foreach ($urlMatches[0] as $match) {
            $url = $this->trimTrailingUrlPunctuation($match[0]);
            $start = (int) $match[1];
            $end = $start + strlen($url);

            $facets[] = [
                'index' => [
                    'byteStart' => $start,
                    'byteEnd' => $end,
                ],
                'features' => [
                    [
                        '$type' => 'app.bsky.richtext.facet#link',
                        'uri' => $url,
                    ],
                ],
            ];
        }

        // Parse mentions (@handle.bsky.social)
        preg_match_all(
            '/@([a-zA-Z0-9]([a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?\.)+[a-zA-Z]([a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?/u',
            $text,
            $mentionMatches,
            PREG_OFFSET_CAPTURE
        );

        $didCache = [];
        foreach ($mentionMatches[0] as $match) {
            $mention = $match[0];
            $handle = substr($mention, 1); // Remove @

            // A mention facet needs the target's DID, not the handle; skip it if unresolvable.
            // Cache by key (not ??) so an unresolvable handle is resolved once, not per occurrence.
            if (! array_key_exists($handle, $didCache)) {
                $didCache[$handle] = $this->resolveHandleToDid($handle);
            }
            $did = $didCache[$handle];
            if ($did === null) {
                continue;
            }

            $start = (int) $match[1];
            $end = $start + strlen($mention);

            $facets[] = [
                'index' => [
                    'byteStart' => $start,
                    'byteEnd' => $end,
                ],
                'features' => [
                    [
                        '$type' => 'app.bsky.richtext.facet#mention',
                        'did' => $did,
                    ],
                ],
            ];
        }

        // Parse hashtags (#tag)
        preg_match_all(
            '/#[^\s\p{P}]+/u',
            $text,
            $hashtagMatches,
            PREG_OFFSET_CAPTURE
        );

        foreach ($hashtagMatches[0] as $match) {
            $hashtag = $match[0];
            $tag = substr($hashtag, 1); // Remove #
            $start = (int) $match[1];
            $end = $start + strlen($hashtag);

            $facets[] = [
                'index' => [
                    'byteStart' => $start,
                    'byteEnd' => $end,
                ],
                'features' => [
                    [
                        '$type' => 'app.bsky.richtext.facet#tag',
                        'tag' => $tag,
                    ],
                ],
            ];
        }

        return $facets;
    }

    /**
     * Resolve a Bluesky handle to its DID via com.atproto.identity.resolveHandle.
     *
     * resolveHandle is a public read served by the AppView (no auth). Returns
     * null on any failure so the caller can skip the mention facet and publish
     * the @handle as plain text instead of an invalid record.
     */
    private function resolveHandleToDid(string $handle): ?string
    {
        $appView = (string) config('trypost.platforms.bluesky.public_appview');

        try {
            $response = $this->socialHttp()->get(
                "{$appView}/xrpc/com.atproto.identity.resolveHandle",
                ['handle' => $handle],
            );

            $did = $response->successful() ? data_get($response->json(), 'did') : null;

            return is_string($did) && str_starts_with($did, 'did:') ? $did : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Trailing sentence punctuation and an unmatched closing paren are almost
     * never part of a URL (e.g. "see https://x.com)."). Mirrors the official
     * atproto link tokenizer so the link facet doesn't over-extend past the URL.
     */
    private function trimTrailingUrlPunctuation(string $url): string
    {
        if (preg_match('/[.,;:!?]$/', $url)) {
            $url = substr($url, 0, -1);
        }

        if (str_ends_with($url, ')') && ! str_contains($url, '(')) {
            $url = substr($url, 0, -1);
        }

        return $url;
    }

    private function buildPostUrl(string $handle, string $postId): string
    {
        $webApp = (string) config('trypost.platforms.bluesky.web_app');

        return "{$webApp}/profile/{$handle}/post/{$postId}";
    }

    private function handleApiError(Response $response): never
    {
        throw BlueskyPublishException::fromApiResponse($response);
    }
}
