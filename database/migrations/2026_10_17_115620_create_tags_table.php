<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tags', function (Blueprint $table) {
            $table->scru128('id', 'tag_');
            $table->foreignScru128('workspace_id', 'wsp_')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestampsTz();
        });

        DB::statement('CREATE UNIQUE INDEX tags_workspace_id_name_unique ON tags (workspace_id, lower(name))');
    }

    public function down(): void
    {
        Schema::dropIfExists('tags');
    }
};
