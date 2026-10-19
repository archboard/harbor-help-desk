<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mailboxes', function (Blueprint $table) {
            $table->text('push_subscription_id')->nullable();
            $table->timestampTz('push_expires_at')->nullable();
            $table->text('push_secret')->nullable();
        });

        // Landlord index, an exception to the tenant-index rule: Gmail's push names only the
        // email address, so the webhook finds the mailbox across workspaces.
        DB::statement("CREATE INDEX mailboxes_google_address_index ON mailboxes (lower(address)) WHERE driver = 'google'");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS mailboxes_google_address_index');

        Schema::table('mailboxes', function (Blueprint $table) {
            $table->dropColumn(['push_subscription_id', 'push_expires_at', 'push_secret']);
        });
    }
};
