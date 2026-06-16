<?php

declare(strict_types=1);

namespace App\Http\Resources\App\Automation;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Flattens the first parsed feed item into a catalog of `{{ fetched.* }}` paths
 * with sample values, so the editor can offer them as expression completions.
 *
 * @property array<string, mixed> $resource The first normalized feed item.
 */
class FeedInspectionResource extends JsonResource
{
    private const SAMPLE_MAX_LENGTH = 120;

    /**
     * @return array{fields: list<array{path: string, sample: string}>}
     */
    public function toArray(Request $request): array
    {
        return [
            'fields' => $this->flatten((array) $this->resource),
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return list<array{path: string, sample: string}>
     */
    private function flatten(array $item, string $prefix = 'fetched'): array
    {
        $fields = [];

        foreach ($item as $key => $value) {
            $path = "{$prefix}.{$key}";

            if (is_array($value) && $this->isAssoc($value)) {
                $fields = array_merge($fields, $this->flatten($value, $path));

                continue;
            }

            $fields[] = ['path' => $path, 'sample' => $this->sample($value)];
        }

        return $fields;
    }

    /**
     * @param  array<int|string, mixed>  $value
     */
    private function isAssoc(array $value): bool
    {
        return $value !== [] && ! array_is_list($value);
    }

    private function sample(mixed $value): string
    {
        $text = is_array($value)
            ? (json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '')
            : (string) $value;

        return str($text)->squish()->limit(self::SAMPLE_MAX_LENGTH)->value();
    }
}
