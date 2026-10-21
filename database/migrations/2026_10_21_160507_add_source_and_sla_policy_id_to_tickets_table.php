<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('source')->default('web');
            $table->foreignScru128('sla_policy_id', 'slp_')->nullable()->constrained()->nullOnDelete();

            $table->index(['workspace_id', 'sla_policy_id']);
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sla_policy_id');
            $table->dropColumn('source');
        });
    }
};
