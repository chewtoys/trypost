<?php

declare(strict_types=1);

namespace App\Enums\Plan;

enum Slug: string
{
    case Workspace = 'workspace';

    public function label(): string
    {
        return match ($this) {
            self::Workspace => 'Workspace',
        };
    }
}
