<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_events', function (Blueprint $table) {
            $table->scru128('id', 'tev_');
            $table->foreignScru128('workspace_id', 'wsp_')->constrained()->cascadeOnDelete();
            $table->foreignScru128('ticket_id', 'tkt_')->index()->constrained()->cascadeOnDelete();
            $table->string('actor_type')->nullable();
            $table->string('actor_id', 40)->nullable();
            $table->string('type');
            $table->jsonb('properties');
            $table->timestampTz('created_at');

            $table->index(['workspace_id', 'ticket_id', 'created_at']);
            $table->index(['actor_type', 'actor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_events');
    }
};
