<?php

declare(strict_types=1);

use App\Actions\Post\DuplicatePost;
use App\Enums\Post\CreatedVia;
use App\Enums\Post\Status as PostStatus;
use App\Events\PostCreated;
use App\Models\Post;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Event;

test('execute clones the post as a draft created via web', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['user_id' => $user->id]);
    $original = Post::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
        'content' => 'Original content',
        'created_via' => CreatedVia::Api,
        'status' => PostStatus::Published,
    ]);

    $copy = DuplicatePost::execute($original, $user);

    expect($copy->id)->not->toBe($original->id)
        ->and($copy->content)->toBe('Original content')
        ->and($copy->status)->toBe(PostStatus::Draft)
        ->and($copy->created_via)->toBe(CreatedVia::Web)
        ->and($copy->scheduled_at)->toBeNull()
        ->and($copy->published_at)->toBeNull();
});

test('execute dispatches PostCreated for the duplicated post', function () {
    Event::fake([PostCreated::class]);

    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['user_id' => $user->id]);
    $original = Post::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
    ]);

    $copy = DuplicatePost::execute($original, $user);

    Event::assertDispatched(
        PostCreated::class,
        fn (PostCreated $event) => $event->post->id === $copy->id,
    );
});
