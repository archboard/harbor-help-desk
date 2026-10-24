<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('satisfaction_ratings', function (Blueprint $table) {
            $table->scru128('id', 'csr_');
            $table->foreignScru128('workspace_id', 'wsp_')->constrained()->cascadeOnDelete();
            $table->foreignScru128('ticket_id', 'tkt_')->constrained()->cascadeOnDelete();
            $table->foreignScru128('contact_id', 'ctc_')->constrained()->cascadeOnDelete();
            $table->string('rating');
            $table->text('comment')->nullable();
            $table->timestampsTz();

            $table->unique(['workspace_id', 'ticket_id']);
            $table->index(['workspace_id', 'contact_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('satisfaction_ratings');
    }
};
