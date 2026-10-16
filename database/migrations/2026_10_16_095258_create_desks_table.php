<?php

use GrantHolle\Scru128\Scru128;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('desks', function (Blueprint $table) {
            $table->scru128('id', 'dsk_');
            $table->foreignScru128('workspace_id', 'wsp_')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->timestampsTz();
            $table->softDeletesTz('archived_at');
        });

        DB::statement('CREATE UNIQUE INDEX desks_workspace_id_slug_unique ON desks (workspace_id, slug) WHERE archived_at IS NULL');

        // Every workspace always has at least one desk.
        DB::table('workspaces')->get(['id', 'name'])->each(fn (object $workspace) => DB::table('desks')->insert([
            'id' => 'dsk_'.Scru128::generate(),
            'workspace_id' => $workspace->id,
            'name' => $workspace->name,
            'slug' => 'default',
            'created_at' => now(),
            'updated_at' => now(),
        ]));
    }

    public function down(): void
    {
        Schema::dropIfExists('desks');
    }
};
