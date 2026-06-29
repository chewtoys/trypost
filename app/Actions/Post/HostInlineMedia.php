<?php

declare(strict_types=1);

namespace App\Actions\Post;

use App\Enums\Media\Type as MediaType;
use App\Models\Workspace;
use App\Services\Post\MediaAttacher;
use Illuminate\Validation\ValidationException;

class HostInlineMedia
{
    /**
     * Resolve a post's inline media into hosted media — external URLs are
     * downloaded and stored; items already on our disk pass through. Rejects
     * (422) when any URL can't be fetched, so a post is never persisted with
     * broken media.
     *
     * @param  array<MediaType>  $allowedTypes
     * @param  array<int, array<string, mixed>>  $media
     * @return array<int, array<string, mixed>>
     *
     * @throws ValidationException
     */
    public static function execute(Workspace $workspace, array $allowedTypes, array $media): array
    {
        if ($media === []) {
            return $media;
        }

        $result = app(MediaAttacher::class)->resolveInlineMedia($workspace, $allowedTypes, $media);

        if ($result['failed'] !== []) {
            throw ValidationException::withMessages([
                'media' => ['Could not fetch media from URL: '.implode(', ', $result['failed'])],
            ]);
        }

        return $result['media'];
    }
}
