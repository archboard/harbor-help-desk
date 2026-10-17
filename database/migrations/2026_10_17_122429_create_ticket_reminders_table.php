<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_reminders', function (Blueprint $table) {
            $table->scru128('id', 'rmd_');
            $table->foreignScru128('workspace_id', 'wsp_')->constrained()->cascadeOnDelete();
            $table->foreignScru128('ticket_id', 'tkt_')->index()->constrained()->cascadeOnDelete();
            $table->foreignScru128('user_id', 'usr_')->nullable()->index()->constrained()->cascadeOnDelete();
            $table->foreignScru128('created_by', 'usr_')->index()->constrained('users')->cascadeOnDelete();
            $table->timestampTz('remind_at');
            $table->text('note')->nullable();
            $table->timestampTz('sent_at')->nullable();
            $table->timestampsTz();
        });

        DB::statement('CREATE INDEX ticket_reminders_due_index ON ticket_reminders (workspace_id, remind_at) WHERE sent_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_reminders');
    }
};
