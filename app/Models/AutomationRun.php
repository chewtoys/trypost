<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Automation\Run\Status;
use App\Observers\AutomationRunObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy([AutomationRunObserver::class])]
class AutomationRun extends Model
{
    use HasFactory;
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'status' => Status::class,
        'context' => 'array',
        'error' => 'array',
        'is_manual' => 'boolean',
        'is_dry_run' => 'boolean',
        'next_action_at' => 'datetime',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function automation(): BelongsTo
    {
        return $this->belongsTo(Automation::class);
    }

    /**
     * Id of the run that started this execution. Fan-out forks sibling runs that
     * all point back at the same root, so callers can treat every branch of one
     * test/trigger as a single family. The root run points at itself.
     */
    public function rootId(): string
    {
        return $this->root_run_id ?? $this->id;
    }

    /**
     * Context for template (`{{ ... }}`) resolution: the run context plus the
     * automation's workflow variables, merged in-memory. Variables are NEVER
     * persisted into the run context (they're encrypted at rest and would
     * otherwise leak in plaintext via the run/node-run API), so we compute this
     * on demand at resolve time only.
     *
     * @return array<string, mixed>
     */
    public function resolverContext(): array
    {
        return array_merge(
            $this->context ?? [],
            ['variables' => $this->automation->resolvedVariables()],
        );
    }

    public function triggerItem(): BelongsTo
    {
        return $this->belongsTo(AutomationTriggerItem::class, 'trigger_item_id');
    }

    public function generatedPost(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'generated_post_id');
    }

    public function nodeRuns(): HasMany
    {
        return $this->hasMany(AutomationNodeRun::class, 'run_id');
    }

    /**
     * Real, production executions only — excludes manual test runs (both dry
     * runs and "with real data" tests are flagged is_manual). The Invocations
     * and Metrics tabs report on these, not on runs the user triggered to test
     * the editor.
     */
    public function scopeProductionRuns(Builder $query): Builder
    {
        return $query->where('is_manual', false)->where('is_dry_run', false);
    }
}
