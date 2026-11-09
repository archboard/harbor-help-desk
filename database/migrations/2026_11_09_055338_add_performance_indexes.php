<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Indexes found by EXPLAIN on the volume seed. The trigram GIN indexes can't lead with
 * `workspace_id` without `btree_gin`, so they are an exception to tenant indexes starting
 * with it. Searches still filter by workspace through the tenant scope.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('create extension if not exists pg_trgm');

        DB::statement('create index tickets_workspace_id_updated_at_index on tickets (workspace_id, updated_at desc, id desc) where deleted_at is null');

        DB::statement('create index contacts_name_trgm on contacts using gin (name gin_trgm_ops)');
        DB::statement('create index contacts_email_trgm on contacts using gin (email gin_trgm_ops)');
        DB::statement('create index contacts_workspace_id_name_index on contacts (workspace_id, name, id)');
    }

    public function down(): void
    {
        DB::statement('drop index tickets_workspace_id_updated_at_index');
        DB::statement('drop index contacts_name_trgm');
        DB::statement('drop index contacts_email_trgm');
        DB::statement('drop index contacts_workspace_id_name_index');
    }
};
