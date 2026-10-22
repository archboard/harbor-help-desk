<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automations', function (Blueprint $table) {
            $table->scru128('id', 'aut_');
            $table->foreignScru128('workspace_id', 'wsp_')->constrained()->cascadeOnDelete();
            $table->foreignScru128('desk_id', 'dsk_')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('enabled')->default(false);
            $table->unsignedInteger('position');
            $table->string('trigger_type');
            $table->jsonb('definition');
            $table->unsignedSmallInteger('schema_version');
            $table->string('webhook_token_hash')->nullable();
            $table->foreignScru128('created_by', 'usr_')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->foreignScru128('updated_by', 'usr_')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['workspace_id', 'enabled', 'trigger_type', 'position']);
            $table->index(['workspace_id', 'desk_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automations');
    }
};
