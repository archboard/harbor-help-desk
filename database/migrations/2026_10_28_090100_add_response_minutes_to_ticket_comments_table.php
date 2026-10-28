<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_comments', function (Blueprint $table) {
            $table->unsignedInteger('response_minutes')->nullable();
            $table->unsignedInteger('response_business_minutes')->nullable();
        });

        DB::statement('CREATE INDEX ticket_comments_responses_index ON ticket_comments (workspace_id, created_at) WHERE response_minutes IS NOT NULL');

        Schema::table('tickets', function (Blueprint $table) {
            $table->index(['workspace_id', 'created_at']);
        });

        Schema::table('ticket_events', function (Blueprint $table) {
            $table->index(['workspace_id', 'type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('ticket_events', function (Blueprint $table) {
            $table->dropIndex(['workspace_id', 'type', 'created_at']);
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex(['workspace_id', 'created_at']);
        });

        DB::statement('DROP INDEX ticket_comments_responses_index');

        Schema::table('ticket_comments', function (Blueprint $table) {
            $table->dropColumn(['response_minutes', 'response_business_minutes']);
        });
    }
};
