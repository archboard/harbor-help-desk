<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhooks', function (Blueprint $table) {
            $table->scru128('id', 'whk_');
            $table->foreignScru128('workspace_id', 'wsp_')->constrained()->cascadeOnDelete();
            $table->text('url');
            $table->string('description')->nullable();
            $table->jsonb('event_types');
            $table->text('secret');
            $table->boolean('is_enabled')->default(true);
            $table->unsignedInteger('consecutive_failures')->default(0);
            $table->timestampTz('disabled_at')->nullable();
            $table->foreignScru128('created_by', 'usr_')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['workspace_id', 'is_enabled']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhooks');
    }
};
