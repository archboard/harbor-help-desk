<?php

namespace Tests\Fixtures;

use App\Models\Concerns\BelongsToWorkspace;
use GrantHolle\Scru128Laravel\Concerns\HasScru128Ids;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Test-only tenant-owned model for exercising BelongsToWorkspace.
 */
#[Fillable(['body'])]
class WorkspaceNote extends Model
{
    use BelongsToWorkspace, HasScru128Ids;

    public function idPrefix(): string
    {
        return 'tst_';
    }

    public static function createTable(): void
    {
        Schema::create('workspace_notes', function (Blueprint $table) {
            $table->scru128('id', 'tst_');
            $table->foreignScru128('workspace_id', 'wsp_')->index()->constrained();
            $table->text('body');
            $table->timestampsTz();
        });
    }
}
