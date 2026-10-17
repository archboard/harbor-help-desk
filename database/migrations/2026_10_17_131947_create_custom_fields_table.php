<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_fields', function (Blueprint $table) {
            $table->scru128('id', 'cfd_');
            $table->foreignScru128('workspace_id', 'wsp_')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type');
            $table->jsonb('options')->nullable();
            $table->boolean('is_required')->default(false);
            $table->integer('position')->default(0);
            $table->timestampsTz();
        });

        DB::statement('CREATE UNIQUE INDEX custom_fields_workspace_id_name_unique ON custom_fields (workspace_id, lower(name))');
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_fields');
    }
};
