<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('workspace_user', function (Blueprint $table) {
            $table->foreignScru128('workspace_id', 'wsp_')->constrained()->cascadeOnDelete();
            $table->foreignScru128('user_id', 'usr_')->index()->constrained()->cascadeOnDelete();
            $table->string('role');
            $table->timestampsTz();

            $table->primary(['workspace_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workspace_user');
    }
};
