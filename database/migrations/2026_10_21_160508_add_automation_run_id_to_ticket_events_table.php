<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_events', function (Blueprint $table) {
            $table->string('automation_run_id', 40)->nullable();

            $table->index(['workspace_id', 'automation_run_id']);
        });
    }

    public function down(): void
    {
        Schema::table('ticket_events', function (Blueprint $table) {
            $table->dropIndex(['workspace_id', 'automation_run_id']);
            $table->dropColumn('automation_run_id');
        });
    }
};
