<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_events', function (Blueprint $table) {
            $table->scru128('id', 'aud_');
            $table->foreignScru128('workspace_id', 'wsp_')->constrained()->cascadeOnDelete();
            $table->string('actor_type')->nullable();
            $table->string('actor_id', 40)->nullable();
            $table->string('action');
            $table->string('subject_type')->nullable();
            $table->string('subject_id', 40)->nullable();
            $table->jsonb('properties');
            $table->string('ip', 45)->nullable();
            $table->timestampTz('created_at');

            $table->index(['workspace_id', 'created_at']);
            $table->index(['workspace_id', 'action', 'created_at']);
            $table->index(['workspace_id', 'actor_id', 'created_at']);
        });

        // The workspace-wide ticket activity list pages through events by time.
        Schema::table('ticket_events', function (Blueprint $table) {
            $table->index(['workspace_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('ticket_events', function (Blueprint $table) {
            $table->dropIndex(['workspace_id', 'created_at']);
        });

        Schema::dropIfExists('audit_events');
    }
};
