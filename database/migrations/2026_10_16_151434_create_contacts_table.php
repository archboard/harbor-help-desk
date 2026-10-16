<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->scru128('id', 'ctc_');
            $table->foreignScru128('workspace_id', 'wsp_')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('email');
            $table->timestampsTz();
        });

        DB::statement('CREATE UNIQUE INDEX contacts_workspace_id_email_unique ON contacts (workspace_id, lower(email))');
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
