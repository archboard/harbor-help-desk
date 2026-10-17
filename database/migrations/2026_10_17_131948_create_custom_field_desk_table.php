<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_field_desk', function (Blueprint $table) {
            $table->foreignScru128('workspace_id', 'wsp_')->constrained()->cascadeOnDelete();
            $table->foreignScru128('custom_field_id', 'cfd_')->constrained()->cascadeOnDelete();
            $table->foreignScru128('desk_id', 'dsk_')->index()->constrained()->cascadeOnDelete();

            $table->primary(['workspace_id', 'custom_field_id', 'desk_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_field_desk');
    }
};
