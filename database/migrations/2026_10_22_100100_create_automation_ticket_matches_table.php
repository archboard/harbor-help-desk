<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation_ticket_matches', function (Blueprint $table) {
            $table->scru128('id', 'atm_');
            $table->foreignScru128('workspace_id', 'wsp_')->constrained()->cascadeOnDelete();
            $table->foreignScru128('automation_id', 'aut_')->constrained()->cascadeOnDelete();
            $table->foreignScru128('ticket_id', 'tkt_')->constrained()->cascadeOnDelete();
            $table->timestampTz('fired_at');
            $table->timestampsTz();

            $table->unique(['workspace_id', 'automation_id', 'ticket_id']);
            $table->index(['workspace_id', 'ticket_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_ticket_matches');
    }
};
