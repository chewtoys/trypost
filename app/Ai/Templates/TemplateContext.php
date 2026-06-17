<?php

declare(strict_types=1);

namespace App\Ai\Templates;

use App\Models\SocialAccount;
use App\Models\Workspace;

/**
 * Everything a template needs to assemble a post from the LLM output.
 */
class TemplateContext
{
    public function __construct(
        public Workspace $workspace,
        public ?SocialAccount $socialAccount,
        public string $format,
        public int $imageCount,
        public bool $isCarousel = false,
        public bool $applyBrandVisuals = true,
    ) {}
}
