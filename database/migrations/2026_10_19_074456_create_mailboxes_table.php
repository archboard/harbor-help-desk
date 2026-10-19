<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mailboxes', function (Blueprint $table) {
            $table->scru128('id', 'mbx_');
            $table->foreignScru128('workspace_id', 'wsp_')->index()->constrained()->cascadeOnDelete();
            $table->foreignScru128('default_desk_id', 'dsk_')->index()->constrained('desks');
            $table->jsonb('routing')->default('[]');
            $table->string('driver');
            $table->text('name');
            $table->text('address');
            $table->text('inbound_address')->nullable();
            $table->text('settings')->nullable();
            $table->text('signature')->nullable();
            $table->unsignedBigInteger('imap_uid_validity')->nullable();
            $table->unsignedBigInteger('imap_last_uid')->nullable();
            $table->timestampTz('polled_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestampsTz();
        });

        DB::statement("CREATE UNIQUE INDEX mailboxes_workspace_id_hosted_unique ON mailboxes (workspace_id) WHERE driver = 'hosted'");
    }

    public function down(): void
    {
        Schema::dropIfExists('mailboxes');
    }
};
