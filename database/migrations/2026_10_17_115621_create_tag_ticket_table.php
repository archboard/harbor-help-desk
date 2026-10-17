<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tag_ticket', function (Blueprint $table) {
            $table->foreignScru128('workspace_id', 'wsp_')->constrained()->cascadeOnDelete();
            $table->foreignScru128('ticket_id', 'tkt_')->constrained()->cascadeOnDelete();
            $table->foreignScru128('tag_id', 'tag_')->index()->constrained()->cascadeOnDelete();
            $table->timestampsTz();

            $table->primary(['workspace_id', 'ticket_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tag_ticket');
    }
};
