<?php

declare(strict_types=1);

namespace App\Services\Social\LinkCard;

use Illuminate\Support\Str;
use Illuminate\Support\Uri;

final readonly class LinkCardMetadata
{
    public function __construct(
        public string $uri,
        public string $title,
        public string $description,
        public ?string $imageUrl,
    ) {}

    /**
     * @return array{uri: string, domain: string, title: string, description: string, image: ?string}
     */
    public function toArray(): array
    {
        return [
            'uri' => $this->uri,
            'domain' => $this->domain(),
            'title' => $this->title,
            'description' => $this->description,
            'image' => $this->imageUrl,
        ];
    }

    /**
     * The bare display host for the card (e.g. "nyt.com" from
     * "https://www.nyt.com/x"), so the frontend renders it without parsing URLs.
     */
    private function domain(): string
    {
        $host = Uri::of($this->uri)->host();

        return $host === null || $host === ''
            ? $this->uri
            : Str::chopStart($host, 'www.');
    }
}
