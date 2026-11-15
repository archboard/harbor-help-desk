<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspace_invitations', function (Blueprint $table) {
            $table->scru128('id', 'inv_');
            $table->foreignScru128('workspace_id', 'wsp_')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('role');
            $table->foreignScru128('desk_id', 'dsk_')->nullable()->constrained()->nullOnDelete();
            $table->foreignScru128('invited_by_id', 'usr_')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['workspace_id', 'desk_id']);
            $table->index(['workspace_id', 'invited_by_id']);
        });

        DB::statement('CREATE UNIQUE INDEX workspace_invitations_workspace_id_email_unique ON workspace_invitations (workspace_id, lower(email))');
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_invitations');
    }
};
