<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Validates a URL that may contain `{{ ... }}` workflow-variable expressions.
 * The expressions are resolved at runtime, so they're replaced with a placeholder
 * before checking the result is a valid URL — letting users template the host or
 * query (e.g. `https://host/feed.xml?channel_id={{ variables.CHANNEL_ID }}`).
 */
class ResolvableUrl implements ValidationRule
{
    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('validation.url')->translate();

            return;
        }

        $candidate = preg_replace('/\{\{\s*[\w.]+\s*\}\}/', 'placeholder', $value);
        $scheme = strtolower((string) parse_url($candidate, PHP_URL_SCHEME));

        // Require a real http(s) URL once expressions are substituted, so the rule
        // is no weaker than the plain `url` rule it replaces (rejects file://,
        // javascript://, etc.) while still allowing templated hosts/queries.
        if (filter_var($candidate, FILTER_VALIDATE_URL) === false || ! in_array($scheme, ['http', 'https'], true)) {
            $fail('validation.url')->translate();
        }
    }
}
