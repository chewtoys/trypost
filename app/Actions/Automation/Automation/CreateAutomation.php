<?php

declare(strict_types=1);

namespace App\Actions\Automation\Automation;

use App\Enums\Automation\Status;
use App\Models\Automation;
use App\Models\User;
use App\Models\Workspace;

class CreateAutomation
{
    public function __invoke(Workspace $workspace, User $user, ?string $name = null): Automation
    {
        return Automation::create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'name' => $name ?: __('automations.default_name'),
            'status' => Status::Draft,
            'nodes' => [],
            'connections' => [],
        ]);
    }
}
