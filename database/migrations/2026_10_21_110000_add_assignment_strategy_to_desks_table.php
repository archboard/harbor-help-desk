<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('desks', function (Blueprint $table) {
            $table->string('assignment_strategy')->default('manual');
        });
    }

    public function down(): void
    {
        Schema::table('desks', function (Blueprint $table) {
            $table->dropColumn('assignment_strategy');
        });
    }
};
