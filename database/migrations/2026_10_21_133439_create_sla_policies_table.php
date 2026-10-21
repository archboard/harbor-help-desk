<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sla_policies', function (Blueprint $table) {
            $table->scru128('id', 'slp_');
            $table->foreignScru128('workspace_id', 'wsp_')->constrained()->cascadeOnDelete();
            $table->foreignScru128('business_schedule_id', 'bsc_')->nullable()->constrained()->restrictOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('warning_percent')->default(75);
            $table->jsonb('targets');
            $table->timestampsTz();

            $table->index(['workspace_id', 'name']);
            $table->index(['workspace_id', 'business_schedule_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sla_policies');
    }
};
