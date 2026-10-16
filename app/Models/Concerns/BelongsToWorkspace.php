<?php

namespace App\Models\Concerns;

use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Scopes a tenant-owned model to the current workspace and fills `workspace_id` on create.
 * With no current workspace, queries match nothing. Remove the scope only in landlord code:
 * `Model::withoutGlobalScope('workspace')`.
 *
 * @mixin Model
 */
trait BelongsToWorkspace
{
    public static function bootBelongsToWorkspace(): void
    {
        static::addGlobalScope('workspace', function (Builder $query): void {
            $query->where($query->qualifyColumn('workspace_id'), Workspace::current()?->getKey());
        });

        static::creating(function (Model $model): void {
            if ($model->getAttribute('workspace_id') === null) {
                $model->setAttribute('workspace_id', Workspace::current()?->getKey());
            }
        });
    }

    /**
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
