<?php

declare(strict_types=1);

use App\DataTransferObjects\MediaItem;

test('altText returns the trimmed meta alt_text', function () {
    $item = MediaItem::fromArray(['id' => 'a', 'path' => 'p.jpg', 'url' => 'u', 'meta' => ['alt_text' => '  a cat  ']]);

    expect($item->altText())->toBe('a cat');
});

test('altText is null when meta has no alt_text or it is blank', function () {
    expect(MediaItem::fromArray(['id' => 'a', 'path' => 'p.jpg', 'url' => 'u'])->altText())->toBeNull()
        ->and(MediaItem::fromArray(['id' => 'a', 'path' => 'p.jpg', 'url' => 'u', 'meta' => ['alt_text' => '   ']])->altText())->toBeNull();
});
