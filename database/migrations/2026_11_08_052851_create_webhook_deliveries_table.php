<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->scru128('id', 'whd_');
            $table->foreignScru128('workspace_id', 'wsp_')->constrained()->cascadeOnDelete();
            $table->foreignScru128('webhook_id', 'whk_')->index()->constrained()->cascadeOnDelete();
            $table->foreignScru128('ticket_event_id', 'tev_')->nullable();
            $table->string('event_type');
            $table->string('status');
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('error')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestampTz('delivered_at')->nullable();
            $table->timestampTz('created_at');

            $table->index(['workspace_id', 'webhook_id', 'created_at']);
            $table->index(['workspace_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
    }
};
