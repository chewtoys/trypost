<?php

declare(strict_types=1);

namespace App\Actions\Automation\Automation;

use App\Models\Automation;
use App\Models\AutomationRun;
use App\Models\AutomationTriggerItem;
use Illuminate\Database\Eloquent\Collection;

class GetAutomationDetails
{
    /**
     * @return array{
     *     runs: Collection<int, AutomationRun>,
     *     triggerItems: Collection<int, AutomationTriggerItem>,
     * }
     */
    public function __invoke(Automation $automation): array
    {
        return [
            'runs' => $automation->runs()->excludingDryRuns()->latest()->take(50)->get(),
            'triggerItems' => $automation->triggerItems()->with('run')->latest()->take(50)->get(),
        ];
    }
}
