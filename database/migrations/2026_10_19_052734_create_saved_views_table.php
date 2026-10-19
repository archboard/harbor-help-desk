<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_views', function (Blueprint $table) {
            $table->scru128('id', 'svw_');
            $table->foreignScru128('workspace_id', 'wsp_')->constrained()->cascadeOnDelete();
            $table->foreignScru128('user_id', 'usr_')->index()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->jsonb('filters');
            $table->boolean('is_shared')->default(false);
            $table->timestampsTz();

            $table->index(['workspace_id', 'user_id']);
        });

        DB::statement('CREATE INDEX saved_views_workspace_id_shared_index ON saved_views (workspace_id) WHERE is_shared');
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_views');
    }
};
