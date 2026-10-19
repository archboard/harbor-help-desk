<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * GIN full-text indexes can't lead with `workspace_id` without the `btree_gin` extension,
 * so these are the exception to tenant indexes starting with it. Searches still filter by
 * workspace through the tenant scope.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->fullText('subject');
        });

        Schema::table('ticket_comments', function (Blueprint $table) {
            $table->fullText('body');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropFullText(['subject']);
        });

        Schema::table('ticket_comments', function (Blueprint $table) {
            $table->dropFullText(['body']);
        });
    }
};
