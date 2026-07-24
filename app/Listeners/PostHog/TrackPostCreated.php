<?php

declare(strict_types=1);

namespace App\Listeners\PostHog;

use App\Events\PostCreated;
use App\Jobs\PostHog\TrackPost;
use App\Services\PostHogService;

class TrackPostCreated
{
    public function handle(PostCreated $event): void
    {
        if (! PostHogService::isEnabled()) {
            return;
        }

        TrackPost::dispatch((string) $event->post->id);
    }
}
