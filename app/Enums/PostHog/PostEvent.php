<?php

declare(strict_types=1);

namespace App\Enums\PostHog;

enum PostEvent: string
{
    case Created = 'post.created';
}
