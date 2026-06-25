<?php

declare(strict_types=1);

namespace App\Enums\SocialAccount;

/**
 * Which LinkedIn identity a member posts as after the unified connect flow:
 * their personal profile (creates a `linkedin` account) or a company page
 * they administer (creates a `linkedin-page` account).
 */
enum LinkedInIdentityType: string
{
    case Person = 'person';
    case Organization = 'organization';
}
