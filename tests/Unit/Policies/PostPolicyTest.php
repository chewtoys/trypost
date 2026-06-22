<?php

declare(strict_types=1);

use App\Models\Account;
use App\Models\Post;
use App\Models\User;
use App\Models\Workspace;
use App\Policies\PostPolicy;

beforeEach(function () {
    $this->policy = new PostPolicy;
});

/**
 * Build a post + an actor with the given workspace role, both in one account.
 *
 * @return array{0: User, 1: Post}
 */
function postPolicyActor(string $role): array
{
    $account = Account::factory()->create();
    $owner = User::factory()->create(['account_id' => $account->id]);
    $account->update(['owner_id' => $owner->id]);
    $workspace = Workspace::factory()->create(['account_id' => $account->id, 'user_id' => $owner->id]);
    $post = Post::factory()->create(['workspace_id' => $workspace->id]);

    if ($role === 'owner') {
        $actor = $owner;
    } else {
        $actor = User::factory()->create(['account_id' => $account->id]);
        $workspace->members()->attach($actor->id, ['role' => $role]);
    }

    $actor->update(['current_workspace_id' => $workspace->id]);

    return [$actor->refresh(), $post];
}

test('any workspace member (including viewer) can view a post', function (string $role) {
    [$actor, $post] = postPolicyActor($role);

    expect($this->policy->view($actor, $post))->toBeTrue();
})->with(['owner', 'admin', 'member', 'viewer']);

test('post update/delete/duplicate is allowed for member+ and denied for viewer', function (string $role, bool $allowed) {
    [$actor, $post] = postPolicyActor($role);

    expect($this->policy->update($actor, $post))->toBe($allowed);
    expect($this->policy->delete($actor, $post))->toBe($allowed);
    expect($this->policy->duplicate($actor, $post))->toBe($allowed);
})->with([
    'owner' => ['owner', true],
    'admin' => ['admin', true],
    'member' => ['member', true],
    'viewer' => ['viewer', false],
]);
