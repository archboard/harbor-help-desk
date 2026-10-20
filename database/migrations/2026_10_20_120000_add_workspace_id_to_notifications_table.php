<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Notifications become a tenant table. Existing rows take the workspace from their data and
     * the reminder class name becomes the notification type; rows with no workspace are dropped.
     */
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->foreignScru128('workspace_id', 'wsp_')->nullable()->constrained()->cascadeOnDelete();
            $table->dropIndex(['notifiable_type', 'notifiable_id']);
        });

        DB::statement("UPDATE notifications SET workspace_id = data->>'workspace_id', type = 'reminder_due', data = data - 'workspace_id' WHERE data->>'workspace_id' IS NOT NULL");
        DB::statement('DELETE FROM notifications WHERE workspace_id IS NULL');
        DB::statement('ALTER TABLE notifications ALTER COLUMN workspace_id SET NOT NULL');

        Schema::table('notifications', function (Blueprint $table) {
            $table->index(['workspace_id', 'notifiable_id', 'created_at']);
        });

        DB::statement('CREATE INDEX notifications_workspace_id_notifiable_id_unread_index ON notifications (workspace_id, notifiable_id) WHERE read_at IS NULL');
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('workspace_id');
            $table->index(['notifiable_type', 'notifiable_id']);
        });
    }
};
