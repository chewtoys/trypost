<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A single execution row for the Invocations tab: enough to render the list
 * (status, timing, step count, error summary) without loading every node run.
 */
class AutomationInvocationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $durationMs = $this->started_at !== null && $this->finished_at !== null
            ? $this->started_at->diffInMilliseconds($this->finished_at)
            : null;

        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'is_manual' => (bool) $this->is_manual,
            'node_run_count' => (int) ($this->node_runs_count ?? 0),
            'duration_ms' => $durationMs,
            'error_message' => is_array($this->error) ? ($this->error['message'] ?? null) : $this->error,
            'created_at' => $this->created_at,
            'started_at' => $this->started_at,
            'finished_at' => $this->finished_at,
        ];
    }
}
