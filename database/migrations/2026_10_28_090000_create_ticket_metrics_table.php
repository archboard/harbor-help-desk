<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_metrics', function (Blueprint $table) {
            $table->scru128('id', 'tmt_');
            $table->foreignScru128('workspace_id', 'wsp_')->constrained()->cascadeOnDelete();
            $table->foreignScru128('ticket_id', 'tkt_')->index()->constrained()->cascadeOnDelete();
            $table->timestampTz('first_responded_at')->nullable();
            $table->foreignScru128('first_responder_id', 'usr_')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('first_response_minutes')->nullable();
            $table->unsignedInteger('first_response_business_minutes')->nullable();
            $table->timestampTz('resolved_at')->nullable();
            $table->unsignedInteger('resolution_minutes')->nullable();
            $table->unsignedInteger('resolution_business_minutes')->nullable();
            $table->unsignedInteger('reopen_count')->default(0);
            $table->timestampsTz();

            $table->unique(['workspace_id', 'ticket_id']);
            $table->index(['workspace_id', 'resolved_at']);
            $table->index(['workspace_id', 'first_responded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_metrics');
    }
};
