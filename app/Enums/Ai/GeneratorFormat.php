<?php

declare(strict_types=1);

namespace App\Enums\Ai;

enum GeneratorFormat: string
{
    case Single = 'single';
    case Carousel = 'carousel';

    public function isCarousel(): bool
    {
        return $this === self::Carousel;
    }
}
