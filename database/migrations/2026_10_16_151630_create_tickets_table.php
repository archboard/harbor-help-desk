<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->scru128('id', 'tkt_');
            $table->foreignScru128('workspace_id', 'wsp_')->constrained()->cascadeOnDelete();
            $table->foreignScru128('desk_id', 'dsk_')->index()->constrained()->cascadeOnDelete();
            $table->foreignScru128('requester_id', 'ctc_')->index()->constrained('contacts');
            $table->unsignedInteger('number');
            $table->string('subject');
            $table->string('status');
            $table->string('priority');
            $table->timestampTz('due_at')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['workspace_id', 'number']);
            $table->index(['workspace_id', 'desk_id', 'status']);
            $table->index(['workspace_id', 'due_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
