<?php

declare(strict_types=1);

namespace App\Services\Social;

/**
 * AT Protocol / Bluesky lexicon identifiers (NSIDs) used across the Bluesky
 * services. Centralized so a typo surfaces as an undefined-constant error
 * instead of a silent runtime "Invalid request" from the API.
 */
final class BlueskyLexicon
{
    public const RESOLVE_HANDLE = 'com.atproto.identity.resolveHandle';

    public const CREATE_RECORD = 'com.atproto.repo.createRecord';

    public const UPLOAD_BLOB = 'com.atproto.repo.uploadBlob';

    public const CREATE_SESSION = 'com.atproto.server.createSession';

    public const REFRESH_SESSION = 'com.atproto.server.refreshSession';

    public const FEED_POST = 'app.bsky.feed.post';

    public const GET_POSTS = 'app.bsky.feed.getPosts';

    public const GET_PROFILE = 'app.bsky.actor.getProfile';

    public const EMBED_IMAGES = 'app.bsky.embed.images';

    public const FACET_LINK = 'app.bsky.richtext.facet#link';

    public const FACET_MENTION = 'app.bsky.richtext.facet#mention';

    public const FACET_TAG = 'app.bsky.richtext.facet#tag';
}
