<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('macros', function (Blueprint $table) {
            $table->scru128('id', 'mac_');
            $table->foreignScru128('workspace_id', 'wsp_')->constrained()->cascadeOnDelete();
            $table->foreignScru128('desk_id', 'dsk_')->nullable()->index()->constrained()->cascadeOnDelete();
            $table->foreignScru128('user_id', 'usr_')->nullable()->index()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('body')->nullable();
            $table->boolean('body_is_internal')->default(false);
            $table->jsonb('actions')->default('{}');
            $table->foreignScru128('created_by', 'usr_')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['workspace_id', 'desk_id']);
            $table->index(['workspace_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('macros');
    }
};
