<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_user', function (Blueprint $table) {
            $table->foreignScru128('workspace_id', 'wsp_')->constrained()->cascadeOnDelete();
            $table->foreignScru128('ticket_id', 'tkt_')->constrained()->cascadeOnDelete();
            $table->foreignScru128('user_id', 'usr_')->index()->constrained()->cascadeOnDelete();
            $table->timestampsTz();

            $table->primary(['workspace_id', 'ticket_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_user');
    }
};
