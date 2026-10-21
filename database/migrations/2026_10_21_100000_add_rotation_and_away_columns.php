<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('desk_user', function (Blueprint $table) {
            $table->boolean('in_rotation')->default(true);
            $table->timestampTz('last_assigned_at')->nullable();
        });

        Schema::table('workspace_user', function (Blueprint $table) {
            $table->timestampTz('away_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('desk_user', function (Blueprint $table) {
            $table->dropColumn(['in_rotation', 'last_assigned_at']);
        });

        Schema::table('workspace_user', function (Blueprint $table) {
            $table->dropColumn('away_at');
        });
    }
};
