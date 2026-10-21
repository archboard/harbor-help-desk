<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_schedules', function (Blueprint $table) {
            $table->scru128('id', 'bsc_');
            $table->foreignScru128('workspace_id', 'wsp_')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('timezone');
            $table->jsonb('weekly_hours');
            $table->jsonb('holidays')->default('[]');
            $table->timestampsTz();

            $table->index(['workspace_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_schedules');
    }
};
