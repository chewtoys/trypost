<?php

declare(strict_types=1);

use App\Enums\PostPlatform\ContentType;
use App\Models\PostPlatform;
use Illuminate\Support\Facades\DB;

/**
 * Load the data migration as an instance so its up() can run against rows that
 * still carry the retired per-format content types.
 */
function collapseLinkedInContentTypesMigration(): object
{
    return require database_path('migrations/2026_06_24_220651_collapse_linkedin_content_types.php');
}

test('the migration collapses retired linkedin profile content types onto linkedin_post', function () {
    $platform = PostPlatform::factory()->create(['content_type' => ContentType::LinkedInPost]);

    foreach (['linkedin_carousel', 'linkedin_document'] as $legacy) {
        DB::table('post_platforms')->where('id', $platform->id)->update(['content_type' => $legacy]);

        collapseLinkedInContentTypesMigration()->up();

        expect(DB::table('post_platforms')->where('id', $platform->id)->value('content_type'))
            ->toBe('linkedin_post');
    }
});

test('the migration collapses retired linkedin page content types onto linkedin_page_post', function () {
    $platform = PostPlatform::factory()->create(['content_type' => ContentType::LinkedInPagePost]);

    foreach (['linkedin_page_carousel', 'linkedin_page_document'] as $legacy) {
        DB::table('post_platforms')->where('id', $platform->id)->update(['content_type' => $legacy]);

        collapseLinkedInContentTypesMigration()->up();

        expect(DB::table('post_platforms')->where('id', $platform->id)->value('content_type'))
            ->toBe('linkedin_page_post');
    }
});

test('the migration leaves other platforms content types untouched', function () {
    $xPost = PostPlatform::factory()->create(['content_type' => ContentType::XPost]);

    collapseLinkedInContentTypesMigration()->up();

    expect(DB::table('post_platforms')->where('id', $xPost->id)->value('content_type'))
        ->toBe(ContentType::XPost->value);
});
