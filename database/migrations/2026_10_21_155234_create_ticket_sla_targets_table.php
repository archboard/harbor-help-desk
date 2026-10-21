<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_sla_targets', function (Blueprint $table) {
            $table->scru128('id', 'slt_');
            $table->foreignScru128('workspace_id', 'wsp_')->constrained()->cascadeOnDelete();
            $table->foreignScru128('ticket_id', 'tkt_')->index()->constrained()->cascadeOnDelete();
            $table->string('metric');
            $table->unsignedInteger('target_minutes');
            $table->timestampTz('started_at');
            $table->timestampTz('paused_at')->nullable();
            $table->unsignedInteger('paused_business_minutes')->default(0);
            $table->timestampTz('warn_at')->nullable();
            $table->timestampTz('due_at')->nullable();
            $table->timestampTz('warned_at')->nullable();
            $table->timestampTz('breached_at')->nullable();
            $table->timestampTz('achieved_at')->nullable();
            $table->timestampsTz();

            $table->unique(['workspace_id', 'ticket_id', 'metric']);
        });

        DB::statement('CREATE INDEX ticket_sla_targets_due_index ON ticket_sla_targets (workspace_id, due_at) WHERE achieved_at IS NULL AND breached_at IS NULL AND due_at IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_sla_targets');
    }
};
