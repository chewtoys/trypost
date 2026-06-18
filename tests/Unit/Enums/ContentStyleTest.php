<?php

declare(strict_types=1);

use App\Enums\Ai\ContentStyle;

test('enum has exactly 3 cases with the correct string values', function () {
    expect(ContentStyle::cases())->toHaveCount(3);
    expect(ContentStyle::ImageCard->value)->toBe('image_card');
    expect(ContentStyle::TweetCard->value)->toBe('tweet_card');
    expect(ContentStyle::TweetCardImage->value)->toBe('tweet_card_image');
});

test('default returns ImageCard', function () {
    expect(ContentStyle::default())->toBe(ContentStyle::ImageCard);
});

test('needsAccount is false only for ImageCard', function () {
    expect(ContentStyle::ImageCard->needsAccount())->toBeFalse();
    expect(ContentStyle::TweetCard->needsAccount())->toBeTrue();
    expect(ContentStyle::TweetCardImage->needsAccount())->toBeTrue();
});

test('humanizes is true only for ImageCard', function () {
    expect(ContentStyle::ImageCard->humanizes())->toBeTrue();
    expect(ContentStyle::TweetCard->humanizes())->toBeFalse();
    expect(ContentStyle::TweetCardImage->humanizes())->toBeFalse();
});

test('previewAsset returns the correct public path for each case', function () {
    expect(ContentStyle::ImageCard->previewAsset())->toBe('/images/ai-templates/image-card.png');
    expect(ContentStyle::TweetCard->previewAsset())->toBe('/images/ai-templates/tweet-card.png');
    expect(ContentStyle::TweetCardImage->previewAsset())->toBe('/images/ai-templates/tweet-card-image.png');
});

test('label returns the expected i18n key format', function () {
    expect(ContentStyle::ImageCard->label())->toBe('posts.ai.templates.image_card.name');
    expect(ContentStyle::TweetCard->label())->toBe('posts.ai.templates.tweet_card.name');
    expect(ContentStyle::TweetCardImage->label())->toBe('posts.ai.templates.tweet_card_image.name');
});

test('description returns the expected i18n key format', function () {
    expect(ContentStyle::ImageCard->description())->toBe('posts.ai.templates.image_card.description');
    expect(ContentStyle::TweetCard->description())->toBe('posts.ai.templates.tweet_card.description');
    expect(ContentStyle::TweetCardImage->description())->toBe('posts.ai.templates.tweet_card_image.description');
});
