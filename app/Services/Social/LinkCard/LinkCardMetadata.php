<?php

declare(strict_types=1);

namespace App\Services\Social\LinkCard;

final readonly class LinkCardMetadata
{
    public function __construct(
        public string $uri,
        public string $title,
        public string $description,
        public ?string $imageUrl,
    ) {}

    /**
     * @return array{uri: string, title: string, description: string, image: ?string}
     */
    public function toArray(): array
    {
        return [
            'uri' => $this->uri,
            'title' => $this->title,
            'description' => $this->description,
            'image' => $this->imageUrl,
        ];
    }
}
