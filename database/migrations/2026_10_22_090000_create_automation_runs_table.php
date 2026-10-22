<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation_runs', function (Blueprint $table) {
            $table->scru128('id', 'arn_');
            $table->foreignScru128('workspace_id', 'wsp_')->constrained()->cascadeOnDelete();
            $table->foreignScru128('automation_id', 'aut_')->constrained()->cascadeOnDelete();
            $table->foreignScru128('ticket_id', 'tkt_')->nullable()->index()->constrained()->cascadeOnDelete();
            $table->foreignScru128('ticket_event_id', 'tev_')->nullable()->index();
            $table->foreignScru128('parent_run_id', 'arn_')->nullable()->index();
            $table->unsignedTinyInteger('depth')->default(0);
            $table->string('status');
            $table->jsonb('definition');
            $table->jsonb('log');
            $table->jsonb('state');
            $table->timestampTz('resume_at')->nullable();
            $table->text('error')->nullable();
            $table->timestampsTz();

            $table->index(['workspace_id', 'automation_id', 'created_at']);
            $table->index(['workspace_id', 'ticket_id', 'created_at']);
        });

        DB::statement("CREATE INDEX automation_runs_resume_at_index ON automation_runs (resume_at) WHERE status = 'waiting'");

        Schema::table('ticket_events', function (Blueprint $table) {
            $table->foreign('automation_run_id')->references('id')->on('automation_runs')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ticket_events', function (Blueprint $table) {
            $table->dropForeign(['automation_run_id']);
        });

        Schema::dropIfExists('automation_runs');
    }
};
