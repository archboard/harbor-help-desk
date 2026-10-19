<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            // The mailbox the ticket's email thread lives in; replies go out through it.
            $table->foreignScru128('mailbox_id', 'mbx_')->nullable()->index()->constrained()->nullOnDelete();
        });

        Schema::table('ticket_comments', function (Blueprint $table) {
            // The email Message-ID, used to thread replies and dedupe inbound mail.
            $table->text('message_id')->nullable();
        });

        DB::statement('CREATE UNIQUE INDEX ticket_comments_workspace_id_message_id_unique ON ticket_comments (workspace_id, message_id) WHERE message_id IS NOT NULL');
    }

    public function down(): void
    {
        Schema::table('ticket_comments', function (Blueprint $table) {
            $table->dropColumn('message_id');
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('mailbox_id');
        });
    }
};
