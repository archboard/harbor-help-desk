<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exports', function (Blueprint $table) {
            $table->scru128('id', 'exp_');
            $table->foreignScru128('workspace_id', 'wsp_')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('subject_id', 40)->nullable();
            $table->foreignScru128('requested_by', 'usr_')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status');
            $table->text('path')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampTz('expires_at');
            $table->timestampsTz();

            $table->index(['workspace_id', 'created_at']);
            $table->index(['workspace_id', 'requested_by']);
            // The daily prune looks across workspaces.
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exports');
    }
};
