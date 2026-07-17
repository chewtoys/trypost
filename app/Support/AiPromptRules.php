<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Single source of truth for the user-supplied AI generation prompt length,
 * shared by the create wizard (StartPostCreationRequest) and the editor's
 * content generation (GeneratePostContentRequest). The frontend counter in
 * resources/js/components/posts/create/AiPostWizard.vue mirrors these bounds.
 */
class AiPromptRules
{
    /**
     * Minimum prompt length (characters) for the create wizard.
     */
    public const PROMPT_MIN_LENGTH = 3;

    /**
     * Maximum prompt length (characters); mirrored by the frontend counter.
     */
    public const PROMPT_MAX_LENGTH = 2000;

    /**
     * Validation rules for the create wizard's generation prompt.
     *
     * @return array<int, string>
     */
    public static function promptRule(): array
    {
        return ['required', 'string', 'min:'.self::PROMPT_MIN_LENGTH, 'max:'.self::PROMPT_MAX_LENGTH];
    }
}
