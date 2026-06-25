<?php

declare(strict_types=1);

namespace App\Services\Social;

use App\Enums\SocialAccount\Platform;

/**
 * Publishes posts to a member's personal LinkedIn profile.
 */
class LinkedInPublisher extends AbstractLinkedInPublisher
{
    protected function platform(): Platform
    {
        return Platform::LinkedIn;
    }

    protected function authorUrn(): string
    {
        return "urn:li:person:{$this->account->platform_user_id}";
    }
}
