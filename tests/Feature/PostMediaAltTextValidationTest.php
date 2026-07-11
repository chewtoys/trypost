<?php

declare(strict_types=1);

use App\Enums\UserWorkspace\Role;
use App\Models\Post;
use App\Models\User;
use App\Models\Workspace;

test('post update keeps media alt_text in meta', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['user_id' => $user->id]);
    $workspace->members()->attach($user->id, ['role' => Role::Member->value]);
    $user->update(['current_workspace_id' => $workspace->id]);

    $post = Post::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
    ]);

    $response = $this->actingAs($user)->put(route('app.posts.update', $post), [
        'status' => 'draft',
        'content' => 'hi',
        'media' => [[
            'id' => 'm1', 'path' => 'uploads/x.jpg', 'url' => 'https://cdn.test/x.jpg',
            'meta' => ['alt_text' => 'a golden retriever on a beach'],
        ]],
    ]);

    $response->assertSessionDoesntHaveErrors();
    expect($post->fresh()->media[0]['meta']['alt_text'])->toBe('a golden retriever on a beach');
});

test('media alt_text over 2000 chars is rejected', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['user_id' => $user->id]);
    $workspace->members()->attach($user->id, ['role' => Role::Member->value]);
    $user->update(['current_workspace_id' => $workspace->id]);

    $post = Post::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
    ]);

    $response = $this->actingAs($user)->put(route('app.posts.update', $post), [
        'status' => 'draft',
        'content' => 'hi',
        'media' => [[
            'id' => 'm1', 'path' => 'uploads/x.jpg', 'url' => 'https://cdn.test/x.jpg',
            'meta' => ['alt_text' => str_repeat('a', 2001)],
        ]],
    ]);

    $response->assertSessionHasErrors('media.0.meta.alt_text');
});
