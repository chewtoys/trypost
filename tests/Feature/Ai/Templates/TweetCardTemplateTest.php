<?php

declare(strict_types=1);

use App\Ai\Templates\TemplateContext;
use App\Ai\Templates\TweetCardTemplate;
use App\Enums\Ai\ContentStyle;
use App\Models\Workspace;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;

test('tweet card template identity', function () {
    $t = new TweetCardTemplate;
    $workspace = Workspace::factory()->create();
    $singleContext = new TemplateContext($workspace, null, 'x_post', 1, false);
    $carouselContext = new TemplateContext($workspace, null, 'instagram_carousel', 2, true);

    expect($t->style())->toBe(ContentStyle::TweetCard)
        ->and($t->key())->toBe('tweet_card')
        ->and($t->needsAccount())->toBeTrue()
        ->and($t->style()->isTweetCard())->toBeTrue()
        ->and($t->promptView($singleContext))->toBe('prompts.post_content.tweet_card')
        ->and($t->promptView($carouselContext))->toBe('prompts.post_content.tweet_card_carousel');
});

test('tweet card single schema returns tweet_text only', function () {
    $t = new TweetCardTemplate;
    $workspace = Workspace::factory()->create();
    $context = new TemplateContext($workspace, null, 'x_post', 1, false);

    $schema = new JsonSchemaTypeFactory;
    $result = $t->schema($schema, $context);

    expect($result)->toHaveKey('tweet_text')
        ->and($result)->not->toHaveKey('caption');
});

test('tweet card carousel schema returns caption and slides', function () {
    $t = new TweetCardTemplate;
    $workspace = Workspace::factory()->create();
    $context = new TemplateContext($workspace, null, 'instagram_carousel', 3, true);

    $schema = new JsonSchemaTypeFactory;
    $result = $t->schema($schema, $context);

    expect($result)->toHaveKeys(['caption', 'slides']);
});
