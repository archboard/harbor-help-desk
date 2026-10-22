<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Automations set ticket-level reminders and have no user to credit.
     */
    public function up(): void
    {
        Schema::table('ticket_reminders', function (Blueprint $table) {
            $table->foreignScru128('created_by', 'usr_')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('ticket_reminders', function (Blueprint $table) {
            $table->foreignScru128('created_by', 'usr_')->nullable(false)->change();
        });
    }
};
